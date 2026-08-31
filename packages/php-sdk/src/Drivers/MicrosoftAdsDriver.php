<?php

namespace OmniSignal\Drivers;

use OmniSignal\DTO\ConversionPayload;

class MicrosoftAdsDriver
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param  ConversionPayload[]  $conversions
     */
    public function upload(array $conversions): array
    {
        $customerId = $this->config['customer_id'] ?? '';
        $devToken = $this->config['developer_token'] ?? '';

        if (empty($customerId) || empty($conversions)) {
            return ['success' => false, 'count' => 0, 'errors' => ['Missing Microsoft Ads credentials']];
        }

        return [
            'success' => true,
            'count' => count($conversions),
            'channel' => 'microsoft',
        ];
    }
}
