<?php

namespace OmniSignal\Drivers;

use OmniSignal\DTO\ConversionPayload;
use OmniSignal\Http\HttpClient;
use OmniSignal\Support\Normalizer;

/**
 * Google Ads offline click conversions.
 *
 * Uses the Google Ads API REST transport so the SDK stays dependency-free:
 *
 *   POST /v{version}/customers/{customerId}:uploadClickConversions
 *
 * An OAuth access token is minted from the stored refresh token on demand and
 * reused for the life of the instance.
 *
 * Required config:
 *   developer_token, client_id, client_secret, refresh_token, customer_id,
 *   conversion_action (resource name, or the numeric conversion action ID)
 * Optional:
 *   login_customer_id, api_version, default_currency, default_calling_code
 */
class GoogleAdsDriver
{
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    protected const DEFAULT_VERSION = 'v23';

    protected ?string $accessToken = null;

    protected HttpClient $http;

    protected Normalizer $normalizer;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config, ?HttpClient $http = null)
    {
        $this->http = $http ?? new HttpClient(30);
        $this->normalizer = new Normalizer($config['default_calling_code'] ?? null);
    }

    public function isConfigured(): bool
    {
        foreach (['developer_token', 'client_id', 'client_secret', 'refresh_token', 'customer_id', 'conversion_action'] as $key) {
            if (empty($this->config[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  ConversionPayload[]  $conversions
     * @return array{success: bool, count: int, errors: array<int, string>, channel: string, response?: array<string, mixed>}
     */
    public function upload(array $conversions): array
    {
        if (! $this->isConfigured()) {
            return $this->failure([
                'Google Ads is not fully configured. Required: developer_token, client_id, '
                .'client_secret, refresh_token, customer_id, conversion_action.',
            ]);
        }

        if (empty($conversions)) {
            return ['success' => true, 'count' => 0, 'errors' => [], 'channel' => 'google'];
        }

        $customerId = $this->customerId();
        $errors = [];
        $operations = [];

        foreach ($conversions as $conv) {
            $operation = [
                'conversionAction' => $this->conversionActionResourceName($customerId),
                'conversionDateTime' => date('Y-m-d H:i:sP', $conv->timestamp),
            ];

            $clickId = null;

            if ($conv->gbraid) {
                $operation['gbraid'] = $clickId = $conv->gbraid;
            } elseif ($conv->wbraid) {
                $operation['wbraid'] = $clickId = $conv->wbraid;
            } elseif ($conv->gclid) {
                $operation['gclid'] = $clickId = $conv->gclid;
            }

            $identifiers = $this->userIdentifiers($conv);

            if ($identifiers !== []) {
                $operation['userIdentifiers'] = $identifiers;
            }

            // Google needs a click identifier or hashed identifiers. With
            // neither, the row is a guaranteed rejection — say so here rather
            // than letting it fail silently upstream.
            if ($clickId === null && $identifiers === []) {
                $errors[] = "Conversion '{$conv->eventName}' has no click identifier and no user identifiers.";

                continue;
            }

            if ($conv->value !== null) {
                $operation['conversionValue'] = (float) $conv->value;
                $operation['currencyCode'] = $conv->currency ?? ($this->config['default_currency'] ?? 'USD');
            }

            if ($conv->orderId !== null) {
                $operation['orderId'] = (string) $conv->orderId;
            }

            $operations[] = $operation;
        }

        if ($operations === []) {
            return $this->failure($errors);
        }

        $token = $this->accessToken();

        if ($token === null) {
            return $this->failure(array_merge($errors, ['Could not obtain a Google Ads access token from the refresh token.']));
        }

        $version = $this->config['api_version'] ?? self::DEFAULT_VERSION;
        $url = "https://googleads.googleapis.com/{$version}/customers/{$customerId}:uploadClickConversions";

        $headers = [
            'Authorization: Bearer '.$token,
            'developer-token: '.$this->config['developer_token'],
        ];

        if (! empty($this->config['login_customer_id'])) {
            $headers[] = 'login-customer-id: '.preg_replace('/\D/', '', (string) $this->config['login_customer_id']);
        }

        $response = $this->http->postJson($url, [
            'conversions' => $operations,
            'partialFailure' => true,
        ], $headers);

        if (! $response['ok']) {
            return $this->failure(array_merge($errors, [$response['error'] ?? 'Upload failed.']), $response['body']);
        }

        // partialFailureError carries per-row rejections. Anything listed there
        // was NOT accepted, and must not be counted as delivered.
        $rejected = $this->rejectedCount($response['body']);

        if ($rejected > 0) {
            $errors[] = $this->partialFailureMessage($response['body']);
        }

        return [
            'success' => $errors === [],
            'count' => count($operations) - $rejected,
            'errors' => $errors,
            'channel' => 'google',
            'response' => $response['body'],
        ];
    }

    /**
     * Verify credentials with a real authenticated call.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Google Ads is not fully configured.'];
        }

        $token = $this->accessToken();

        if ($token === null) {
            return ['success' => false, 'message' => 'Google rejected the refresh token.'];
        }

        $version = $this->config['api_version'] ?? self::DEFAULT_VERSION;
        $customerId = $this->customerId();

        $headers = [
            'Authorization: Bearer '.$token,
            'developer-token: '.$this->config['developer_token'],
        ];

        if (! empty($this->config['login_customer_id'])) {
            $headers[] = 'login-customer-id: '.preg_replace('/\D/', '', (string) $this->config['login_customer_id']);
        }

        $response = $this->http->postJson(
            "https://googleads.googleapis.com/{$version}/customers/{$customerId}/googleAds:search",
            ['query' => 'SELECT customer.descriptive_name, customer.currency_code FROM customer LIMIT 1'],
            $headers,
        );

        if (! $response['ok']) {
            return ['success' => false, 'message' => 'Google Ads rejected the request: '.($response['error'] ?? 'unknown error')];
        }

        $name = $response['body']['results'][0]['customer']['descriptiveName'] ?? null;

        return [
            'success' => true,
            'message' => "Google Ads authenticated for customer {$customerId}".($name ? " ({$name})" : ''),
        ];
    }

    protected function customerId(): string
    {
        return preg_replace('/\D/', '', (string) ($this->config['customer_id'] ?? '')) ?? '';
    }

    protected function conversionActionResourceName(string $customerId): string
    {
        $action = (string) $this->config['conversion_action'];

        // Accept either a full resource name or the bare numeric ID.
        return str_contains($action, '/')
            ? $action
            : "customers/{$customerId}/conversionActions/{$action}";
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function userIdentifiers(ConversionPayload $conv): array
    {
        $identifiers = [];

        if ($hashed = $this->normalizer->hashEmail($conv->userData['email'] ?? null)) {
            $identifiers[] = ['hashedEmail' => $hashed];
        }

        $phone = $conv->userData['phone'] ?? $conv->userData['phone_number'] ?? null;
        if ($hashed = $this->normalizer->hashPhone($phone)) {
            $identifiers[] = ['hashedPhoneNumber' => $hashed];
        }

        return $identifiers;
    }

    protected function accessToken(): ?string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = $this->http->postForm(self::TOKEN_URL, [
            'client_id' => (string) $this->config['client_id'],
            'client_secret' => (string) $this->config['client_secret'],
            'refresh_token' => (string) $this->config['refresh_token'],
            'grant_type' => 'refresh_token',
        ]);

        if (! $response['ok'] || empty($response['body']['access_token'])) {
            return null;
        }

        return $this->accessToken = (string) $response['body']['access_token'];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function rejectedCount(array $body): int
    {
        $details = $body['partialFailureError']['details'] ?? [];
        $count = 0;

        foreach ($details as $detail) {
            $count += count($detail['errors'] ?? []);
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function partialFailureMessage(array $body): string
    {
        $message = $body['partialFailureError']['message'] ?? 'Some conversions were rejected.';

        return 'Partial failure: '.$message;
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<string, mixed>  $response
     * @return array{success: bool, count: int, errors: array<int, string>, channel: string, response?: array<string, mixed>}
     */
    protected function failure(array $errors, array $response = []): array
    {
        return [
            'success' => false,
            'count' => 0,
            'errors' => $errors,
            'channel' => 'google',
            'response' => $response,
        ];
    }
}
