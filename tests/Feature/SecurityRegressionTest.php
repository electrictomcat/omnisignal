<?php

namespace Tests\Feature;

use App\Mail\PortalAccessLink;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Regressions for the exploitable findings in the readiness audit.
 *
 * Every test here failed against the code as it stood.
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function orderPayload(string $orderId = 'ORD-1', string $tier = 'agency'): array
    {
        return [
            'meta' => ['event_name' => 'order_created', 'custom_data' => ['tier' => $tier]],
            'data' => ['attributes' => [
                'identifier' => $orderId,
                'user_email' => 'buyer@example.com',
                'user_name' => 'Buyer',
            ]],
        ];
    }

    // ------------------------------------------------------------- OS-01

    public function test_webhook_without_a_signature_header_is_rejected(): void
    {
        $this->postJson('/webhooks/lemonsqueezy', $this->orderPayload())
            ->assertStatus(401);

        $this->assertSame(0, License::count());
    }

    public function test_webhook_with_a_wrong_signature_is_rejected(): void
    {
        $this->withHeaders(['X-Signature' => str_repeat('a', 64)])
            ->postJson('/webhooks/lemonsqueezy', $this->orderPayload())
            ->assertStatus(401);

        $this->assertSame(0, License::count());
    }

    public function test_webhook_is_rejected_when_no_signing_secret_is_configured(): void
    {
        config()->set('services.lemonsqueezy.signing_secret', null);

        $payload = $this->orderPayload();

        $this->withHeaders(['X-Signature' => 'anything'])
            ->postJson('/webhooks/lemonsqueezy', $payload)
            ->assertStatus(401);

        $this->assertSame(0, License::count());
    }

    public function test_a_correctly_signed_webhook_still_works(): void
    {
        $payload = $this->orderPayload();

        $this->postSignedWebhook($payload)->assertOk();

        $license = License::sole();
        $this->assertSame('agency', $license->tier);
        $this->assertSame('active', $license->status);
    }

    // ------------------------------------------------------------- OS-05

    public function test_a_replayed_webhook_does_not_rotate_the_licence_key(): void
    {
        $payload = $this->orderPayload('ORD-REPLAY');

        $this->postSignedWebhook($payload);
        $first = License::where('order_id', 'ORD-REPLAY')->value('license_key');

        // Lemon Squeezy retries webhooks and lets you resend them by hand.
        $this->postSignedWebhook($payload);
        $second = License::where('order_id', 'ORD-REPLAY')->value('license_key');

        $this->assertSame($first, $second);
        $this->assertSame(1, License::count());
    }

    public function test_an_order_with_no_identifier_is_ignored(): void
    {
        $payload = [
            'meta' => ['event_name' => 'order_created'],
            'data' => ['attributes' => ['user_email' => 'nobody@example.com']],
        ];

        $this->postSignedWebhook($payload)->assertOk();

        // Blank order IDs previously collapsed every such order onto one row,
        // overwriting the previous customer's licence.
        $this->assertSame(0, License::count());
    }

    // ------------------------------------------------------------- OS-02

    public function test_the_portal_never_reveals_a_licence_key_without_the_emailed_link(): void
    {
        Mail::fake();

        License::create([
            'order_id' => 'REAL-1', 'customer_email' => 'victim@example.com', 'customer_name' => 'Victim Co',
            'tier' => 'pro', 'license_key' => 'OMNI-AAAA-BBBB-CCCC-DDDD', 'status' => 'active',
            'activation_limit' => 5, 'activation_count' => 1, 'instances' => ['victim.com'],
        ]);

        // The old attack: guess the email, read the key off the page.
        $this->get('/portal?q=victim@example.com')
            ->assertOk()
            ->assertDontSee('OMNI-AAAA-BBBB-CCCC-DDDD');

        $this->post('/portal/lookup', ['email' => 'victim@example.com'])
            ->assertRedirect(route('portal'));

        $this->get('/portal')->assertDontSee('OMNI-AAAA-BBBB-CCCC-DDDD');

        Mail::assertSent(PortalAccessLink::class);
    }

    public function test_the_lookup_form_does_not_disclose_whether_an_address_is_a_customer(): void
    {
        Mail::fake();

        License::create([
            'order_id' => 'REAL-2', 'customer_email' => 'real@example.com',
            'tier' => 'pro', 'license_key' => 'OMNI-1111-2222-3333-4444', 'status' => 'active',
            'activation_limit' => 5,
        ]);

        $known = $this->post('/portal/lookup', ['email' => 'real@example.com'])->getSession()->get('status');
        $unknown = $this->post('/portal/lookup', ['email' => 'stranger@example.com'])->getSession()->get('status');

        $this->assertSame(
            str_replace('real@example.com', 'X', (string) $known),
            str_replace('stranger@example.com', 'X', (string) $unknown),
        );

        Mail::assertSentCount(1);
    }

    public function test_a_signed_link_unlocks_the_licence_for_that_address_only(): void
    {
        License::create([
            'order_id' => 'REAL-3', 'customer_email' => 'owner@example.com',
            'tier' => 'pro', 'license_key' => 'OMNI-OWNR-KEY0-0000-0001', 'status' => 'active',
            'activation_limit' => 5, 'instances' => ['owner.com'],
        ]);
        License::create([
            'order_id' => 'REAL-4', 'customer_email' => 'other@example.com',
            'tier' => 'pro', 'license_key' => 'OMNI-OTHR-KEY0-0000-0002', 'status' => 'active',
            'activation_limit' => 5,
        ]);

        $url = URL::temporarySignedRoute('portal.show', now()->addMinutes(30), ['email' => 'owner@example.com']);

        $this->get($url)
            ->assertOk()
            ->assertSee('OMNI-OWNR-KEY0-0000-0001')
            ->assertDontSee('OMNI-OTHR-KEY0-0000-0002');
    }

    public function test_a_tampered_or_expired_link_is_refused(): void
    {
        License::create([
            'order_id' => 'REAL-5', 'customer_email' => 'owner@example.com',
            'tier' => 'pro', 'license_key' => 'OMNI-TAMP-0000-0000-0001', 'status' => 'active',
            'activation_limit' => 5,
        ]);

        // Swapping the email on a validly signed link must break the signature.
        $url = URL::temporarySignedRoute('portal.show', now()->addMinutes(30), ['email' => 'someone@example.com']);
        $this->get(str_replace('someone%40example.com', 'owner%40example.com', $url))->assertForbidden();

        $expired = URL::temporarySignedRoute('portal.show', now()->subMinute(), ['email' => 'owner@example.com']);
        $this->get($expired)->assertForbidden();
    }

    // ------------------------------------------------------------- OS-03

    public function test_a_stranger_cannot_deactivate_someone_elses_domain(): void
    {
        $license = License::create([
            'order_id' => 'REAL-6', 'customer_email' => 'victim2@example.com',
            'tier' => 'pro', 'license_key' => 'OMNI-1111-2222-3333-9999', 'status' => 'active',
            'activation_limit' => 5, 'activation_count' => 1, 'instances' => ['victimshop.com'],
        ]);

        $this->post('/portal/deactivate', ['license_id' => $license->id, 'domain' => 'victimshop.com'])
            ->assertRedirect(route('portal'));

        $this->assertContains('victimshop.com', $license->fresh()->instances);
    }

    public function test_a_session_cannot_deactivate_a_licence_it_does_not_own(): void
    {
        $mine = License::create([
            'order_id' => 'MINE', 'customer_email' => 'me@example.com',
            'tier' => 'pro', 'license_key' => 'OMNI-MINE-0000-0000-0001', 'status' => 'active',
            'activation_limit' => 5, 'activation_count' => 1, 'instances' => ['mine.com'],
        ]);
        $theirs = License::create([
            'order_id' => 'THEIRS', 'customer_email' => 'them@example.com',
            'tier' => 'pro', 'license_key' => 'OMNI-THRS-0000-0000-0002', 'status' => 'active',
            'activation_limit' => 5, 'activation_count' => 1, 'instances' => ['theirs.com'],
        ]);

        $url = URL::temporarySignedRoute('portal.show', now()->addMinutes(30), ['email' => 'me@example.com']);
        $this->get($url)->assertOk();

        // Guessing the neighbouring integer ID must not work.
        $this->post('/portal/deactivate', ['license_id' => $theirs->id, 'domain' => 'theirs.com']);
        $this->assertContains('theirs.com', $theirs->fresh()->instances);

        // ...but the owner can still manage their own.
        $this->post('/portal/deactivate', ['license_id' => $mine->id, 'domain' => 'mine.com']);
        $this->assertNotContains('mine.com', $mine->fresh()->instances ?? []);
    }

    // ------------------------------------------------------------- OS-04

    public function test_licence_validation_is_rate_limited(): void
    {
        $status = 200;

        for ($i = 0; $i < 60 && $status !== 429; $i++) {
            $status = $this->postJson('/api/v1/licenses/validate', ['license_key' => 'OMNI-GUESS-'.$i])
                ->getStatusCode();
        }

        $this->assertSame(429, $status, 'key guessing should be throttled');
    }

    public function test_licence_activation_is_rate_limited(): void
    {
        $status = 200;

        for ($i = 0; $i < 40 && $status !== 429; $i++) {
            $status = $this->postJson('/api/v1/licenses/activate', [
                'license_key' => 'OMNI-GUESS-'.$i, 'domain' => 'example.com',
            ])->getStatusCode();
        }

        $this->assertSame(429, $status);
    }

    // ------------------------------------------------------------- OS-06

    public function test_the_analytics_dashboard_is_not_publicly_reachable(): void
    {
        $this->get('/dashboard')->assertNotFound();
        $this->get('/ad-conversions')->assertNotFound();
    }

    public function test_robots_disallows_the_account_and_internal_surfaces(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        foreach (['/portal', '/dashboard', '/ad-conversions', '/api/'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}", $robots);
        }
    }

    public function test_checkout_sends_buyers_to_a_receipt_page_not_the_dashboard(): void
    {
        $this->get('/thanks')->assertOk()->assertSee('Thanks');
    }

    // ------------------------------------------------------------- OS-12

    public function test_activation_respects_the_domain_limit(): void
    {
        $license = License::create([
            'order_id' => 'LIMIT', 'customer_email' => 'limit@example.com',
            'tier' => 'starter', 'license_key' => 'OMNI-LMT0-0000-0000-0001', 'status' => 'active',
            'activation_limit' => 1,
        ]);

        $this->assertTrue($license->activate('first.com'));
        $this->assertFalse($license->activate('second.com'));
        $this->assertSame(1, $license->fresh()->activation_count);
    }

    public function test_the_same_site_written_differently_does_not_consume_two_slots(): void
    {
        $license = License::create([
            'order_id' => 'NORM', 'customer_email' => 'norm@example.com',
            'tier' => 'starter', 'license_key' => 'OMNI-NORM-0000-0000-0001', 'status' => 'active',
            'activation_limit' => 1,
        ]);

        $this->assertTrue($license->activate('https://www.Example.com/shop'));
        $this->assertTrue($license->activate('example.com'));
        $this->assertSame(['example.com'], $license->fresh()->instances);
    }
}
