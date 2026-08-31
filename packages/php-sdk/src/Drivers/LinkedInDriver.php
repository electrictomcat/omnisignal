<?php

namespace OmniSignal\Drivers;

use OmniSignal\DTO\ConversionPayload;
use OmniSignal\Http\HttpClient;
use OmniSignal\Support\Normalizer;

/**
 * LinkedIn Conversions API.
 *
 * Required config: access_token, conversion_rule_id.
 * Optional: version (YYYYMM), default_calling_code.
 *
 * @see https://learn.microsoft.com/en-us/linkedin/marketing/integrations/ads-reporting/conversions-api-schema
 */
class LinkedInDriver
{
    protected const ENDPOINT = 'https://api.linkedin.com/rest/conversionEvents';

    /**
     * LinkedIn supports a version for a minimum of one year, then retires it —
     * at which point every call returns 426. Overridable via config so it can
     * be rolled forward without an SDK release.
     */
    public const DEFAULT_VERSION = '202608';

    protected HttpClient $http;

    protected Normalizer $normalizer;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config, ?HttpClient $http = null)
    {
        $this->http = $http ?? new HttpClient(15);
        $this->normalizer = new Normalizer($config['default_calling_code'] ?? null);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['access_token']) && ! empty($this->config['conversion_rule_id']);
    }

    /**
     * @param  ConversionPayload[]  $conversions
     * @return array{success: bool, count: int, errors: array<int, string>, channel: string}
     */
    public function upload(array $conversions): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'count' => 0,
                'errors' => ['LinkedIn is not configured. Required: access_token, conversion_rule_id.'],
                'channel' => 'linkedin',
            ];
        }

        $ruleId = $this->config['conversion_rule_id'];
        $version = (string) ($this->config['version'] ?? self::DEFAULT_VERSION);

        $uploaded = 0;
        $errors = [];

        foreach ($conversions as $conv) {
            $userIds = $this->userIds($conv);

            if ($userIds === []) {
                $errors[] = "Conversion '{$conv->eventName}' skipped: no hashed email or li_fat_id to identify the member.";

                continue;
            }

            $body = [
                'conversion' => "urn:lla:llaPartnerConversion:{$ruleId}",
                'conversionHappenedAt' => $conv->timestamp * 1000,
                'user' => ['userIds' => $userIds],
            ];

            if ($conv->value !== null) {
                // conversionValue, not totalBudget — the latter is a campaign
                // budget field that this endpoint ignores. `amount` is a string.
                $body['conversionValue'] = [
                    'currencyCode' => $conv->currency ?? 'USD',
                    'amount' => number_format((float) $conv->value, 2, '.', ''),
                ];
            }

            if ($conv->orderId !== null) {
                $body['eventId'] = (string) $conv->orderId;
            }

            $response = $this->http->postJson(self::ENDPOINT, $body, [
                'Authorization: Bearer '.$this->config['access_token'],
                'X-Restli-Protocol-Version: 2.0.0',
                'LinkedIn-Version: '.$version,
            ]);

            if ($response['ok']) {
                $uploaded++;

                continue;
            }

            if ($response['status'] === 426) {
                $errors[] = "LinkedIn API version {$version} is no longer supported. Set a current YYYYMM value in config.";

                continue;
            }

            $errors[] = $response['error'] ?? 'Upload failed.';
        }

        return [
            // Only a clean run counts as success; one landing event out of a
            // hundred is not "it worked".
            'success' => $errors === [],
            'count' => $uploaded,
            'errors' => $errors,
            'channel' => 'linkedin',
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'LinkedIn is not configured.'];
        }

        $ruleId = $this->config['conversion_rule_id'];
        $version = (string) ($this->config['version'] ?? self::DEFAULT_VERSION);

        $response = $this->http->get("https://api.linkedin.com/rest/conversions/{$ruleId}", [
            'Authorization: Bearer '.$this->config['access_token'],
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: '.$version,
        ]);

        if ($response['status'] === 426) {
            return ['success' => false, 'message' => "LinkedIn API version {$version} is no longer supported."];
        }

        if (! $response['ok']) {
            return ['success' => false, 'message' => 'LinkedIn rejected the request: '.($response['error'] ?? 'unknown error')];
        }

        $name = $response['body']['name'] ?? null;

        return [
            'success' => true,
            'message' => "LinkedIn authenticated for conversion rule {$ruleId}".($name ? " ({$name})" : ''),
        ];
    }

    /**
     * @return array<int, array{idType: string, idValue: string}>
     */
    protected function userIds(ConversionPayload $conv): array
    {
        $userIds = [];

        if ($hashed = $this->normalizer->hashEmail($conv->userData['email'] ?? null)) {
            $userIds[] = ['idType' => 'SHA256_EMAIL', 'idValue' => $hashed];
        }

        if ($conv->liFatId) {
            $userIds[] = ['idType' => 'LINKEDIN_FIRST_PARTY_ADS_TRACKING_UUID', 'idValue' => $conv->liFatId];
        }

        return $userIds;
    }
}
