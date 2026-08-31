<?php

namespace Tests\Feature;

use OmniSignal\Drivers\GoogleAdsDriver;
use OmniSignal\Drivers\LinkedInDriver;
use OmniSignal\Drivers\MetaCapiDriver;
use OmniSignal\Drivers\MicrosoftAdsDriver;
use OmniSignal\Drivers\TikTokDriver;
use OmniSignal\DTO\ConversionPayload;
use OmniSignal\Http\HttpClient;
use OmniSignal\OmniSignalClient;
use PHPUnit\Framework\TestCase;

/**
 * Regressions for OS-07.
 *
 * Google, LinkedIn and Microsoft used to check a credential was non-empty and
 * return ['success' => true] without making a single request. These tests
 * assert that every channel now actually talks to its API, and that a refusal
 * is reported as a refusal.
 */
class SdkDriverTest extends TestCase
{
    /**
     * @param  array<int, array{ok: bool, status: int, body: array<string, mixed>, error: string|null}>  $responses
     */
    private function recordingClient(array $responses): HttpClient
    {
        return new class($responses) extends HttpClient
        {
            /** @var array<int, array{url: string, method: string, headers: array<int, string>, body: mixed}> */
            public array $requests = [];

            /** @param array<int, array<string, mixed>> $responses */
            public function __construct(private array $responses)
            {
                parent::__construct();
            }

            public function postJson(string $url, array $body, array $headers = []): array
            {
                $this->requests[] = ['url' => $url, 'method' => 'POST', 'headers' => $headers, 'body' => $body];

                return $this->next();
            }

            public function postForm(string $url, array $fields): array
            {
                $this->requests[] = ['url' => $url, 'method' => 'POST', 'headers' => [], 'body' => $fields];

                return $this->next();
            }

            public function get(string $url, array $headers = []): array
            {
                $this->requests[] = ['url' => $url, 'method' => 'GET', 'headers' => $headers, 'body' => null];

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

    /** @return array{ok: bool, status: int, body: array<string, mixed>, error: string|null} */
    private function ok(array $body = []): array
    {
        return ['ok' => true, 'status' => 200, 'body' => $body, 'error' => null];
    }

    /** @return array{ok: bool, status: int, body: array<string, mixed>, error: string|null} */
    private function refused(int $status, string $error): array
    {
        return ['ok' => false, 'status' => $status, 'body' => [], 'error' => $error];
    }

    private function payload(array $overrides = []): ConversionPayload
    {
        return new ConversionPayload(
            eventName: $overrides['eventName'] ?? 'Purchase',
            value: $overrides['value'] ?? 49.99,
            currency: $overrides['currency'] ?? 'USD',
            orderId: $overrides['orderId'] ?? 'ORD-1',
            timestamp: 1767225600,
            gclid: $overrides['gclid'] ?? null,
            msclkid: $overrides['msclkid'] ?? null,
            ttclid: $overrides['ttclid'] ?? null,
            liFatId: $overrides['liFatId'] ?? null,
            userData: $overrides['userData'] ?? [],
        );
    }

    // ------------------------------------------------------------- Google

    public function test_google_actually_posts_a_conversion(): void
    {
        $http = $this->recordingClient([
            $this->ok(['access_token' => 'ya29.token']),
            $this->ok(['results' => [['gclid' => 'GC-1']]]),
        ]);

        $driver = new GoogleAdsDriver([
            'developer_token' => 'dev', 'client_id' => 'cid', 'client_secret' => 'secret',
            'refresh_token' => 'refresh', 'customer_id' => '123-456-7890', 'conversion_action' => '555',
        ], $http);

        $result = $driver->upload([$this->payload(['gclid' => 'GC-1'])]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['count']);
        $this->assertCount(2, $http->requests, 'expected a token exchange and an upload');

        $upload = $http->requests[1];
        $this->assertSame(
            'https://googleads.googleapis.com/v23/customers/1234567890:uploadClickConversions',
            $upload['url'],
        );
        $this->assertContains('developer-token: dev', $upload['headers']);
        $this->assertContains('Authorization: Bearer ya29.token', $upload['headers']);

        $conversion = $upload['body']['conversions'][0];
        $this->assertSame('GC-1', $conversion['gclid']);
        $this->assertSame('customers/1234567890/conversionActions/555', $conversion['conversionAction']);
        $this->assertSame(49.99, $conversion['conversionValue']);
        $this->assertSame('ORD-1', $conversion['orderId']);
    }

    public function test_google_reports_failure_instead_of_claiming_success(): void
    {
        $http = $this->recordingClient([
            $this->ok(['access_token' => 'ya29.token']),
            $this->refused(403, 'HTTP 403: The caller does not have permission'),
        ]);

        $driver = new GoogleAdsDriver([
            'developer_token' => 'dev', 'client_id' => 'cid', 'client_secret' => 'secret',
            'refresh_token' => 'refresh', 'customer_id' => '1234567890', 'conversion_action' => '555',
        ], $http);

        $result = $driver->upload([$this->payload(['gclid' => 'GC-1'])]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['count']);
        $this->assertStringContainsString('permission', $result['errors'][0]);
    }

    public function test_google_does_not_count_partially_rejected_rows_as_delivered(): void
    {
        $http = $this->recordingClient([
            $this->ok(['access_token' => 'ya29.token']),
            $this->ok(['partialFailureError' => [
                'message' => 'Invalid gclid',
                'details' => [['errors' => [['message' => 'Invalid gclid']]]],
            ]]),
        ]);

        $driver = new GoogleAdsDriver([
            'developer_token' => 'dev', 'client_id' => 'cid', 'client_secret' => 'secret',
            'refresh_token' => 'refresh', 'customer_id' => '1234567890', 'conversion_action' => '555',
        ], $http);

        $result = $driver->upload([$this->payload(['gclid' => 'GC-1'])]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['count']);
    }

    public function test_google_refuses_to_run_when_half_configured(): void
    {
        $http = $this->recordingClient([]);

        // conversion_action missing — previously this returned success anyway.
        $driver = new GoogleAdsDriver([
            'developer_token' => 'dev', 'client_id' => 'cid', 'client_secret' => 'secret',
            'refresh_token' => 'refresh', 'customer_id' => '1234567890',
        ], $http);

        $result = $driver->upload([$this->payload(['gclid' => 'GC-1'])]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['count']);
        $this->assertCount(0, $http->requests);
    }

    // ---------------------------------------------------------- Microsoft

    public function test_microsoft_actually_posts_a_conversion(): void
    {
        $http = $this->recordingClient([$this->ok(['PartialErrors' => []])]);

        $driver = new MicrosoftAdsDriver([
            'developer_token' => 'dev', 'customer_id' => '111',
            'account_id' => '222', 'access_token' => 'tok',
        ], $http);

        $result = $driver->upload([$this->payload(['msclkid' => 'MS-1'])]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['count']);

        $request = $http->requests[0];
        $this->assertSame(
            'https://campaign.api.bingads.microsoft.com/CampaignManagement/v13/OfflineConversions/Apply',
            $request['url'],
        );
        $this->assertContains('CustomerAccountId: 222', $request['headers']);
        $this->assertSame('2026-01-01T00:00:00Z', $request['body']['OfflineConversions'][0]['ConversionTime']);
    }

    public function test_microsoft_surfaces_per_item_errors(): void
    {
        $http = $this->recordingClient([
            $this->ok(['PartialErrors' => [['Index' => 0, 'Message' => 'Unknown goal']]]),
        ]);

        $driver = new MicrosoftAdsDriver([
            'developer_token' => 'dev', 'customer_id' => '111',
            'account_id' => '222', 'access_token' => 'tok',
        ], $http);

        $result = $driver->upload([$this->payload(['msclkid' => 'MS-1'])]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['count']);
        $this->assertStringContainsString('Unknown goal', $result['errors'][0]);
    }

    // ----------------------------------------------------------- LinkedIn

    public function test_linkedin_actually_posts_and_uses_conversion_value(): void
    {
        $http = $this->recordingClient([$this->ok()]);

        $driver = new LinkedInDriver(['access_token' => 'tok', 'conversion_rule_id' => '987'], $http);

        $result = $driver->upload([$this->payload(['userData' => ['email' => 'buyer@example.com']])]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['count']);

        $body = $http->requests[0]['body'];
        $this->assertArrayNotHasKey('totalBudget', $body);
        $this->assertSame('49.99', $body['conversionValue']['amount']);
        $this->assertSame(1767225600000, $body['conversionHappenedAt']);
        $this->assertContains('LinkedIn-Version: '.LinkedInDriver::DEFAULT_VERSION, $http->requests[0]['headers']);
    }

    public function test_linkedin_reports_an_expired_api_version(): void
    {
        $http = $this->recordingClient([$this->refused(426, 'HTTP 426: upgrade required')]);

        $driver = new LinkedInDriver(['access_token' => 'tok', 'conversion_rule_id' => '987'], $http);

        $result = $driver->upload([$this->payload(['userData' => ['email' => 'buyer@example.com']])]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no longer supported', $result['errors'][0]);
    }

    // -------------------------------------------------------- Meta/TikTok

    public function test_meta_reports_a_rejection(): void
    {
        $http = $this->recordingClient([$this->refused(400, 'HTTP 400: Invalid access token')]);

        $driver = new MetaCapiDriver(['pixel_id' => '123', 'access_token' => 'tok'], $http);

        $result = $driver->upload([$this->payload(['userData' => ['email' => 'buyer@example.com']])]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['count']);
    }

    public function test_tiktok_treats_a_non_zero_code_as_failure_despite_http_200(): void
    {
        $http = $this->recordingClient([
            $this->ok(['code' => 40001, 'message' => 'Access token is invalid']),
        ]);

        $driver = new TikTokDriver(['pixel_code' => 'PX', 'access_token' => 'tok'], $http);

        $result = $driver->upload([$this->payload(['ttclid' => 'TT-1'])]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['count']);
        $this->assertStringContainsString('invalid', $result['errors'][0]);
    }

    // ------------------------------------------------------------ Client

    public function test_the_client_does_not_register_a_half_configured_channel(): void
    {
        $client = OmniSignalClient::create([
            // customer_id alone used to be enough to "enable" Google.
            'google' => ['customer_id' => '1234567890'],
            'microsoft' => ['customer_id' => '111'],
            'linkedin' => ['access_token' => 'tok'],
        ]);

        $this->assertSame([], $client->activeChannels());
    }

    public function test_the_client_registers_a_fully_configured_channel(): void
    {
        $client = OmniSignalClient::create([
            'google' => [
                'developer_token' => 'dev', 'client_id' => 'cid', 'client_secret' => 'secret',
                'refresh_token' => 'refresh', 'customer_id' => '1234567890', 'conversion_action' => '555',
            ],
        ]);

        $this->assertSame(['google'], $client->activeChannels());
    }

    // ------------------------------------------------- shared normalization

    public function test_identifiers_normalize_the_same_way_on_every_channel(): void
    {
        $canonical = hash('sha256', 'testuser@gmail.com');
        $userData = ['email' => 'Test.User+promo@Gmail.com'];

        $metaHttp = $this->recordingClient([$this->ok()]);
        (new MetaCapiDriver(['pixel_id' => '1', 'access_token' => 't'], $metaHttp))
            ->upload([$this->payload(['userData' => $userData])]);

        $ttHttp = $this->recordingClient([$this->ok(['code' => 0])]);
        (new TikTokDriver(['pixel_code' => 'P', 'access_token' => 't'], $ttHttp))
            ->upload([$this->payload(['userData' => $userData])]);

        $liHttp = $this->recordingClient([$this->ok()]);
        (new LinkedInDriver(['access_token' => 't', 'conversion_rule_id' => '1'], $liHttp))
            ->upload([$this->payload(['userData' => $userData])]);

        $this->assertSame($canonical, $metaHttp->requests[0]['body']['data'][0]['user_data']['em'][0]);
        $this->assertSame($canonical, $ttHttp->requests[0]['body']['data'][0]['user']['email']);
        $this->assertSame($canonical, $liHttp->requests[0]['body']['user']['userIds'][0]['idValue']);
    }
}
