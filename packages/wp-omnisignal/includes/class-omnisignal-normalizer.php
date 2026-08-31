<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Identifier normalisation shared by every channel.
 *
 * Ad platforms all expect the same thing before SHA-256: a lowercased,
 * trimmed email with Gmail dots and +suffixes collapsed, and a phone number in
 * full E.164. A hash of a wrongly normalised value is well-formed and matches
 * nobody, which is indistinguishable from a working integration — so a number
 * that cannot be resolved to E.164 is dropped rather than guessed at.
 */
class OmniSignal_Normalizer
{
    private const DOT_INSENSITIVE_DOMAINS = ['gmail.com', 'googlemail.com'];

    public static function hash_email(?string $email): ?string
    {
        $normalized = self::email($email);

        return $normalized ? hash('sha256', $normalized) : null;
    }

    public static function email(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $normalized = strtolower(trim($email));

        if (! is_email($normalized)) {
            return null;
        }

        [$local, $domain] = explode('@', $normalized, 2);

        if (in_array($domain, self::DOT_INSENSITIVE_DOMAINS, true)) {
            $local = str_replace('.', '', explode('+', $local, 2)[0]);

            if ($local === '') {
                return null;
            }
        }

        return $local.'@'.$domain;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function hash_phone(?string $phone, array $options = []): ?string
    {
        $normalized = self::phone($phone, $options);

        return $normalized ? hash('sha256', $normalized) : null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function phone(?string $phone, array $options = []): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $trimmed = trim($phone);
        $had_plus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($had_plus) {
            return self::valid_e164('+'.$digits);
        }

        $code = ltrim(trim((string) ($options['default_calling_code'] ?? '')), '+');

        // Fall back to the store's country when the admin has not set one.
        if ($code === '' && function_exists('WC')) {
            $code = self::calling_code_for_country(WC()->countries ? WC()->countries->get_base_country() : '');
        }

        if ($code === '') {
            return null;
        }

        if (str_starts_with($digits, $code) && strlen($digits) > strlen($code) + 6) {
            return self::valid_e164('+'.$digits);
        }

        $digits = ltrim($digits, '0');

        return $digits === '' ? null : self::valid_e164('+'.$code.$digits);
    }

    /**
     * Calling codes for the countries a WooCommerce store is most likely in.
     * Unknown countries return '' so the number is dropped, not mis-hashed.
     */
    private static function calling_code_for_country(string $country): string
    {
        $codes = [
            'US' => '1', 'CA' => '1', 'GB' => '44', 'IE' => '353', 'AU' => '61',
            'NZ' => '64', 'DE' => '49', 'FR' => '33', 'ES' => '34', 'IT' => '39',
            'NL' => '31', 'BE' => '32', 'AT' => '43', 'CH' => '41', 'SE' => '46',
            'NO' => '47', 'DK' => '45', 'FI' => '358', 'PL' => '48', 'PT' => '351',
            'ZA' => '27', 'IN' => '91', 'SG' => '65', 'JP' => '81', 'BR' => '55',
            'MX' => '52',
        ];

        return $codes[strtoupper($country)] ?? '';
    }

    private static function valid_e164(string $candidate): ?string
    {
        return preg_match('/^\+[1-9]\d{6,14}$/', $candidate) === 1 ? $candidate : null;
    }
}
