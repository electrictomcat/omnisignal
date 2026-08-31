<?php

namespace Tests\Feature;

use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PortalAndRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_renders_successfully(): void
    {
        $response = $this->get('/portal');
        $response->assertOk();
        $response->assertSee('Manage Your OmniSignal License');
    }

    public function test_portal_lookup_displays_license_and_domain_instances(): void
    {
        $license = License::create([
            'order_id' => 'ORD-PORTAL-100',
            'customer_email' => 'founder@startup.com',
            'tier' => 'pro',
            'license_key' => 'OMNI-PORTAL-TEST-999',
            'status' => 'active',
            'activation_limit' => 5,
            'activation_count' => 1,
            'instances' => ['startup.com'],
            'expires_at' => now()->addYear(),
        ]);

        // Access is proven by control of the purchase email, so the key is
        // only rendered behind the signed link we mail out.
        $this->get('/portal?q=founder@startup.com')
            ->assertOk()
            ->assertDontSee('OMNI-PORTAL-TEST-999');

        $response = $this->get(URL::temporarySignedRoute(
            'portal.show',
            now()->addMinutes(30),
            ['email' => 'founder@startup.com'],
        ));

        $response->assertOk();
        $response->assertSee('OMNI-PORTAL-TEST-999');
        $response->assertSee('startup.com');
        $response->assertSee('Active');
    }

    public function test_portal_deactivates_domain(): void
    {
        $license = License::create([
            'order_id' => 'ORD-PORTAL-101',
            'customer_email' => 'dev@agency.com',
            'tier' => 'pro',
            'license_key' => 'OMNI-DEACT-TEST-111',
            'status' => 'active',
            'activation_limit' => 5,
            'activation_count' => 2,
            'instances' => ['site1.com', 'site2.com'],
        ]);

        // Establish the portal session the emailed link creates.
        $this->get(URL::temporarySignedRoute(
            'portal.show',
            now()->addMinutes(30),
            ['email' => 'dev@agency.com'],
        ))->assertOk();

        $response = $this->post('/portal/deactivate', [
            'license_id' => $license->id,
            'domain' => 'site1.com',
        ]);

        $response->assertRedirect();
        $license->refresh();

        $this->assertEquals(1, $license->activation_count);
        $this->assertFalse($license->isActivatedFor('site1.com'));
        $this->assertTrue($license->isActivatedFor('site2.com'));
    }

    public function test_order_refunded_webhook_deactivates_license(): void
    {
        $license = License::create([
            'order_id' => 'ORD-REFUND-200',
            'customer_email' => 'refundme@example.com',
            'tier' => 'starter',
            'license_key' => 'OMNI-REFUND-KEY-222',
            'status' => 'active',
            'activation_limit' => 1,
            'activation_count' => 1,
            'instances' => ['oldshop.com'],
        ]);

        $payload = [
            'meta' => [
                'event_name' => 'order_refunded',
            ],
            'data' => [
                'attributes' => [
                    'identifier' => 'ORD-REFUND-200',
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);
        $response->assertOk();

        $license->refresh();
        $this->assertEquals('refunded', $license->status);
        $this->assertFalse($license->isActive());
        $this->assertEquals(0, $license->activation_count);
        $this->assertEmpty($license->instances);
    }

    public function test_legal_and_refund_pages_render_successfully(): void
    {
        $this->get('/refunds')->assertOk()->assertSee('14-Day Money-Back Guarantee');
        $this->get('/terms')->assertOk()->assertSee('Terms of Service');
        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy');
    }
}
