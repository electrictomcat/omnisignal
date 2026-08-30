<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\OmniSignal\ConversionManager;
use App\OmniSignal\DTO\ConversionPayload;
use App\OmniSignal\Drivers\MetaCapiDriver;
use App\OmniSignal\Facades\GoogleAdsConversions;
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

    public function test_dashboard_renders_successfully(): void
    {
        Lead::create([
            'gclid' => 'gclid_test_123',
            'conversions' => [
                [
                    'event' => 'Demo Booked',
                    'value' => 250.00,
                    'currency' => 'USD',
                    'status' => 'uploaded',
                    'timestamp' => now()->timestamp,
                ],
            ],
        ]);

        $response = $this->get('/ad-conversions');

        $response->assertOk();
        $response->assertSee('OmniSignal');
        $response->assertSee('Demo Booked');
        $response->assertSee('250.00');

        $dashResponse = $this->get('/dashboard');
        $dashResponse->assertOk();
    }

    public function test_meta_capi_driver_payload_formatting(): void
    {
        config()->set('omnisignal.meta.pixel_id', '1234567890');
        config()->set('omnisignal.meta.access_token', 'META_TEST_TOKEN');

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

    public function test_install_command_runs_successfully(): void
    {
        $this->artisan('ad-conversions:install')
            ->expectsQuestion('Which advertising channels do you want to configure?', ['google'])
            ->expectsOutputToContain('OmniSignal Setup Wizard')
            ->expectsOutputToContain('OmniSignal installation and setup completed!')
            ->assertSuccessful();
    }
}
