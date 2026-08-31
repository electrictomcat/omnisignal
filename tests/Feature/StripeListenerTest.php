<?php

namespace Tests\Feature;

use App\OmniSignal\Facades\GoogleAdsConversions;
use App\OmniSignal\Listeners\OmniSignalStripeListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_listener_records_checkout_session_completed(): void
    {
        GoogleAdsConversions::fake();

        $listener = new OmniSignalStripeListener();

        $stripeEvent = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_12345',
                    'amount_total' => 14900,
                    'currency' => 'usd',
                    'customer_details' => [
                        'email' => 'buyer@startup.com',
                        'phone' => '+15551234567',
                    ],
                ],
            ],
        ];

        $listener->handle($stripeEvent);

        GoogleAdsConversions::assertRecorded('Purchase', 149.00);
    }
}
