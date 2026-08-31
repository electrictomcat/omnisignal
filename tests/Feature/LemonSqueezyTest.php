<?php

namespace Tests\Feature;

use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LemonSqueezyTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_redirects_to_lemon_squeezy_store(): void
    {
        $response = $this->get('/checkout/pro');

        $response->assertRedirect();
        $this->assertStringContainsString('lemonsqueezy.com', $response->headers->get('Location'));
    }

    public function test_webhook_creates_license_on_order_created(): void
    {
        $payload = [
            'meta' => [
                'event_name' => 'order_created',
                'custom_data' => [
                    'tier' => 'pro',
                ],
            ],
            'data' => [
                'attributes' => [
                    'identifier' => 'ORD-12345',
                    'user_email' => 'buyer@example.com',
                    'user_name' => 'John Doe',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/lemonsqueezy', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('licenses', [
            'order_id' => 'ORD-12345',
            'customer_email' => 'buyer@example.com',
            'tier' => 'pro',
            'activation_limit' => 5,
            'status' => 'active',
        ]);
    }

    public function test_license_api_validation_and_activation(): void
    {
        $license = License::create([
            'order_id' => 'ORD-999',
            'customer_email' => 'dev@example.com',
            'tier' => 'starter',
            'license_key' => 'OMNI-TEST-1234-5678',
            'status' => 'active',
            'activation_limit' => 1,
            'activation_count' => 0,
            'instances' => [],
        ]);

        // Validate Key
        $validateRes = $this->postJson('/api/v1/licenses/validate', [
            'license_key' => 'OMNI-TEST-1234-5678',
        ]);
        $validateRes->assertOk();
        $validateRes->assertJson(['valid' => true, 'tier' => 'starter']);

        // Activate 1st domain (Allowed)
        $act1 = $this->postJson('/api/v1/licenses/activate', [
            'license_key' => 'OMNI-TEST-1234-5678',
            'domain' => 'client1.com',
        ]);
        $act1->assertOk();
        $act1->assertJson(['activated' => true, 'activation_count' => 1]);

        // Activate 2nd domain (Rejected due to 1 domain limit on starter)
        $act2 = $this->postJson('/api/v1/licenses/activate', [
            'license_key' => 'OMNI-TEST-1234-5678',
            'domain' => 'client2.com',
        ]);
        $act2->assertStatus(422);
        $act2->assertJson(['activated' => false]);

        // Deactivate 1st domain
        $deact = $this->postJson('/api/v1/licenses/deactivate', [
            'license_key' => 'OMNI-TEST-1234-5678',
            'domain' => 'client1.com',
        ]);
        $deact->assertOk();
        $deact->assertJson(['deactivated' => true, 'activation_count' => 0]);
    }
}
