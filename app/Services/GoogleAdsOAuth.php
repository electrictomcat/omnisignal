<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The Google Ads OAuth exchange, and the two read calls the connect flow needs.
 *
 * This lives on omnisignal.dev rather than in the WordPress plugin because the
 * exchange needs a client secret. A GPL plugin's source is public, so shipping
 * one would publish it; and the Google Ads developer token is issued against
 * our manager account, not each customer's. Uploads are therefore made here on
 * the customer's behalf, scoped to their account with login-customer-id.
 */
class GoogleAdsOAuth
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const SCOPE = 'https://www.googleapis.com/auth/adwords';

    public function isConfigured(): bool
    {
        return ! empty(config('services.google_ads.client_id'))
            && ! empty(config('services.google_ads.client_secret'))
            && ! empty(config('services.google_ads.developer_token'));
    }

    /**
     * Where to send the customer to grant access.
     */
    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google_ads.client_id'),
            'redirect_uri' => route('portal.connect.google.callback'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            // offline + consent so Google returns a refresh token even when the
            // customer has authorised this app before.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Trade the authorization code for a refresh token.
     *
     * @return array{ok: bool, refresh_token?: string, access_token?: string, error?: string}
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google_ads.client_id'),
            'client_secret' => config('services.google_ads.client_secret'),
            'redirect_uri' => route('portal.connect.google.callback'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            Log::warning('[GoogleAdsOAuth] Code exchange failed: '.$response->body());

            return ['ok' => false, 'error' => $this->errorFrom($response->json(), $response->body())];
        }

        $refreshToken = $response->json('refresh_token');

        if (! $refreshToken) {
            // Google omits it when the user has an existing grant and we did
            // not force the consent screen. prompt=consent should prevent this.
            return ['ok' => false, 'error' => 'Google did not return a refresh token. Revoke the app at myaccount.google.com and try again.'];
        }

        return [
            'ok' => true,
            'refresh_token' => (string) $refreshToken,
            'access_token' => (string) $response->json('access_token'),
        ];
    }

    /**
     * Mint a short-lived access token from a stored refresh token.
     */
    public function accessToken(string $refreshToken): ?string
    {
        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'client_id' => config('services.google_ads.client_id'),
            'client_secret' => config('services.google_ads.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        return $response->successful() ? $response->json('access_token') : null;
    }

    /**
     * Ad accounts this grant can reach.
     *
     * @return array{ok: bool, accounts?: array<int, array{id: string, name: string, manager: bool}>, error?: string}
     */
    public function accessibleAccounts(string $refreshToken): array
    {
        $token = $this->accessToken($refreshToken);

        if (! $token) {
            return ['ok' => false, 'error' => 'Google rejected the stored authorisation. Reconnect the account.'];
        }

        $version = config('services.google_ads.api_version', 'v23');

        $listed = Http::withToken($token)
            ->withHeaders(['developer-token' => config('services.google_ads.developer_token')])
            ->timeout(15)
            ->get("https://googleads.googleapis.com/{$version}/customers:listAccessibleCustomers");

        if (! $listed->successful()) {
            return ['ok' => false, 'error' => $this->errorFrom($listed->json(), $listed->body())];
        }

        $accounts = [];

        foreach ((array) $listed->json('resourceNames', []) as $resourceName) {
            $id = str_replace('customers/', '', (string) $resourceName);
            $accounts[] = ['id' => $id, 'name' => $this->describe($token, $version, $id), 'manager' => false];
        }

        return ['ok' => true, 'accounts' => $accounts];
    }

    /**
     * Conversion actions on an account, so the customer picks rather than types.
     *
     * @return array{ok: bool, actions?: array<int, array{resource_name: string, name: string}>, error?: string}
     */
    public function conversionActions(string $refreshToken, string $customerId): array
    {
        $token = $this->accessToken($refreshToken);

        if (! $token) {
            return ['ok' => false, 'error' => 'Google rejected the stored authorisation. Reconnect the account.'];
        }

        $version = config('services.google_ads.api_version', 'v23');

        $response = Http::withToken($token)
            ->withHeaders(['developer-token' => config('services.google_ads.developer_token')])
            ->timeout(20)
            ->post("https://googleads.googleapis.com/{$version}/customers/{$customerId}/googleAds:search", [
                'query' => 'SELECT conversion_action.resource_name, conversion_action.name '
                    .'FROM conversion_action '
                    .'WHERE conversion_action.status = "ENABLED" '
                    .'ORDER BY conversion_action.name',
            ]);

        if (! $response->successful()) {
            return ['ok' => false, 'error' => $this->errorFrom($response->json(), $response->body())];
        }

        $actions = [];

        foreach ((array) $response->json('results', []) as $row) {
            $action = $row['conversionAction'] ?? [];

            if (! empty($action['resourceName'])) {
                $actions[] = [
                    'resource_name' => (string) $action['resourceName'],
                    'name' => (string) ($action['name'] ?? $action['resourceName']),
                ];
            }
        }

        return ['ok' => true, 'actions' => $actions];
    }

    /**
     * Best-effort descriptive name; falls back to the bare ID.
     */
    protected function describe(string $token, string $version, string $customerId): string
    {
        try {
            $response = Http::withToken($token)
                ->withHeaders(['developer-token' => config('services.google_ads.developer_token')])
                ->timeout(10)
                ->post("https://googleads.googleapis.com/{$version}/customers/{$customerId}/googleAds:search", [
                    'query' => 'SELECT customer.descriptive_name FROM customer LIMIT 1',
                ]);

            $name = $response->json('results.0.customer.descriptiveName');

            return $name ? (string) $name : $customerId;
        } catch (\Throwable) {
            return $customerId;
        }
    }

    /**
     * @param  mixed  $decoded
     */
    protected function errorFrom($decoded, string $raw): string
    {
        if (is_array($decoded)) {
            $message = $decoded['error']['message']
                ?? $decoded['error_description']
                ?? (is_string($decoded['error'] ?? null) ? $decoded['error'] : null);

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return mb_substr($raw, 0, 300);
    }
}
