<?php

namespace Tests\Feature;

use App\Models\User;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DashboardTestCase;

class DashboardAccessTest extends DashboardTestCase
{
    use RefreshDatabase;

    public function test_an_anonymous_visitor_is_turned_away_even_when_the_dashboard_is_enabled(): void
    {
        // HTTP Basic challenge rather than a redirect: this app ships no
        // login page, so `auth` would 500 on a missing `login` route.
        $this->get('/ad-conversions')->assertUnauthorized();
    }

    public function test_an_authenticated_operator_sees_the_dashboard(): void
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

        $response = $this->actingAs(User::factory()->create())->get('/ad-conversions');

        $response->assertOk();
        $response->assertSee('OmniSignal');
        $response->assertSee('Demo Booked');
        $response->assertSee('250.00');
    }
}
