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
            case 'license_key_created':
                $this->handleLicenseKeyCreated($attributes);
                break;
            case 'subscription_created':
            case 'subscription_updated':
                $this->handleSubscriptionEvent($attributes);
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
        $orderId = (string) ($attributes['identifier'] ?? $attributes['first_order_item']['order_id'] ?? '');
        $email = strtolower(trim((string) ($attributes['user_email'] ?? $attributes['customer_email'] ?? '')));
        $name = (string) ($attributes['user_name'] ?? $attributes['customer_name'] ?? '');
        $tier = (string) ($customData['tier'] ?? 'pro');

        $limits = [
            'starter' => 1,
            'pro' => 5,
            'agency' => 999999,
        ];
        $limit = $limits[$tier] ?? 5;

        // Generate or retrieve license
        $licenseKey = License::generateKey();

        License::updateOrCreate(
            ['order_id' => $orderId],
            [
                'customer_email' => $email,
                'customer_name' => $name,
                'tier' => $tier,
                'license_key' => $licenseKey,
                'status' => 'active',
                'activation_limit' => $limit,
                'expires_at' => now()->addYear(),
            ]
        );

        Log::info("[LemonSqueezy Webhook] Created license for order {$orderId} ({$email})");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function handleLicenseKeyCreated(array $attributes): void
    {
        $key = (string) ($attributes['key'] ?? '');
        $orderId = (string) ($attributes['order_id'] ?? '');
        $activationLimit = (int) ($attributes['activation_limit'] ?? 1);

        if ($key) {
            License::where('order_id', $orderId)->update([
                'license_key' => $key,
                'activation_limit' => $activationLimit,
            ]);
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
        }
    }
}
