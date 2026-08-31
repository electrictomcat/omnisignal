<?php

namespace OmniSignal\Drivers;

use OmniSignal\DTO\ConversionPayload;
use OmniSignal\Http\HttpClient;
use OmniSignal\Support\Normalizer;

/**
 * TikTok Events API (server-to-server).
 *
 * Required config: pixel_code, access_token.
 * Optional: default_calling_code.
 */
class TikTokDriver
{
    protected const ENDPOINT = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

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
        return ! empty($this->config['pixel_code']) && ! empty($this->config['access_token']);
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
                'errors' => ['TikTok is not configured. Required: pixel_code, access_token.'],
                'channel' => 'tiktok',
            ];
        }

        if (empty($conversions)) {
            return ['success' => true, 'count' => 0, 'errors' => [], 'channel' => 'tiktok'];
        }

        $pixelCode = $this->config['pixel_code'];
        $events = [];

        foreach ($conversions as $conv) {
            $user = [];

            if ($hashed = $this->normalizer->hashEmail($conv->userData['email'] ?? null)) {
                $user['email'] = $hashed;
            }

            $phone = $conv->userData['phone'] ?? $conv->userData['phone_number'] ?? null;
            if ($hashed = $this->normalizer->hashPhone($phone)) {
                $user['phone_number'] = $hashed;
            }

            if (! empty($conv->ttclid)) {
                $user['ttclid'] = $conv->ttclid;
            }

            // TikTok weights IP and user agent heavily for match quality.
            if (! empty($conv->userData['client_ip'])) {
                $user['ip'] = $conv->userData['client_ip'];
            }
            if (! empty($conv->userData['client_user_agent'])) {
                $user['user_agent'] = $conv->userData['client_user_agent'];
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

            if ($conv->eventSourceUrl) {
                $event['page'] = ['url' => $conv->eventSourceUrl];
            }

            if ($conv->orderId) {
                $event['event_id'] = (string) $conv->orderId;
            }

            $events[] = $event;
        }

        $response = $this->http->postJson(self::ENDPOINT, [
            'pixel_code' => $pixelCode,
            'event_source' => 'web',
            'event_source_id' => $pixelCode,
            'data' => $events,
        ], ['Access-Token: '.$this->config['access_token']]);

        // TikTok answers 200 with a non-zero `code` on rejection, so the HTTP
        // status alone is not evidence that anything was accepted.
        $code = (int) ($response['body']['code'] ?? -1);

        if (! $response['ok'] || $code !== 0) {
            $message = $response['body']['message'] ?? $response['error'] ?? 'Upload failed.';

            return [
                'success' => false,
                'count' => 0,
                'errors' => [$message],
                'channel' => 'tiktok',
                'response' => $response['body'],
            ];
        }

        return [
            'success' => true,
            'count' => count($events),
            'errors' => [],
            'channel' => 'tiktok',
            'response' => $response['body'],
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'TikTok is not configured.'];
        }

        $response = $this->http->get(
            'https://business-api.tiktok.com/open_api/v1.3/user/info/',
            ['Access-Token: '.$this->config['access_token']],
        );

        $code = (int) ($response['body']['code'] ?? -1);

        if (! $response['ok'] || $code !== 0) {
            $message = $response['body']['message'] ?? $response['error'] ?? 'unknown error';

            return ['success' => false, 'message' => 'TikTok rejected the access token: '.$message];
        }

        $name = $response['body']['data']['display_name'] ?? null;

        return [
            'success' => true,
            'message' => 'TikTok authenticated'.($name ? " as {$name}" : '')." for pixel {$this->config['pixel_code']}",
        ];
    }
}
