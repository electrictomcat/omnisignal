<?php

namespace Tests\Feature;

use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LemonSqueezyWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_order_webhook_creates_1_domain_license(): void
    {
        $payload = [
            'meta' => [
                'event_name' => 'order_created',
            ],
            'data' => [
                'attributes' => [
                    'identifier' => 'ORD-STARTER-001',
                    'user_email' => 'starter@example.com',
                    'user_name' => 'Starter User',
                    'first_order_item' => [
                        'product_id' => '1328315',
                        'product_name' => 'Starter',
                        'variant_id' => '2076021',
                        'order_id' => 'ORD-STARTER-001',
                    ],
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);
        $response->assertOk();

        $this->assertDatabaseHas('licenses', [
            'order_id' => 'ORD-STARTER-001',
            'customer_email' => 'starter@example.com',
            'tier' => 'starter',
            'activation_limit' => 1,
            'status' => 'active',
        ]);
    }

    public function test_pro_order_webhook_creates_5_domain_license(): void
    {
        $payload = [
            'meta' => [
                'event_name' => 'order_created',
            ],
            'data' => [
                'attributes' => [
                    'identifier' => 'ORD-PRO-002',
                    'user_email' => 'pro@example.com',
                    'user_name' => 'Pro Dev',
                    'first_order_item' => [
                        'product_id' => '1328318',
                        'product_name' => 'Pro',
                        'variant_id' => '2076026',
                        'order_id' => 'ORD-PRO-002',
                    ],
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);
        $response->assertOk();

        $this->assertDatabaseHas('licenses', [
            'order_id' => 'ORD-PRO-002',
            'customer_email' => 'pro@example.com',
            'tier' => 'pro',
            'activation_limit' => 5,
            'status' => 'active',
        ]);
    }

    public function test_agency_order_webhook_creates_unlimited_license(): void
    {
        $payload = [
            'meta' => [
                'event_name' => 'order_created',
            ],
            'data' => [
                'attributes' => [
                    'identifier' => 'ORD-AGENCY-003',
                    'user_email' => 'agency@example.com',
                    'user_name' => 'Agency Leader',
                    'first_order_item' => [
                        'product_id' => '1328321',
                        'product_name' => 'Agency Zen',
                        'variant_id' => '2076033',
                        'order_id' => 'ORD-AGENCY-003',
                    ],
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);
        $response->assertOk();

        $license = License::where('order_id', 'ORD-AGENCY-003')->first();
        $this->assertNotNull($license);
        $this->assertEquals('agency', $license->tier);
        $this->assertGreaterThan(1000, $license->activation_limit);
    }

    public function test_license_key_created_updates_key(): void
    {
        License::create([
            'order_id' => 'ORD-NATIVE-004',
            'customer_email' => 'native@example.com',
            'tier' => 'pro',
            'license_key' => 'OMNI-OLD-KEY',
            'status' => 'active',
            'activation_limit' => 5,
        ]);

        $payload = [
            'meta' => [
                'event_name' => 'license_key_created',
            ],
            'data' => [
                'attributes' => [
                    'order_id' => 'ORD-NATIVE-004',
                    'key' => 'LEMON-NATIVE-KEY-12345',
                    'activation_limit' => 5,
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);
        $response->assertOk();

        $this->assertDatabaseHas('licenses', [
            'order_id' => 'ORD-NATIVE-004',
            'license_key' => 'LEMON-NATIVE-KEY-12345',
        ]);
    }

    public function test_subscription_cancelled_marks_license_inactive(): void
    {
        License::create([
            'order_id' => 'ORD-SUB-005',
            'customer_email' => 'sub@example.com',
            'tier' => 'pro',
            'license_key' => 'OMNI-SUB-KEY',
            'status' => 'active',
            'activation_limit' => 5,
        ]);

        $payload = [
            'meta' => [
                'event_name' => 'subscription_cancelled',
            ],
            'data' => [
                'attributes' => [
                    'order_id' => 'ORD-SUB-005',
                    'status' => 'cancelled',
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);
        $response->assertOk();

        $this->assertDatabaseHas('licenses', [
            'order_id' => 'ORD-SUB-005',
            'status' => 'inactive',
        ]);
    }
}
