<?php

namespace OmniSignal\Support;

/**
 * Identifier normalisation shared by every channel.
 *
 * All the ad platforms want the same thing before SHA-256: lowercase and
 * trimmed email with Gmail dots and +suffixes collapsed, and a phone number in
 * full E.164. A well-formed hash of a wrongly normalised value matches nobody
 * and looks exactly like success, so numbers that cannot be resolved to E.164
 * return null instead of being guessed at.
 */
class Normalizer
{
    /** @var array<int, string> */
    protected const DOT_INSENSITIVE_DOMAINS = ['gmail.com', 'googlemail.com'];

    public function __construct(protected ?string $defaultCallingCode = null) {}

    public function hashEmail(?string $email): ?string
    {
        $normalized = $this->email($email);

        return $normalized ? hash('sha256', $normalized) : null;
    }

    public function email(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = strtolower(trim($email));

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
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

    public function hashPhone(?string $phone): ?string
    {
        $normalized = $this->phone($phone);

        return $normalized ? hash('sha256', $normalized) : null;
    }

    public function phone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);
        $hadPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($hadPlus) {
            return $this->validE164('+'.$digits);
        }

        $code = ltrim(trim((string) $this->defaultCallingCode), '+');

        if ($code === '') {
            return null;
        }

        if (str_starts_with($digits, $code) && strlen($digits) > strlen($code) + 6) {
            return $this->validE164('+'.$digits);
        }

        $digits = ltrim($digits, '0');

        return $digits === '' ? null : $this->validE164('+'.$code.$digits);
    }

    protected function validE164(string $candidate): ?string
    {
        return preg_match('/^\+[1-9]\d{6,14}$/', $candidate) === 1 ? $candidate : null;
    }
}
