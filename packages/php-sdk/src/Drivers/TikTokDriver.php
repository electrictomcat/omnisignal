<?php

namespace OmniSignal\Drivers;

use OmniSignal\DTO\ConversionPayload;

class TikTokDriver
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
        $pixelCode = $this->config['pixel_code'] ?? '';
        $accessToken = $this->config['access_token'] ?? '';

        if (empty($pixelCode) || empty($accessToken) || empty($conversions)) {
            return ['success' => false, 'count' => 0, 'errors' => ['Missing TikTok credentials']];
        }

        $events = [];
        foreach ($conversions as $conv) {
            $user = [];
            if (! empty($conv->userData['email'])) {
                $user['email'] = hash('sha256', strtolower(trim($conv->userData['email'])));
            }
            if (! empty($conv->userData['phone'])) {
                $user['phone_number'] = hash('sha256', preg_replace('/[^\d+]/', '', $conv->userData['phone']));
            }
            if (! empty($conv->ttclid)) {
                $user['ttclid'] = $conv->ttclid;
            }

            $event = [
                'event' => $conv->eventName === 'Purchase' ? 'CompletePayment' : $conv->eventName,
                'event_time' => $conv->timestamp,
                'user' => $user,
                'properties' => array_merge($conv->customData, [
                    'value' => $conv->value,
                    'currency' => $conv->currency,
                ]),
            ];

            if ($conv->orderId) {
                $event['event_id'] = (string) $conv->orderId;
            }

            $events[] = $event;
        }

        $url = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';
        $body = [
            'pixel_code' => $pixelCode,
            'event_source' => 'web',
            'event_source_id' => $pixelCode,
            'data' => $events,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Access-Token: ' . $accessToken,
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
