<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestEventCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_event_command_runs_successfully(): void
    {
        config()->set('google-ads-conversions.meta.pixel_id', '123456');
        config()->set('google-ads-conversions.meta.access_token', 'META_TEST_TOKEN');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
        ]);

        $this->artisan('omnisignal:test-event', [
            '--channel' => 'meta',
            '--value' => '99.50',
            '--order-id' => 'CLI-TEST-100',
        ])
            ->expectsOutputToContain('OmniSignal Test Event Dispatcher')
            ->expectsOutputToContain('Purchase')
            ->expectsOutputToContain('SUCCESS')
            ->assertSuccessful();
    }
}
