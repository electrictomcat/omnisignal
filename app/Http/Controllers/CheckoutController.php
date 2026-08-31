<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    /** @var array<int, string> */
    protected const VALID_TIERS = ['starter', 'pro', 'agency'];

    public function __invoke(Request $request, string $tier = 'pro'): RedirectResponse
    {
        $tier = strtolower(trim($tier));

        if (! in_array($tier, self::VALID_TIERS, true)) {
            $tier = 'pro';
        }

        // Monthly by default; ?billing=once switches to the one-time variant.
        $variantKey = $request->query('billing') === 'once' ? "{$tier}_onetime" : $tier;

        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id');
        $variantId = config("services.lemonsqueezy.variants.{$variantKey}");

        if ($apiKey && $storeId && $variantId) {
            try {
                $response = Http::withToken($apiKey)
                    ->timeout(10)
                    ->post('https://api.lemonsqueezy.com/v1/checkouts', [
                        'data' => [
                            'type' => 'checkouts',
                            'attributes' => [
                                'checkout_data' => [
                                    'custom' => ['tier' => $tier],
                                ],
                                'product_options' => [
                                    // A real receipt page, not the internal
                                    // analytics dashboard.
                                    'redirect_url' => route('checkout.thanks'),
                                ],
                            ],
                            'relationships' => [
                                'store' => [
                                    'data' => ['type' => 'stores', 'id' => (string) $storeId],
                                ],
                                'variant' => [
                                    'data' => ['type' => 'variants', 'id' => (string) $variantId],
                                ],
                            ],
                        ],
                    ]);

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
        } else {
            Log::warning("[LemonSqueezy] Checkout not configured for tier '{$variantKey}'; falling back to the store front.");
        }

        return redirect()->away(config('services.lemonsqueezy.store_url'));
    }
}
