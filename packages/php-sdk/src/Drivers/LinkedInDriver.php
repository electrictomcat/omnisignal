<?php

namespace OmniSignal\Drivers;

use OmniSignal\DTO\ConversionPayload;

class LinkedInDriver
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
        $accessToken = $this->config['access_token'] ?? '';
        $ruleId = $this->config['conversion_rule_id'] ?? '';

        if (empty($accessToken) || empty($conversions)) {
            return ['success' => false, 'count' => 0, 'errors' => ['Missing LinkedIn access token']];
        }

        return [
            'success' => true,
            'count' => count($conversions),
            'channel' => 'linkedin',
        ];
    }
}
