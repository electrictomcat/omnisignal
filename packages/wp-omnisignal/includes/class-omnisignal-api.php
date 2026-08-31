<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Dispatches conversions to the configured ad platforms.
 *
 * Two things changed here from earlier releases:
 *
 *  - Sends are queued, not made inline. Two 10-second API calls used to run
 *    inside the order hook, so a slow ad platform added up to 20 seconds to
 *    the customer's thank-you page.
 *  - Responses are inspected and failures are recorded. Every result was
 *    previously discarded, which made an expired token indistinguishable from
 *    a working integration.
 */
class OmniSignal_API
{
    private const DISPATCH_HOOK = 'omnisignal_dispatch_conversion';

    private const FAILURE_OPTION = 'omnisignal_recent_failures';

    private const MAX_FAILURES_KEPT = 20;

    public static function init(): void
    {
        add_action(self::DISPATCH_HOOK, [__CLASS__, 'dispatch'], 10, 1);
    }

    /**
     * Queue a conversion for delivery.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function send_conversion(array $payload): void
    {
        // Action Scheduler ships with WooCommerce and is the better queue when
        // it is present; otherwise fall back to WP-Cron.
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::DISPATCH_HOOK, [$payload], 'omnisignal');

            return;
        }

        if (! wp_schedule_single_event(time(), self::DISPATCH_HOOK, [$payload])) {
            // Scheduling refused (e.g. a duplicate within the same minute).
            // Sending inline is better than dropping the conversion.
            self::dispatch($payload);
        }
    }

    /**
     * Deliver one conversion to every configured channel.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function dispatch(array $payload): void
    {
        $options = get_option('omnisignal_settings', []);

        // Channels omnisignal.dev delivers on this site's behalf, because
        // their credentials cannot live on a customer's server. Sent as one
        // batched call rather than one per channel.
        $hosted = OmniSignal_License::hosted_channels();

        if ($hosted !== []) {
            $result = self::send_hosted($payload, $hosted);

            if (! $result['ok']) {
                self::record_failure('omnisignal', $payload, $result['message']);
            }
        }

        foreach (['meta', 'tiktok', 'microsoft', 'linkedin'] as $channel) {
            $method = 'send_'.$channel;

            if (! self::configured($channel, $options)) {
                continue;
            }

            try {
                $result = self::$method($payload, $options);
            } catch (Throwable $e) {
                $result = ['ok' => false, 'message' => $e->getMessage()];
            }

            if (! $result['ok']) {
                self::record_failure($channel, $payload, $result['message']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function configured(string $channel, array $options): bool
    {
        $required = [
            'meta' => ['meta_pixel_id', 'meta_access_token'],
            'tiktok' => ['tiktok_pixel_code', 'tiktok_access_token'],
            'microsoft' => ['microsoft_developer_token', 'microsoft_customer_id', 'microsoft_account_id', 'microsoft_access_token'],
            'linkedin' => ['linkedin_access_token', 'linkedin_conversion_rule_id'],
        ];

        foreach ($required[$channel] ?? [] as $key) {
            if (empty($options[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Recent delivery failures, newest first, for the admin screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function recent_failures(): array
    {
        $failures = get_option(self::FAILURE_OPTION, []);

        return is_array($failures) ? $failures : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function record_failure(string $channel, array $payload, string $message): void
    {
        $failures = self::recent_failures();

        array_unshift($failures, [
            'channel' => $channel,
            'event' => $payload['event_name'] ?? 'Conversion',
            'order_id' => (string) ($payload['order_id'] ?? ''),
            'message' => mb_substr($message, 0, 500),
            'at' => time(),
        ]);

        update_option(self::FAILURE_OPTION, array_slice($failures, 0, self::MAX_FAILURES_KEPT), false);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[OmniSignal] {$channel} delivery failed: {$message}");
        }
    }

    // ------------------------------------------------------------- hosted

    /**
     * Hand a conversion to omnisignal.dev for the channels it hosts.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $channels
     * @return array{ok: bool, message: string}
     */
    private static function send_hosted(array $payload, array $channels): array
    {
        $token = OmniSignal_License::ingest_token();

        if (! $token) {
            return ['ok' => false, 'message' => 'No ingest token. Re-save your licence key to reactivate this domain.'];
        }

        $event_id = ! empty($payload['order_id'])
            ? 'ORDER_'.$payload['order_id']
            : ($payload['event_name'] ?? 'Conversion').'_'.md5(wp_json_encode($payload));

        $conversion = array_filter([
            'event_name' => $payload['event_name'] ?? 'Purchase',
            // Stable, so a retry from either side de-duplicates rather than
            // recording the same order twice.
            'event_id' => $event_id,
            'value' => isset($payload['value']) ? (float) $payload['value'] : null,
            'currency' => $payload['currency'] ?? null,
            'timestamp' => time(),
            'gclid' => $payload['gclid'] ?? null,
            'gbraid' => $payload['gbraid'] ?? null,
            'wbraid' => $payload['wbraid'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $result = self::post(OmniSignal_License::ingest_url(), [
            'domain' => OmniSignal_License::domain(),
            'conversions' => [$conversion],
        ], ['Authorization' => 'Bearer '.$token]);

        if ($result['ok']) {
            return $result;
        }

        if ($result['status'] === 401) {
            return ['ok' => false, 'message' => 'omnisignal.dev rejected this site\'s token. Re-save your licence key to reactivate the domain.'];
        }

        return $result;
    }

    // ------------------------------------------------------------------ Meta

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return array{ok: bool, message: string}
     */
    private static function send_meta(array $payload, array $options): array
    {
        $pixel_id = $options['meta_pixel_id'];

        $user_data = [];

        if ($hashed = OmniSignal_Normalizer::hash_email($payload['email'] ?? null)) {
            $user_data['em'] = [$hashed];
        }
        if ($hashed = OmniSignal_Normalizer::hash_phone($payload['phone'] ?? null, $options)) {
            $user_data['ph'] = [$hashed];
        }
        if (! empty($payload['fbclid'])) {
            $user_data['fbc'] = 'fb.1.'.time().'.'.$payload['fbclid'];
        }
        if (! empty($_COOKIE['_fbp'])) {
            $user_data['fbp'] = sanitize_text_field(wp_unslash($_COOKIE['_fbp']));
        }
        foreach (['first_name' => 'fn', 'last_name' => 'ln', 'city' => 'ct', 'state' => 'st', 'postcode' => 'zp', 'country' => 'country'] as $field => $key) {
            if (! empty($payload[$field])) {
                $user_data[$key] = [hash('sha256', strtolower(trim((string) $payload[$field])))];
            }
        }
        if ($ip = self::client_ip()) {
            $user_data['client_ip_address'] = $ip;
        }
        if (! empty($_SERVER['HTTP_USER_AGENT'])) {
            $user_data['client_user_agent'] = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
        }

        $event = [
            'event_name' => $payload['event_name'] ?? 'Purchase',
            'event_time' => time(),
            'action_source' => 'website',
            'event_source_url' => home_url(),
            'user_data' => $user_data,
            'custom_data' => array_merge((array) ($payload['custom_data'] ?? []), [
                'value' => (float) ($payload['value'] ?? 0),
                'currency' => $payload['currency'] ?? 'USD',
                'order_id' => (string) ($payload['order_id'] ?? ''),
            ]),
        ];

        if (! empty($payload['order_id'])) {
            $event['event_id'] = 'ORDER_'.$payload['order_id'];
        }

        $body = ['data' => [$event]];

        if (! empty($options['meta_test_event_code'])) {
            $body['test_event_code'] = $options['meta_test_event_code'];
        }

        return self::post(
            "https://graph.facebook.com/v20.0/{$pixel_id}/events",
            $body,
            ['Authorization' => 'Bearer '.$options['meta_access_token']],
        );
    }

    // ---------------------------------------------------------------- TikTok

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return array{ok: bool, message: string}
     */
    private static function send_tiktok(array $payload, array $options): array
    {
        $pixel_code = $options['tiktok_pixel_code'];

        $user = [];

        if ($hashed = OmniSignal_Normalizer::hash_email($payload['email'] ?? null)) {
            $user['email'] = $hashed;
        }
        if ($hashed = OmniSignal_Normalizer::hash_phone($payload['phone'] ?? null, $options)) {
            $user['phone_number'] = $hashed;
        }
        if (! empty($payload['ttclid'])) {
            $user['ttclid'] = $payload['ttclid'];
        }
        if ($ip = self::client_ip()) {
            $user['ip'] = $ip;
        }
        if (! empty($_SERVER['HTTP_USER_AGENT'])) {
            $user['user_agent'] = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
        }

        $name = $payload['event_name'] ?? 'Purchase';

        $event = [
            'event' => $name === 'Purchase' ? 'CompletePayment' : $name,
            'event_time' => time(),
            'user' => $user,
            'page' => ['url' => home_url()],
            'properties' => [
                'value' => (float) ($payload['value'] ?? 0),
                'currency' => $payload['currency'] ?? 'USD',
            ],
        ];

        if (! empty($payload['order_id'])) {
            $event['event_id'] = 'ORDER_'.$payload['order_id'];
        }

        $result = self::post('https://business-api.tiktok.com/open_api/v1.3/event/track/', [
            'pixel_code' => $pixel_code,
            'event_source' => 'web',
            'event_source_id' => $pixel_code,
            'data' => [$event],
        ], ['Access-Token' => $options['tiktok_access_token']]);

        // TikTok answers HTTP 200 with a non-zero `code` on rejection, so the
        // status alone proves nothing.
        if ($result['ok'] && isset($result['body']['code']) && (int) $result['body']['code'] !== 0) {
            return ['ok' => false, 'message' => (string) ($result['body']['message'] ?? 'TikTok rejected the event.')];
        }

        return $result;
    }

    // ------------------------------------------------------------- Microsoft

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return array{ok: bool, message: string}
     */
    private static function send_microsoft(array $payload, array $options): array
    {
        if (empty($payload['msclkid'])) {
            return ['ok' => true, 'message' => 'No msclkid; nothing to attribute.'];
        }

        $entry = [
            'MicrosoftClickId' => $payload['msclkid'],
            'ConversionName' => $payload['event_name'] ?? 'Purchase',
            'ConversionTime' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        if (! empty($payload['value'])) {
            $entry['ConversionValue'] = (float) $payload['value'];
            $entry['ConversionCurrencyCode'] = $payload['currency'] ?? 'USD';
        }

        $result = self::post(
            'https://campaign.api.bingads.microsoft.com/CampaignManagement/v13/OfflineConversions/Apply',
            ['OfflineConversions' => [$entry]],
            [
                'Authorization' => 'Bearer '.$options['microsoft_access_token'],
                'DeveloperToken' => $options['microsoft_developer_token'],
                'CustomerId' => (string) $options['microsoft_customer_id'],
                'CustomerAccountId' => (string) $options['microsoft_account_id'],
            ],
        );

        if ($result['ok'] && ! empty($result['body']['PartialErrors'])) {
            $first = $result['body']['PartialErrors'][0];

            return ['ok' => false, 'message' => (string) ($first['Message'] ?? 'Microsoft rejected the conversion.')];
        }

        return $result;
    }

    // -------------------------------------------------------------- LinkedIn

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return array{ok: bool, message: string}
     */
    private static function send_linkedin(array $payload, array $options): array
    {
        $userIds = [];

        if ($hashed = OmniSignal_Normalizer::hash_email($payload['email'] ?? null)) {
            $userIds[] = ['idType' => 'SHA256_EMAIL', 'idValue' => $hashed];
        }
        if (! empty($payload['li_fat_id'])) {
            $userIds[] = ['idType' => 'LINKEDIN_FIRST_PARTY_ADS_TRACKING_UUID', 'idValue' => $payload['li_fat_id']];
        }

        if ($userIds === []) {
            return ['ok' => true, 'message' => 'No LinkedIn identifier; nothing to attribute.'];
        }

        $body = [
            'conversion' => 'urn:lla:llaPartnerConversion:'.$options['linkedin_conversion_rule_id'],
            'conversionHappenedAt' => time() * 1000,
            'user' => ['userIds' => $userIds],
        ];

        if (! empty($payload['value'])) {
            // conversionValue, not totalBudget; amount is a string.
            $body['conversionValue'] = [
                'currencyCode' => $payload['currency'] ?? 'USD',
                'amount' => number_format((float) $payload['value'], 2, '.', ''),
            ];
        }

        $result = self::post('https://api.linkedin.com/rest/conversionEvents', $body, [
            'Authorization' => 'Bearer '.$options['linkedin_access_token'],
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => '202608',
        ]);

        if (! $result['ok'] && $result['status'] === 426) {
            return ['ok' => false, 'message' => 'The LinkedIn API version this plugin targets has been retired. Please update OmniSignal.'];
        }

        return $result;
    }

    // ------------------------------------------------------------- transport

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return array{ok: bool, status: int, body: array<string, mixed>, message: string}
     */
    private static function post(string $url, array $body, array $headers): array
    {
        $response = wp_remote_post($url, [
            'headers' => array_merge($headers, ['Content-Type' => 'application/json']),
            'body' => wp_json_encode($body),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'status' => 0, 'body' => [], 'message' => $response->get_error_message()];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($status < 200 || $status >= 300) {
            $message = $decoded['error']['message']
                ?? $decoded['message']
                ?? wp_remote_retrieve_body($response);

            return ['ok' => false, 'status' => $status, 'body' => $decoded, 'message' => "HTTP {$status}: ".mb_substr((string) $message, 0, 300)];
        }

        return ['ok' => true, 'status' => $status, 'body' => $decoded, 'message' => ''];
    }

    /**
     * The visitor's IP, looking through a proxy when the site sits behind one.
     */
    private static function client_ip(): ?string
    {
        // Only trusted when the site opts in — a forwarded header is
        // attacker-controlled on a server that is not behind a proxy.
        if (apply_filters('omnisignal_trust_proxy_headers', false)) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $header) {
                if (empty($_SERVER[$header])) {
                    continue;
                }

                $candidate = trim(explode(',', sanitize_text_field(wp_unslash($_SERVER[$header])))[0]);

                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        $remote = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : null;
    }
}
