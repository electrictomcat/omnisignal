<?php

namespace App\OmniSignal\Listeners;

use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;

class OmniSignalStripeListener
{
    /**
     * Handle incoming Stripe webhook payload and record conversion.
     *
     * @param  array<string, mixed>  $stripeEvent
     */
    public function handle(array $stripeEvent): void
    {
        $type = $stripeEvent['type'] ?? '';
        $object = $stripeEvent['data']['object'] ?? [];

        switch ($type) {
            case 'checkout.session.completed':
                $amount = (float) (($object['amount_total'] ?? 0) / 100);
                $currency = strtoupper((string) ($object['currency'] ?? 'USD'));
                $email = $object['customer_details']['email'] ?? $object['customer_email'] ?? null;
                $phone = $object['customer_details']['phone'] ?? null;
                $orderId = $object['id'] ?? null;

                GoogleAdsConversions::record(
                    eventName: 'Purchase',
                    value: $amount,
                    currency: $currency,
                    orderId: $orderId,
                    userIdentifiers: array_filter(['email' => $email, 'phone' => $phone])
                );
                break;

            case 'invoice.payment_succeeded':
                $amount = (float) (($object['amount_paid'] ?? 0) / 100);
                $currency = strtoupper((string) ($object['currency'] ?? 'USD'));
                $email = $object['customer_email'] ?? null;
                $orderId = $object['id'] ?? null;

                GoogleAdsConversions::record(
                    eventName: 'Subscription Payment',
                    value: $amount,
                    currency: $currency,
                    orderId: $orderId,
                    userIdentifiers: array_filter(['email' => $email])
                );
                break;
        }
    }
}
