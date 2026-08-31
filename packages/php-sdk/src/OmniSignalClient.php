<?php

namespace OmniSignal;

use OmniSignal\Drivers\GoogleAdsDriver;
use OmniSignal\Drivers\LinkedInDriver;
use OmniSignal\Drivers\MetaCapiDriver;
use OmniSignal\Drivers\MicrosoftAdsDriver;
use OmniSignal\Drivers\TikTokDriver;
use OmniSignal\DTO\ConversionPayload;

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

        // These three ask the driver itself whether it has everything it
        // needs, rather than guessing from one key. A half-configured channel
        // that silently no-ops is worse than one that says it is not set up.
        foreach ([
            'google' => GoogleAdsDriver::class,
            'linkedin' => LinkedInDriver::class,
            'microsoft' => MicrosoftAdsDriver::class,
        ] as $name => $class) {
            if (empty($this->config[$name])) {
                continue;
            }

            $driver = new $class($this->config[$name]);

            if ($driver->isConfigured()) {
                $this->drivers[$name] = $driver;
            }
        }
    }

    /**
     * Check every configured channel's credentials against its live API.
     *
     * @return array<string, array{success: bool, message: string}>
     */
    public function testConnections(): array
    {
        $results = [];

        foreach ($this->drivers as $channel => $driver) {
            if (! method_exists($driver, 'testConnection')) {
                continue;
            }

            try {
                $results[$channel] = $driver->testConnection();
            } catch (\Throwable $e) {
                $results[$channel] = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * The channels that are configured and will actually be sent to.
     *
     * @return array<int, string>
     */
    public function activeChannels(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Send a single conversion event directly to all configured ad channels.
     */
    public function record(
        string $eventName,
        ?float $value = null,
        string $currency = 'USD',
        ?string $orderId = null,
        array $user = [],
        array $clickIds = [],
        array $customData = [],
        ?string $eventSourceUrl = null,
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
            fbc: $clickIds['fbc'] ?? null,
            fbp: $clickIds['fbp'] ?? null,
            liFatId: $clickIds['li_fat_id'] ?? null,
            customData: $customData,
            eventSourceUrl: $eventSourceUrl,
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
        $url = $this->apiBase.'/licenses/activate';
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
