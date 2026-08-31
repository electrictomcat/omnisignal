<?php

namespace Tests\Feature;

use App\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Drivers\MetaCapiDriver;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmniSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('OmniSignal');
        $response->assertSee('Attribution Nirvana');
        $response->assertSee('How much lost ad revenue will you recover?');
        $response->assertSee('omnisignal.dev');
    }

    public function test_docs_knowledge_base_renders_successfully(): void
    {
        $response = $this->get('/docs');

        $response->assertOk();
        $response->assertSee('Knowledge Base');
        $response->assertSee('Quickstart');
        $response->assertSee('Google Ads Offline Conversions');
        $response->assertSee('Meta');

        $kbRedirect = $this->get('/kb');
        $kbRedirect->assertRedirect('/docs');
    }

    public function test_dashboard_is_not_publicly_reachable(): void
    {
        // It shows lead counts, click identifiers and attributed revenue.
        $this->get('/dashboard')->assertNotFound();
        $this->get('/ad-conversions')->assertNotFound();
    }

    public function test_meta_capi_driver_payload_formatting(): void
    {
        config()->set('google-ads-conversions.meta.pixel_id', '1234567890');
        config()->set('google-ads-conversions.meta.access_token', 'META_TEST_TOKEN');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
        ]);

        $driver = new MetaCapiDriver;
        $this->assertTrue($driver->isConfigured());

        $payload = new ConversionPayload(
            eventName: 'Purchase',
            value: 120.0,
            currency: 'USD',
            orderId: 'ORD-999',
            fbclid: 'test_fbclid_123',
            userData: [
                'email' => 'Customer@Example.com',
                'first_name' => 'Alice',
            ],
        );

        $result = $driver->upload([$payload]);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['count']);

        Http::assertSent(function ($request) {
            $data = $request['data'][0];

            return $data['event_name'] === 'Purchase'
                && $data['user_data']['em'][0] === hash('sha256', 'customer@example.com');
        });
    }

    public function test_install_command_reports_channel_readiness(): void
    {
        // The wizard is no longer interactive and no longer claims to have
        // "configured" channels it never wrote any configuration for.
        $this->artisan('ad-conversions:install')
            ->expectsOutputToContain('Channel status')
            ->expectsOutputToContain('not configured')
            ->assertSuccessful();
    }
}
