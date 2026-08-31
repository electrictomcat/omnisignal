<?php

namespace OmniSignal\Drivers;

use OmniSignal\DTO\ConversionPayload;
use OmniSignal\Http\HttpClient;
use OmniSignal\Support\Normalizer;

/**
 * Meta (Facebook & Instagram) Conversions API.
 *
 * Required config: pixel_id, access_token.
 * Optional: api_version, test_event_code, default_calling_code.
 */
class MetaCapiDriver
{
    public const DEFAULT_VERSION = 'v20.0';

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
        return ! empty($this->config['pixel_id']) && ! empty($this->config['access_token']);
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
                'errors' => ['Meta is not configured. Required: pixel_id, access_token.'],
                'channel' => 'meta',
            ];
        }

        if (empty($conversions)) {
            return ['success' => true, 'count' => 0, 'errors' => [], 'channel' => 'meta'];
        }

        $apiVersion = $this->config['api_version'] ?? self::DEFAULT_VERSION;
        $events = [];

        foreach ($conversions as $conv) {
            $userData = [];

            if ($hashed = $this->normalizer->hashEmail($conv->userData['email'] ?? null)) {
                $userData['em'] = [$hashed];
            }

            $phone = $conv->userData['phone'] ?? $conv->userData['phone_number'] ?? null;
            if ($hashed = $this->normalizer->hashPhone($phone)) {
                $userData['ph'] = [$hashed];
            }

            // fbc must carry the timestamp of the click, not of the upload.
            if (! empty($conv->fbc)) {
                $userData['fbc'] = $conv->fbc;
            } elseif (! empty($conv->fbclid)) {
                $userData['fbc'] = 'fb.1.'.$conv->timestamp.'.'.$conv->fbclid;
            }

            if (! empty($conv->fbp)) {
                $userData['fbp'] = $conv->fbp;
            }

            // Meta weights IP and user agent heavily for match quality.
            if (! empty($conv->userData['client_ip'])) {
                $userData['client_ip_address'] = $conv->userData['client_ip'];
            }
            if (! empty($conv->userData['client_user_agent'])) {
                $userData['client_user_agent'] = $conv->userData['client_user_agent'];
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

            if ($conv->eventSourceUrl) {
                $event['event_source_url'] = $conv->eventSourceUrl;
            }

            if ($conv->orderId) {
                $event['event_id'] = (string) $conv->orderId;
            }

            $events[] = $event;
        }

        $body = ['data' => $events];

        if (! empty($this->config['test_event_code'])) {
            $body['test_event_code'] = $this->config['test_event_code'];
        }

        $response = $this->http->postJson(
            "https://graph.facebook.com/{$apiVersion}/{$this->config['pixel_id']}/events",
            $body,
            ['Authorization: Bearer '.$this->config['access_token']],
        );

        return [
            'success' => $response['ok'],
            'count' => $response['ok'] ? count($events) : 0,
            'errors' => $response['ok'] ? [] : [$response['error'] ?? 'Upload failed.'],
            'channel' => 'meta',
            'response' => $response['body'],
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Meta is not configured.'];
        }

        $apiVersion = $this->config['api_version'] ?? self::DEFAULT_VERSION;

        $response = $this->http->get(
            "https://graph.facebook.com/{$apiVersion}/{$this->config['pixel_id']}",
            ['Authorization: Bearer '.$this->config['access_token']],
        );

        if (! $response['ok']) {
            return ['success' => false, 'message' => 'Meta rejected the request: '.($response['error'] ?? 'unknown error')];
        }

        $name = $response['body']['name'] ?? $this->config['pixel_id'];

        return ['success' => true, 'message' => "Meta authenticated for pixel {$name}"];
    }
}
