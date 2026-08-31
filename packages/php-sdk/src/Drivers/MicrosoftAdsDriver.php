<?php

namespace OmniSignal\Drivers;

use OmniSignal\DTO\ConversionPayload;
use OmniSignal\Http\HttpClient;

/**
 * Microsoft Advertising offline conversions.
 *
 * Campaign Management v13 REST:
 *   POST /CampaignManagement/v13/OfflineConversions/Apply
 *
 * Required config: developer_token, customer_id, account_id, access_token.
 * The manager (customer) ID and the ad account ID are different values and
 * both headers are required.
 *
 * @see https://learn.microsoft.com/en-us/advertising/campaign-management-service/applyofflineconversions?view=bingads-13
 */
class MicrosoftAdsDriver
{
    protected const APPLY_URL = 'https://campaign.api.bingads.microsoft.com/CampaignManagement/v13/OfflineConversions/Apply';

    protected const USER_QUERY_URL = 'https://clientcenter.api.bingads.microsoft.com/CustomerManagement/v13/User/Query';

    /** Microsoft accepts at most 1,000 offline conversions per request. */
    protected const MAX_PER_REQUEST = 1000;

    protected HttpClient $http;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config, ?HttpClient $http = null)
    {
        $this->http = $http ?? new HttpClient(30);
    }

    public function isConfigured(): bool
    {
        foreach (['developer_token', 'customer_id', 'account_id', 'access_token'] as $key) {
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
            return [
                'success' => false,
                'count' => 0,
                'errors' => ['Microsoft Ads is not fully configured. Required: developer_token, customer_id, account_id, access_token.'],
                'channel' => 'microsoft',
            ];
        }

        $items = [];
        $errors = [];

        foreach ($conversions as $conv) {
            if (! $conv->msclkid) {
                $errors[] = "Conversion '{$conv->eventName}' skipped: no msclkid to attribute it with.";

                continue;
            }

            $entry = [
                'MicrosoftClickId' => $conv->msclkid,
                'ConversionName' => $conv->eventName,
                // Microsoft requires UTC with a Z suffix, not a local offset.
                'ConversionTime' => gmdate('Y-m-d\TH:i:s\Z', $conv->timestamp),
            ];

            if ($conv->value !== null) {
                $entry['ConversionValue'] = (float) $conv->value;
                $entry['ConversionCurrencyCode'] = $conv->currency ?? 'USD';
            }

            $items[] = $entry;
        }

        if ($items === []) {
            return ['success' => $errors === [], 'count' => 0, 'errors' => $errors, 'channel' => 'microsoft'];
        }

        $uploaded = 0;
        $last = [];

        foreach (array_chunk($items, self::MAX_PER_REQUEST) as $chunk) {
            $response = $this->http->postJson(self::APPLY_URL, ['OfflineConversions' => $chunk], $this->headers());
            $last = $response['body'];

            if (! $response['ok']) {
                $errors[] = $response['error'] ?? 'Upload failed.';

                continue;
            }

            $partial = $response['body']['PartialErrors'] ?? [];

            foreach ($partial as $error) {
                $index = $error['Index'] ?? '?';
                $message = $error['Message'] ?? ($error['ErrorCode'] ?? 'Unknown error');
                $errors[] = "Item {$index}: {$message}";
            }

            $uploaded += count($chunk) - count($partial);
        }

        return [
            'success' => $errors === [],
            'count' => $uploaded,
            'errors' => $errors,
            'channel' => 'microsoft',
            'response' => $last,
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Microsoft Ads is not fully configured.'];
        }

        // GetUser needs only the token and developer token.
        $response = $this->http->postJson(self::USER_QUERY_URL, ['UserId' => null], [
            'Authorization: Bearer '.$this->config['access_token'],
            'DeveloperToken: '.$this->config['developer_token'],
        ]);

        if (! $response['ok']) {
            return ['success' => false, 'message' => 'Microsoft rejected the credentials: '.($response['error'] ?? 'unknown error')];
        }

        $name = $response['body']['User']['Name']['FirstName'] ?? null;

        return [
            'success' => true,
            'message' => 'Microsoft Ads authenticated'.($name ? " as {$name}" : '')
                .' for account '.$this->config['account_id'],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function headers(): array
    {
        return [
            'Authorization: Bearer '.$this->config['access_token'],
            'DeveloperToken: '.$this->config['developer_token'],
            'CustomerId: '.$this->config['customer_id'],
            'CustomerAccountId: '.$this->config['account_id'],
        ];
    }
}
