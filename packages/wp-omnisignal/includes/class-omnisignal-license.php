<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Licence validation against omnisignal.dev.
 *
 * The admin screen previously treated any non-empty string as a valid Pro
 * licence — typing a single character showed "Pro License Active" — so the
 * licensing was entirely unenforced. This calls the real API, activates the
 * site's domain against the key, and caches the verdict so a network blip
 * cannot lock a paying customer out of their own plugin.
 */
class OmniSignal_License
{
    private const API_BASE = 'https://omnisignal.dev/api/v1/licenses';

    private const STATE_OPTION = 'omnisignal_license_state';

    /** How long a verdict is trusted before we re-check. */
    private const CACHE_TTL = DAY_IN_SECONDS;

    /**
     * How long a previously valid licence keeps working while the API is
     * unreachable. A customer who has paid should not lose functionality
     * because our server is down.
     */
    private const GRACE_PERIOD = 14 * DAY_IN_SECONDS;

    public static function init(): void
    {
        add_action('omnisignal_refresh_license', [__CLASS__, 'refresh']);
        add_action('update_option_omnisignal_settings', [__CLASS__, 'on_settings_saved'], 10, 2);

        if (! wp_next_scheduled('omnisignal_refresh_license')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'omnisignal_refresh_license');
        }
    }

    public static function key(): string
    {
        $options = get_option('omnisignal_settings', []);

        return trim((string) ($options['license_key'] ?? ''));
    }

    public static function domain(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        return strtolower(preg_replace('/^www\./', '', (string) $host));
    }

    /**
     * The cached licence state.
     *
     * @return array{status: string, tier: string, message: string, checked_at: int}
     */
    public static function state(): array
    {
        $default = [
            'status' => 'unlicensed',
            'tier' => '',
            'message' => 'No licence key entered.',
            'hosted_channels' => [],
            'ingest_token' => '',
            'ingest_url' => '',
            'checked_at' => 0,
        ];

        if (self::key() === '') {
            return $default;
        }

        $state = get_option(self::STATE_OPTION, []);

        if (! is_array($state) || empty($state['checked_at'])) {
            return self::refresh();
        }

        if ((time() - (int) $state['checked_at']) > self::CACHE_TTL) {
            return self::refresh();
        }

        return wp_parse_args($state, $default);
    }

    public static function is_valid(): bool
    {
        $state = self::state();

        if ($state['status'] === 'valid') {
            return true;
        }

        // Keep a previously valid licence working while we cannot reach the API.
        if ($state['status'] === 'unreachable') {
            $lastValid = (int) get_option('omnisignal_license_last_valid', 0);

            return $lastValid > 0 && (time() - $lastValid) < self::GRACE_PERIOD;
        }

        return false;
    }

    public static function tier(): string
    {
        return self::is_valid() ? (string) self::state()['tier'] : '';
    }

    /**
     * Channels omnisignal.dev delivers on this customer's behalf.
     *
     * Google Ads is here rather than in the plugin because it needs an OAuth
     * client secret and a developer token, and a GPL plugin's source is public.
     *
     * @return array<int, string>
     */
    public static function hosted_channels(): array
    {
        if (! self::is_valid()) {
            return [];
        }

        $channels = self::state()['hosted_channels'] ?? [];

        return is_array($channels) ? $channels : [];
    }

    /**
     * The per-domain credential this site posts conversions with.
     */
    public static function ingest_token(): ?string
    {
        $token = self::state()['ingest_token'] ?? '';

        return $token !== '' ? (string) $token : null;
    }

    public static function ingest_url(): string
    {
        $url = self::state()['ingest_url'] ?? '';

        return $url !== '' ? (string) $url : 'https://omnisignal.dev/api/v1/conversions';
    }

    /**
     * Re-check the key with the API and activate this domain.
     *
     * @return array{status: string, tier: string, message: string, checked_at: int}
     */
    public static function refresh(): array
    {
        $key = self::key();

        if ($key === '') {
            delete_option(self::STATE_OPTION);

            return [
                'status' => 'unlicensed',
                'tier' => '',
                'message' => 'No licence key entered.',
                'hosted_channels' => [],
                'ingest_token' => '',
                'ingest_url' => '',
                'checked_at' => time(),
            ];
        }

        $state = self::request('activate', ['license_key' => $key, 'domain' => self::domain()]);

        update_option(self::STATE_OPTION, $state, false);

        if ($state['status'] === 'valid') {
            update_option('omnisignal_license_last_valid', time(), false);
        }

        return $state;
    }

    public static function on_settings_saved($old, $new): void
    {
        $oldKey = trim((string) ($old['license_key'] ?? ''));
        $newKey = trim((string) ($new['license_key'] ?? ''));

        if ($oldKey !== $newKey) {
            delete_option(self::STATE_OPTION);
            self::refresh();
        }
    }

    /**
     * The last known-good state, so a temporary outage does not wipe the
     * channel list and token a working site is already using.
     *
     * @return array<string, mixed>
     */
    private static function previous(): array
    {
        $state = get_option(self::STATE_OPTION, []);

        return is_array($state) ? $state : [];
    }

    /**
     * @param  array<string, string>  $body
     * @return array<string, mixed>
     */
    private static function request(string $endpoint, array $body): array
    {
        $response = wp_remote_post(self::API_BASE.'/'.$endpoint, [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => wp_json_encode($body),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return array_merge(self::previous(), [
                'status' => 'unreachable',
                'message' => 'Could not reach omnisignal.dev: '.$response->get_error_message(),
                'checked_at' => time(),
            ]);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        $data = is_array($data) ? $data : [];

        if ($code >= 500 || $code === 429) {
            return array_merge(self::previous(), [
                'status' => 'unreachable',
                'message' => 'omnisignal.dev is temporarily unavailable (HTTP '.$code.').',
                'checked_at' => time(),
            ]);
        }

        if ($code === 200 && ! empty($data['activated'])) {
            return [
                'status' => 'valid',
                'tier' => (string) ($data['tier'] ?? 'pro'),
                'message' => sprintf(
                    'Active on %s (%d of %d domains used).',
                    self::domain(),
                    (int) ($data['activation_count'] ?? 1),
                    (int) ($data['activation_limit'] ?? 1)
                ),

                // Channels omnisignal.dev uploads for this customer, and the
                // credential this site uses to hand them over. The token is
                // scoped to this domain, so it cannot be used to reach the
                // customer's licence key or their other sites.
                'hosted_channels' => array_values((array) ($data['hosted_channels'] ?? [])),
                'ingest_token' => (string) ($data['ingest_token'] ?? ''),
                'ingest_url' => (string) ($data['ingest_url'] ?? self::API_BASE.'/../conversions'),

                'checked_at' => time(),
            ];
        }

        return [
            'status' => 'invalid',
            'tier' => '',
            'message' => (string) ($data['message'] ?? 'This licence key was not accepted.'),
            'hosted_channels' => [],
            'ingest_token' => '',
            'ingest_url' => '',
            'checked_at' => time(),
        ];
    }
}
