<?php

namespace OmniSignal\Drivers;

use OmniSignal\DTO\ConversionPayload;

class MetaCapiDriver
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
        $pixelId = $this->config['pixel_id'] ?? '';
        $accessToken = $this->config['access_token'] ?? '';
        $apiVersion = $this->config['api_version'] ?? 'v20.0';

        if (empty($pixelId) || empty($accessToken) || empty($conversions)) {
            return ['success' => false, 'count' => 0, 'errors' => ['Missing Meta credentials']];
        }

        $events = [];
        foreach ($conversions as $conv) {
            $userData = [];
            if (! empty($conv->userData['email'])) {
                $userData['em'] = [hash('sha256', strtolower(trim($conv->userData['email'])))];
            }
            if (! empty($conv->userData['phone'])) {
                $userData['ph'] = [hash('sha256', preg_replace('/[^\d+]/', '', $conv->userData['phone']))];
            }
            if (! empty($conv->fbclid)) {
                $userData['fbc'] = 'fb.1.' . $conv->timestamp . '.' . $conv->fbclid;
            }

            $event = [
                'event_name' => $conv->eventName,
                'event_time' => $conv->timestamp,
                'action_source' => $conv->actionSource,
                'user_data' => $userData,
                'custom_data' => array_merge($conv->customData, [
                    'value' => $conv->value,
                    'currency' => $conv->currency,
                ]),
            ];

            if ($conv->orderId) {
                $event['event_id'] = (string) $conv->orderId;
            }

            $events[] = $event;
        }

        $url = "https://graph.facebook.com/{$apiVersion}/{$pixelId}/events";
        $body = ['data' => $events];
        if (! empty($this->config['test_event_code'])) {
            $body['test_event_code'] = $this->config['test_event_code'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode((string) $response, true) ?: [];

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'count' => count($events),
            'response' => $result,
        ];
    }
}
