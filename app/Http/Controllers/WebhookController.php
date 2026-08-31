<?php

namespace App\Http\Controllers;

use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $signingSecret = config('services.lemonsqueezy.signing_secret');
        $signature = $request->header('X-Signature');

        if ($signingSecret && $signature) {
            $computedSignature = hash_hmac('sha256', $request->getContent(), $signingSecret);
            if (! hash_equals($computedSignature, $signature)) {
                Log::warning('[LemonSqueezy Webhook] Invalid signature received.');

                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $eventName = $request->input('meta.event_name');
        $data = $request->input('data');
        $attributes = $data['attributes'] ?? [];
        $customData = $request->input('meta.custom_data', []);

        Log::info("[LemonSqueezy Webhook] Received event: {$eventName}");

        switch ($eventName) {
            case 'order_created':
                $this->handleOrderCreated($attributes, $customData);
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
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $customData
     */
    protected function handleOrderCreated(array $attributes, array $customData): void
    {
        $orderId = (string) ($attributes['identifier'] ?? $attributes['first_order_item']['order_id'] ?? $attributes['order_number'] ?? '');
        $customerId = (string) ($attributes['customer_id'] ?? '');
        $email = strtolower(trim((string) ($attributes['user_email'] ?? $attributes['customer_email'] ?? '')));
        $name = (string) ($attributes['user_name'] ?? $attributes['customer_name'] ?? '');

        $variantId = (string) ($attributes['first_order_item']['variant_id'] ?? '');
        $productId = (string) ($attributes['first_order_item']['product_id'] ?? '');
        $productName = strtolower((string) ($attributes['first_order_item']['product_name'] ?? ''));

        // Determine tier
        $tier = 'pro';
        if (! empty($customData['tier'])) {
            $tier = strtolower(trim($customData['tier']));
        } elseif (str_contains($productName, 'starter') || in_array($variantId, ['2076019', '2076021', '2076023'], true) || $productId === '1328315') {
            $tier = 'starter';
        } elseif (str_contains($productName, 'agency') || in_array($variantId, ['2076031', '2076032', '2076033'], true) || $productId === '1328321') {
            $tier = 'agency';
        }

        $limits = [
            'starter' => 1,
            'pro' => 5,
            'agency' => 999999,
        ];
        $limit = $limits[$tier] ?? 5;

        // Generate default license key if not provided directly in order payload
        $licenseKey = License::generateKey();

        License::updateOrCreate(
            ['order_id' => $orderId],
            [
                'customer_id' => $customerId ?: null,
                'customer_email' => $email,
                'customer_name' => $name,
                'product_id' => $productId ?: null,
                'variant_id' => $variantId ?: null,
                'tier' => $tier,
                'license_key' => $licenseKey,
                'status' => 'active',
                'activation_limit' => $limit,
                'expires_at' => now()->addYear(),
            ]
        );

        Log::info("[LemonSqueezy Webhook] Created {$tier} license for order {$orderId} ({$email}) with limit {$limit}");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function handleOrderRefunded(array $attributes): void
    {
        $orderId = (string) ($attributes['identifier'] ?? $attributes['order_id'] ?? $attributes['order_number'] ?? '');

        if ($orderId) {
            License::where('order_id', $orderId)->update([
                'status' => 'refunded',
                'instances' => [],
                'activation_count' => 0,
            ]);

            Log::info("[LemonSqueezy Webhook] Order {$orderId} was refunded. License deactivated and instances cleared.");
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function handleLicenseKeyCreated(array $attributes): void
    {
        $key = (string) ($attributes['key'] ?? '');
        $orderId = (string) ($attributes['order_id'] ?? '');
        $activationLimit = (int) ($attributes['activation_limit'] ?? 1);

        if ($key && $orderId) {
            License::where('order_id', $orderId)->update([
                'license_key' => $key,
                'activation_limit' => $activationLimit,
            ]);
            Log::info("[LemonSqueezy Webhook] Updated native license key for order {$orderId}");
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function handleSubscriptionEvent(array $attributes): void
    {
        $status = (string) ($attributes['status'] ?? 'active');
        $orderId = (string) ($attributes['order_id'] ?? '');

        if ($orderId) {
            License::where('order_id', $orderId)->update([
                'status' => $status === 'active' ? 'active' : 'inactive',
            ]);
            Log::info("[LemonSqueezy Webhook] Updated license subscription status to {$status} for order {$orderId}");
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function handlePaymentFailed(array $attributes): void
    {
        $orderId = (string) ($attributes['order_id'] ?? '');
        if ($orderId) {
            Log::warning("[LemonSqueezy Webhook] Subscription payment failed for order {$orderId}.");
        }
    }
}
