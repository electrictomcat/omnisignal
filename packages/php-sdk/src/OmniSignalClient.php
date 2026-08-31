<?php

namespace OmniSignal;

use OmniSignal\DTO\ConversionPayload;
use OmniSignal\Drivers\GoogleAdsDriver;
use OmniSignal\Drivers\LinkedInDriver;
use OmniSignal\Drivers\MetaCapiDriver;
use OmniSignal\Drivers\MicrosoftAdsDriver;
use OmniSignal\Drivers\TikTokDriver;

class OmniSignalClient
{
    protected array $config;
    protected array $drivers = [];
    protected string $apiBase = 'https://omnisignal.dev/api/v1';

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->initDrivers();
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    protected function initDrivers(): void
    {
        if (! empty($this->config['meta']['pixel_id']) && ! empty($this->config['meta']['access_token'])) {
            $this->drivers['meta'] = new MetaCapiDriver($this->config['meta']);
        }

        if (! empty($this->config['tiktok']['pixel_code']) && ! empty($this->config['tiktok']['access_token'])) {
            $this->drivers['tiktok'] = new TikTokDriver($this->config['tiktok']);
        }

        if (! empty($this->config['google']['customer_id'])) {
            $this->drivers['google'] = new GoogleAdsDriver($this->config['google']);
        }

        if (! empty($this->config['linkedin']['access_token'])) {
            $this->drivers['linkedin'] = new LinkedInDriver($this->config['linkedin']);
        }

        if (! empty($this->config['microsoft']['customer_id'])) {
            $this->drivers['microsoft'] = new MicrosoftAdsDriver($this->config['microsoft']);
        }
    }

    /**
     * Send a single conversion event directly to all configured ad channels.
     */
    public function record(
        string $eventName,
        float $value = 0.0,
        string $currency = 'USD',
        ?string $orderId = null,
        array $user = [],
        array $clickIds = [],
        array $customData = []
    ): array {
        $payload = new ConversionPayload(
            eventName: $eventName,
            value: $value,
            currency: $currency,
            orderId: $orderId,
            userData: $user,
            gclid: $clickIds['gclid'] ?? null,
            gbraid: $clickIds['gbraid'] ?? null,
            wbraid: $clickIds['wbraid'] ?? null,
            fbclid: $clickIds['fbclid'] ?? null,
            ttclid: $clickIds['ttclid'] ?? null,
            msclkid: $clickIds['msclkid'] ?? null,
            liFatId: $clickIds['li_fat_id'] ?? null,
            customData: $customData
        );

        return $this->send([$payload]);
    }

    /**
     * Broadcast batch conversion payloads across all configured ad channels.
     *
     * @param  ConversionPayload[]  $conversions
     */
    public function send(array $conversions): array
    {
        $results = [];

        foreach ($this->drivers as $channel => $driver) {
            try {
                $results[$channel] = $driver->upload($conversions);
            } catch (\Throwable $e) {
                $results[$channel] = [
                    'success' => false,
                    'count' => 0,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return $results;
    }

    /**
     * Activate a license key for a domain.
     */
    public function activateLicense(string $licenseKey, string $domain): array
    {
        $url = $this->apiBase . '/licenses/activate';
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['license_key' => $licenseKey, 'domain' => $domain]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'data' => json_decode((string) $response, true) ?: [],
        ];
    }
}
