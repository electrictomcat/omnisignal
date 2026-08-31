<?php

namespace App\Http\Controllers;

use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Domain-limit per tier. Kept here rather than inline so the mapping is in
     * one place and testable.
     */
    protected const TIER_LIMITS = [
        'starter' => 1,
        'pro' => 5,
        'agency' => 999999,
    ];

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventName = $request->input('meta.event_name');
        $data = $request->input('data');
        $attributes = is_array($data) ? ($data['attributes'] ?? []) : [];
        $customData = $request->input('meta.custom_data', []);

        Log::info("[LemonSqueezy Webhook] Received event: {$eventName}");

        switch ($eventName) {
            case 'order_created':
                $this->handleOrderCreated($attributes, is_array($customData) ? $customData : []);
                break;
            case 'order_refunded':
                $this->handleOrderRefunded($attributes);
                break;
            case 'license_key_created':
                $this->handleLicenseKeyCreated($attributes);
                break;
            case 'subscription_created':
            case 'subscription_updated':
            case 'subscription_cancelled':
            case 'subscription_expired':
                $this->handleSubscriptionEvent($attributes);
                break;
            case 'subscription_payment_failed':
                $this->handlePaymentFailed($attributes);
                break;
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Verify the Lemon Squeezy HMAC.
     *
     * Both the secret and the header are mandatory. Treating a missing header
     * as "nothing to check" let anyone POST an order_created and mint
     * themselves a license, so the absence of either is now a rejection.
     */
    protected function hasValidSignature(Request $request): bool
    {
        $signingSecret = config('services.lemonsqueezy.signing_secret');

        if (empty($signingSecret)) {
            Log::critical(
                '[LemonSqueezy Webhook] LEMON_SQUEEZY_SIGNING_SECRET is not configured; '
                .'rejecting webhook. Set it or no order will ever be processed.'
            );

            return false;
        }

        $signature = (string) $request->header('X-Signature', '');

        if ($signature === '') {
            Log::warning('[LemonSqueezy Webhook] Rejected: no X-Signature header.');

            return false;
        }

        $computed = hash_hmac('sha256', $request->getContent(), $signingSecret);

        if (! hash_equals($computed, $signature)) {
            Log::warning('[LemonSqueezy Webhook] Rejected: signature mismatch.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $customData
     */
    protected function handleOrderCreated(array $attributes, array $customData): void
    {
        $orderId = (string) ($attributes['identifier'] ?? $attributes['first_order_item']['order_id'] ?? $attributes['order_number'] ?? '');

        // Without an order ID every such delivery collapses onto one row and
        // overwrites the previous customer's license.
        if ($orderId === '') {
            Log::error('[LemonSqueezy Webhook] order_created with no usable order identifier; ignoring.');

            return;
        }

        $customerId = (string) ($attributes['customer_id'] ?? '');
        $email = strtolower(trim((string) ($attributes['user_email'] ?? $attributes['customer_email'] ?? '')));
        $name = (string) ($attributes['user_name'] ?? $attributes['customer_name'] ?? '');

        $variantId = (string) ($attributes['first_order_item']['variant_id'] ?? '');
        $productId = (string) ($attributes['first_order_item']['product_id'] ?? '');
        $productName = strtolower((string) ($attributes['first_order_item']['product_name'] ?? ''));

        $tier = $this->resolveTier($customData, $productName, $variantId, $productId);
        $limit = self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS['pro'];

        $license = License::firstOrNew(['order_id' => $orderId]);

        // Only mint a key for a genuinely new order. Lemon Squeezy retries
        // webhooks, and regenerating here would silently invalidate the key the
        // customer has already installed.
        if (! $license->exists) {
            $license->license_key = License::generateKey();
        }

        $license->fill([
            'customer_id' => $customerId ?: null,
            'customer_email' => $email,
            'customer_name' => $name,
            'product_id' => $productId ?: null,
            'variant_id' => $variantId ?: null,
            'tier' => $tier,
            'status' => 'active',
            'activation_limit' => $limit,
        ]);

        if (! $license->expires_at) {
            $license->expires_at = now()->addYear();
        }

        $license->save();

        Log::info("[LemonSqueezy Webhook] {$tier} license for order {$orderId} ({$email}), limit {$limit}.");
    }

    /**
     * Work out which tier an order belongs to.
     *
     * Checkout custom data wins; then the configured variant IDs; then the
     * product name. Variant IDs come from config rather than being inlined so
     * renaming a product in Lemon Squeezy cannot silently reassign customers.
     *
     * @param  array<string, mixed>  $customData
     */
    protected function resolveTier(array $customData, string $productName, string $variantId, string $productId): string
    {
        if (! empty($customData['tier'])) {
            $candidate = strtolower(trim((string) $customData['tier']));

            if (array_key_exists($candidate, self::TIER_LIMITS)) {
                return $candidate;
            }
        }

        foreach (array_keys(self::TIER_LIMITS) as $tier) {
            $variants = array_filter([
                (string) config("services.lemonsqueezy.variants.{$tier}"),
                (string) config("services.lemonsqueezy.variants.{$tier}_onetime"),
            ]);

            if ($variantId !== '' && in_array($variantId, $variants, true)) {
                return $tier;
            }

            $configuredProduct = (string) config("services.lemonsqueezy.products.{$tier}");
            if ($productId !== '' && $configuredProduct !== '' && $productId === $configuredProduct) {
                return $tier;
            }

            if ($productName !== '' && str_contains($productName, $tier)) {
                return $tier;
            }
        }

        Log::warning(
            "[LemonSqueezy Webhook] Could not classify order (variant={$variantId}, product={$productId}, "
            ."name='{$productName}'); defaulting to pro."
        );

        return 'pro';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function handleOrderRefunded(array $attributes): void
    {
        $orderId = (string) ($attributes['identifier'] ?? $attributes['order_id'] ?? $attributes['order_number'] ?? '');

        if ($orderId === '') {
            return;
        }

        License::where('order_id', $orderId)->update([
            'status' => 'refunded',
            'instances' => [],
            'activation_count' => 0,
        ]);

        Log::info("[LemonSqueezy Webhook] Order {$orderId} refunded; license deactivated and instances cleared.");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function handleLicenseKeyCreated(array $attributes): void
    {
        $key = (string) ($attributes['key'] ?? '');
        $orderId = (string) ($attributes['order_id'] ?? '');
        $activationLimit = (int) ($attributes['activation_limit'] ?? 1);

        if ($key === '' || $orderId === '') {
            return;
        }

        License::where('order_id', $orderId)->update([
            'license_key' => $key,
            'activation_limit' => $activationLimit,
        ]);

        Log::info("[LemonSqueezy Webhook] Adopted Lemon Squeezy native key for order {$orderId}.");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function handleSubscriptionEvent(array $attributes): void
    {
        $status = (string) ($attributes['status'] ?? 'active');
        $orderId = (string) ($attributes['order_id'] ?? '');

        if ($orderId === '') {
            return;
        }

        License::where('order_id', $orderId)->update([
            'status' => $status === 'active' ? 'active' : 'inactive',
        ]);

        Log::info("[LemonSqueezy Webhook] Subscription status '{$status}' applied to order {$orderId}.");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function handlePaymentFailed(array $attributes): void
    {
        $orderId = (string) ($attributes['order_id'] ?? '');

        if ($orderId !== '') {
            Log::warning("[LemonSqueezy Webhook] Subscription payment failed for order {$orderId}.");
        }
    }
}
