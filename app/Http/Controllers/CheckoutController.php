<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __invoke(Request $request, string $tier = 'pro'): RedirectResponse
    {
        $tier = strtolower(trim($tier));
        $validTiers = ['starter', 'pro', 'agency'];

        if (! in_array($tier, $validTiers, true)) {
            $tier = 'pro';
        }

        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id', '463287');
        $variantId = config("services.lemonsqueezy.variants.{$tier}");

        // If specific variant ID is configured, create dynamic checkout session via LemonSqueezy API
        if ($apiKey && $storeId && $variantId) {
            try {
                $payload = [
                    'data' => [
                        'type' => 'checkouts',
                        'attributes' => [
                            'checkout_data' => [
                                'custom' => [
                                    'tier' => $tier,
                                ],
                            ],
                            'product_options' => [
                                'redirect_url' => url('/dashboard?checkout=success'),
                            ],
                        ],
                        'relationships' => [
                            'store' => [
                                'data' => [
                                    'type' => 'stores',
                                    'id' => (string) $storeId,
                                ],
                            ],
                            'variant' => [
                                'data' => [
                                    'type' => 'variants',
                                    'id' => (string) $variantId,
                                ],
                            ],
                        ],
                    ],
                ];

                $response = Http::withToken($apiKey)
                    ->timeout(10)
                    ->post('https://api.lemonsqueezy.com/v1/checkouts', $payload);

                if ($response->successful()) {
                    $checkoutUrl = $response->json('data.attributes.url');
                    if ($checkoutUrl) {
                        return redirect()->away($checkoutUrl);
                    }
                }

                Log::warning('[LemonSqueezy] Failed to create checkout session: '.$response->body());
            } catch (\Throwable $e) {
                Log::error('[LemonSqueezy] Checkout error: '.$e->getMessage());
            }
        }

        // Direct fallback to LemonSqueezy store URL
        return redirect()->away("https://omnisignal.lemonsqueezy.com");
    }
}
