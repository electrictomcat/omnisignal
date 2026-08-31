<?php

namespace Tests\Feature;

use App\Jobs\UploadHostedConversion;
use App\Models\ChannelConnection;
use App\Models\License;
use App\Support\IngestToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use OmniSignal\Http\HttpClient;
use Tests\TestCase;

/**
 * The hosted connector: Google Ads uploads made on a customer's behalf.
 *
 * The security boundary is the interesting part. A site holds only a token
 * derived from its own domain, so a compromised install must not be able to
 * reach the licence key, another domain, or another customer.
 */
class HostedConnectorTest extends TestCase
{
    use RefreshDatabase;

    private function license(array $overrides = []): License
    {
        return License::create(array_merge([
            'order_id' => 'ORD-'.uniqid(),
            'customer_email' => 'owner@example.com',
            'tier' => 'pro',
            'license_key' => 'OMNI-'.strtoupper(uniqid()),
            'status' => 'active',
            'activation_limit' => 5,
            'activation_count' => 1,
            'instances' => ['shop.example'],
            'expires_at' => now()->addYear(),
        ], $overrides));
    }

    private function connect(License $license): ChannelConnection
    {
        return ChannelConnection::create([
            'license_id' => $license->id,
            'channel' => 'google',
            'credentials' => [
                'refresh_token' => 'refresh-token-value',
                'customer_id' => '1234567890',
                'conversion_action' => 'customers/1234567890/conversionActions/555',
            ],
            'account_id' => '1234567890',
            'status' => 'connected',
        ]);
    }

    /**
     * An SDK HTTP client that records what it was asked to send.
     *
     * @param  array<int, array<string, mixed>>  $responses
     */
    private function recordingClient(array $responses): HttpClient
    {
        return new class($responses) extends HttpClient
        {
            /** @var array<int, array<string, mixed>> */
            public array $requests = [];

            /** @param array<int, array<string, mixed>> $responses */
            public function __construct(private array $responses)
            {
                parent::__construct();
            }

            public function postJson(string $url, array $body, array $headers = []): array
            {
                $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => $body];

                return $this->next();
            }

            public function postForm(string $url, array $fields): array
            {
                $this->requests[] = ['url' => $url, 'headers' => [], 'body' => $fields];

                return $this->next();
            }

            public function get(string $url, array $headers = []): array
            {
                $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => null];

                return $this->next();
            }

            /** @return array<string, mixed> */
            private function next(): array
            {
                return array_shift($this->responses)
                    ?? ['ok' => true, 'status' => 200, 'body' => [], 'error' => null];
            }
        };
    }

    /** @return array<string, mixed> */
    private function payload(string $eventId = 'ORDER_1'): array
    {
        return [
            'domain' => 'shop.example',
            'conversions' => [[
                'event_name' => 'Purchase',
                'event_id' => $eventId,
                'value' => 49.99,
                'currency' => 'GBP',
                'gclid' => 'GC-1',
            ]],
        ];
    }

    // ----------------------------------------------------------- the token

    public function test_a_token_is_bound_to_one_domain_on_one_licence(): void
    {
        $a = $this->license(['instances' => ['a.example']]);
        $b = $this->license(['customer_email' => 'other@example.com', 'instances' => ['b.example']]);

        $tokenForA = IngestToken::for($a, 'a.example');

        $this->assertSame($a->id, IngestToken::resolve('a.example', $tokenForA)?->id);

        // Same token, different domain, and the other licence's domain.
        $this->assertNull(IngestToken::resolve('b.example', $tokenForA));
        $this->assertNull(IngestToken::resolve('a.example', IngestToken::for($b, 'a.example')));
    }

    public function test_deactivating_the_domain_revokes_its_token(): void
    {
        $license = $this->license();
        $token = IngestToken::for($license, 'shop.example');

        $this->assertNotNull(IngestToken::resolve('shop.example', $token));

        $license->deactivate('shop.example');

        $this->assertNull(IngestToken::resolve('shop.example', $token));
    }

    public function test_an_inactive_licence_cannot_ingest(): void
    {
        $license = $this->license();
        $token = IngestToken::for($license, 'shop.example');

        $license->forceFill(['status' => 'refunded'])->save();

        $this->assertNull(IngestToken::resolve('shop.example', $token));
    }

    public function test_the_token_survives_the_domain_being_written_differently(): void
    {
        $license = $this->license();

        $this->assertNotNull(IngestToken::resolve(
            'https://WWW.Shop.example/checkout',
            IngestToken::for($license, 'shop.example'),
        ));
    }

    // ---------------------------------------------------------- the ingest

    public function test_it_refuses_an_unauthenticated_post(): void
    {
        Queue::fake();
        $this->connect($this->license());

        $this->postJson('/api/v1/conversions', $this->payload())->assertStatus(401);
        $this->withToken('nonsense')->postJson('/api/v1/conversions', $this->payload())->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_it_queues_a_conversion_for_a_connected_licence(): void
    {
        Queue::fake();

        $license = $this->license();
        $this->connect($license);

        $this->withToken(IngestToken::for($license, 'shop.example'))
            ->postJson('/api/v1/conversions', $this->payload())
            ->assertStatus(202)
            ->assertJsonPath('accepted', 1)
            ->assertJsonPath('channels', ['google']);

        Queue::assertPushed(UploadHostedConversion::class, function (UploadHostedConversion $job) use ($license) {
            return $job->licenseId === $license->id
                && $job->conversion['event_id'] === 'ORDER_1'
                && $job->channels === ['google'];
        });
    }

    public function test_a_retry_of_the_same_event_is_not_uploaded_twice(): void
    {
        Queue::fake();

        $license = $this->license();
        $this->connect($license);
        $token = IngestToken::for($license, 'shop.example');

        $this->withToken($token)->postJson('/api/v1/conversions', $this->payload())
            ->assertJsonPath('accepted', 1);

        // The site retried; the customer must not be charged a second
        // conversion for the same order.
        $this->withToken($token)->postJson('/api/v1/conversions', $this->payload())
            ->assertJsonPath('accepted', 0)
            ->assertJsonPath('results.0.status', 'duplicate');

        Queue::assertPushed(UploadHostedConversion::class, 1);
    }

    public function test_it_accepts_nothing_when_no_channel_is_connected(): void
    {
        Queue::fake();

        $license = $this->license();

        $this->withToken(IngestToken::for($license, 'shop.example'))
            ->postJson('/api/v1/conversions', $this->payload())
            ->assertOk()
            ->assertJsonPath('accepted', 0);

        Queue::assertNothingPushed();
    }

    public function test_a_half_finished_connection_is_not_treated_as_live(): void
    {
        Queue::fake();

        $license = $this->license();
        $this->connect($license)->forceFill(['status' => 'needs_reauth'])->save();

        $this->withToken(IngestToken::for($license, 'shop.example'))
            ->postJson('/api/v1/conversions', $this->payload())
            ->assertOk()
            ->assertJsonPath('accepted', 0);
    }

    public function test_it_rejects_an_oversized_batch(): void
    {
        $license = $this->license();
        $this->connect($license);

        $payload = ['domain' => 'shop.example', 'conversions' => []];
        for ($i = 0; $i < 51; $i++) {
            $payload['conversions'][] = ['event_name' => 'Purchase', 'event_id' => "E{$i}"];
        }

        $this->withToken(IngestToken::for($license, 'shop.example'))
            ->postJson('/api/v1/conversions', $payload)
            ->assertStatus(422);
    }

    public function test_a_site_can_ask_which_channels_are_hosted(): void
    {
        $license = $this->license();

        $this->withToken(IngestToken::for($license, 'shop.example'))
            ->postJson('/api/v1/conversions/channels', ['domain' => 'shop.example'])
            ->assertOk()
            ->assertJsonPath('channels', []);

        $this->connect($license);

        $this->withToken(IngestToken::for($license, 'shop.example'))
            ->postJson('/api/v1/conversions/channels', ['domain' => 'shop.example'])
            ->assertOk()
            ->assertJsonPath('channels', ['google']);
    }

    // --------------------------------------------------------- credentials

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $license = $this->license();
        $this->connect($license);

        $raw = (string) \DB::table('channel_connections')->value('credentials');

        $this->assertStringNotContainsString('refresh-token-value', $raw);
        $this->assertStringNotContainsString('conversionActions', $raw);
    }

    public function test_credentials_are_not_exposed_when_the_model_is_serialised(): void
    {
        $license = $this->license();
        $connection = $this->connect($license);

        $this->assertArrayNotHasKey('credentials', $connection->toArray());
    }

    public function test_activation_hands_the_site_a_token_and_never_the_licence_key(): void
    {
        $license = $this->license(['instances' => [], 'activation_count' => 0]);

        $response = $this->postJson('/api/v1/licenses/activate', [
            'license_key' => $license->license_key,
            'domain' => 'newsite.example',
        ])->assertOk();

        $token = $response->json('ingest_token');

        $this->assertNotEmpty($token);
        $this->assertNotSame($license->license_key, $token);
        $this->assertSame($license->id, IngestToken::resolve('newsite.example', $token)?->id);
    }

    // ------------------------------------------------------------ upload

    public function test_the_job_uploads_with_our_credentials_and_the_customers_account(): void
    {
        $license = $this->license();
        $connection = $this->connect($license);

        config()->set('services.google_ads', [
            'client_id' => 'our-client-id',
            'client_secret' => 'our-secret',
            'developer_token' => 'our-dev-token',
            'api_version' => 'v23',
        ]);

        $http = $this->recordingClient([
            ['ok' => true, 'status' => 200, 'body' => ['access_token' => 'ya29.token'], 'error' => null],
            ['ok' => true, 'status' => 200, 'body' => ['results' => [[]]], 'error' => null],
        ]);
        $this->app->instance(HttpClient::class, $http);

        (new UploadHostedConversion($license->id, [
            'event_name' => 'Purchase',
            'event_id' => 'ORDER_9',
            'value' => 10.0,
            'currency' => 'USD',
            'gclid' => 'GC-9',
        ], ['google']))->handle();

        $this->assertCount(2, $http->requests, 'expected a token exchange and an upload');

        // Our OAuth application exchanges the customer's refresh token.
        $exchange = $http->requests[0];
        $this->assertSame('our-client-id', $exchange['body']['client_id']);
        $this->assertSame('refresh-token-value', $exchange['body']['refresh_token']);

        // The upload carries our developer token, but targets their account
        // and their conversion action.
        $upload = $http->requests[1];
        $this->assertSame(
            'https://googleads.googleapis.com/v23/customers/1234567890:uploadClickConversions',
            $upload['url'],
        );
        $this->assertContains('developer-token: our-dev-token', $upload['headers']);

        $conversion = $upload['body']['conversions'][0];
        $this->assertSame('GC-9', $conversion['gclid']);
        $this->assertSame('customers/1234567890/conversionActions/555', $conversion['conversionAction']);
        $this->assertSame('ORDER_9', $conversion['orderId']);

        $this->assertNotNull($connection->fresh()->verified_at);
    }

    public function test_a_revoked_grant_flags_the_connection_instead_of_retrying_forever(): void
    {
        $license = $this->license();
        $connection = $this->connect($license);

        config()->set('services.google_ads', [
            'client_id' => 'our-client-id',
            'client_secret' => 'our-secret',
            'developer_token' => 'our-dev-token',
            'api_version' => 'v23',
        ]);

        // Google refuses the refresh token: the customer revoked our access.
        $http = $this->recordingClient([
            ['ok' => false, 'status' => 400, 'body' => [], 'error' => 'HTTP 400: invalid_grant'],
        ]);
        $this->app->instance(HttpClient::class, $http);

        // Must not throw: a customer revoking access is their problem to fix,
        // not something to retry three times an hour indefinitely.
        (new UploadHostedConversion($license->id, [
            'event_name' => 'Purchase', 'event_id' => 'ORDER_X', 'gclid' => 'GC-X',
        ], ['google']))->handle();

        $this->assertSame('needs_reauth', $connection->fresh()->status);
        $this->assertSame([], $license->fresh()->load('connections')->hostedChannels());
    }

    public function test_a_transient_upload_failure_is_retried(): void
    {
        $license = $this->license();
        $this->connect($license);

        config()->set('services.google_ads', [
            'client_id' => 'our-client-id',
            'client_secret' => 'our-secret',
            'developer_token' => 'our-dev-token',
            'api_version' => 'v23',
        ]);

        $http = $this->recordingClient([
            ['ok' => true, 'status' => 200, 'body' => ['access_token' => 'ya29.token'], 'error' => null],
            ['ok' => false, 'status' => 503, 'body' => [], 'error' => 'HTTP 503: backend unavailable'],
        ]);
        $this->app->instance(HttpClient::class, $http);

        $this->expectException(\RuntimeException::class);

        (new UploadHostedConversion($license->id, [
            'event_name' => 'Purchase', 'event_id' => 'ORDER_Y', 'gclid' => 'GC-Y',
        ], ['google']))->handle();
    }

    // -------------------------------------------------------- portal access

    public function test_connecting_requires_the_portal_session(): void
    {
        $license = $this->license();

        // No emailed link followed, so no session.
        $this->get(route('portal.connect.google', $license))
            ->assertRedirect(route('portal'));
    }

    public function test_a_customer_cannot_connect_someone_elses_licence(): void
    {
        $mine = $this->license(['customer_email' => 'me@example.com']);
        $theirs = $this->license(['customer_email' => 'them@example.com']);

        $this->get(URL::temporarySignedRoute('portal.show', now()->addMinutes(30), ['email' => 'me@example.com']))
            ->assertOk();

        $this->get(route('portal.connect.google', $theirs))
            ->assertRedirect(route('portal'))
            ->assertSessionHasErrors('portal');

        $this->assertDatabaseCount('channel_connections', 0);
    }

    public function test_a_customer_cannot_disconnect_someone_elses_connection(): void
    {
        $theirs = $this->license(['customer_email' => 'them@example.com']);
        $connection = $this->connect($theirs);

        $this->license(['customer_email' => 'me@example.com']);
        $this->get(URL::temporarySignedRoute('portal.show', now()->addMinutes(30), ['email' => 'me@example.com']))
            ->assertOk();

        $this->delete(route('portal.connect.destroy', $connection))
            ->assertRedirect(route('portal'));

        $this->assertDatabaseHas('channel_connections', ['id' => $connection->id]);
    }
}
