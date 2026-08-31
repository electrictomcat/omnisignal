<?php

namespace App\Support;

use App\Models\License;
use Illuminate\Support\Facades\Config;

/**
 * The credential a customer's site uses to post conversions to us.
 *
 * Derived rather than stored: an HMAC of the licence key and the domain, keyed
 * on the application key. That buys three things.
 *
 *  - The site never holds the licence key itself, so a compromised WordPress
 *    install does not leak the customer's key or let an attacker deactivate
 *    their other domains.
 *  - Revocation is already implemented: deactivating a domain stops the token
 *    verifying, because verification requires the domain to still be activated.
 *  - Rotation is already implemented: a new licence key invalidates every
 *    token derived from the old one.
 *
 * It also means there is no second table of secrets to leak.
 */
class IngestToken
{
    /**
     * Mint the token for one activated domain.
     */
    public static function for(License $license, string $domain): string
    {
        return hash_hmac(
            'sha256',
            $license->license_key.'|'.self::normalize($domain),
            self::key(),
        );
    }

    /**
     * Resolve a (domain, token) pair to the licence it belongs to.
     *
     * Returns null when the domain is not activated on any licence, the
     * licence is inactive, or the token does not verify.
     */
    public static function resolve(string $domain, string $token): ?License
    {
        $domain = self::normalize($domain);

        if ($domain === '' || $token === '') {
            return null;
        }

        // An HMAC cannot be reversed, so the domain narrows the candidates and
        // the token is then checked against each in constant time.
        $candidates = License::query()
            ->where('status', 'active')
            ->whereJsonContains('instances', $domain)
            ->get();

        foreach ($candidates as $license) {
            if (! $license->isActive()) {
                continue;
            }

            if (hash_equals(self::for($license, $domain), $token)) {
                return $license;
            }
        }

        return null;
    }

    /**
     * Match the normalisation the License model uses for activations, so a
     * token minted for "www.Example.com/shop" verifies for "example.com".
     */
    public static function normalize(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return '';
        }

        if (str_contains($domain, '://')) {
            $domain = (string) (parse_url($domain, PHP_URL_HOST) ?: $domain);
        }

        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];

        return preg_replace('/^www\./', '', $domain) ?? $domain;
    }

    protected static function key(): string
    {
        $key = (string) Config::get('app.key');

        // Laravel stores the key base64-encoded; use the raw bytes so the
        // token does not change if the encoding ever does.
        return str_starts_with($key, 'base64:')
            ? (string) base64_decode(substr($key, 7), true)
            : $key;
    }
}
