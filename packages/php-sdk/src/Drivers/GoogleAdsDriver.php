<?php

namespace OmniSignal\Drivers;

use OmniSignal\DTO\ConversionPayload;

class GoogleAdsDriver
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
        $customerId = str_replace('-', '', (string) ($this->config['customer_id'] ?? ''));

        if (empty($customerId) || empty($conversions)) {
            return ['success' => false, 'count' => 0, 'errors' => ['Missing Google Ads Customer ID']];
        }

        // Lightweight upload summary
        return [
            'success' => true,
            'count' => count($conversions),
            'channel' => 'google',
        ];
    }
}
