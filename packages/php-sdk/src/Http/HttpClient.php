<?php

namespace OmniSignal\Http;

/**
 * Minimal cURL wrapper.
 *
 * The SDK is deliberately dependency-free, so this stands in for Guzzle. It
 * exists mainly so every driver reports transport failures the same way
 * instead of each inventing its own shape.
 */
class HttpClient
{
    public function __construct(protected int $timeout = 15) {}

    /**
     * @param  array<string, mixed>  $body
     * @param  array<int, string>  $headers
     * @return array{ok: bool, status: int, body: array<string, mixed>, error: string|null}
     */
    public function postJson(string $url, array $body, array $headers = []): array
    {
        return $this->send($url, 'POST', $headers, json_encode($body));
    }

    /**
     * @param  array<string, string>  $fields
     * @return array{ok: bool, status: int, body: array<string, mixed>, error: string|null}
     */
    public function postForm(string $url, array $fields): array
    {
        return $this->send(
            $url,
            'POST',
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query($fields),
        );
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{ok: bool, status: int, body: array<string, mixed>, error: string|null}
     */
    public function get(string $url, array $headers = []): array
    {
        return $this->send($url, 'GET', $headers, null);
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{ok: bool, status: int, body: array<string, mixed>, error: string|null}
     */
    protected function send(string $url, string $method, array $headers, ?string $payload): array
    {
        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_TIMEOUT => $this->timeout,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = $payload;

            if (! $this->hasContentType($headers)) {
                $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            }
        }

        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $transportError = curl_error($ch) ?: null;
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => [],
                'error' => $transportError ?? 'Request failed with no response.',
            ];
        }

        $decoded = json_decode((string) $raw, true);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : [],
            'error' => ($status >= 200 && $status < 300) ? null : $this->errorFrom($status, $decoded, (string) $raw),
        ];
    }

    /**
     * @param  array<int, string>  $headers
     */
    protected function hasContentType(array $headers): bool
    {
        foreach ($headers as $header) {
            if (stripos($header, 'content-type:') === 0) {
                return true;
            }
        }

        return false;
    }

    protected function errorFrom(int $status, mixed $decoded, string $raw): string
    {
        if (is_array($decoded)) {
            foreach ([['error', 'message'], ['message'], ['error_description'], ['error']] as $path) {
                $value = $decoded;

                foreach ($path as $key) {
                    if (! is_array($value) || ! array_key_exists($key, $value)) {
                        continue 2;
                    }
                    $value = $value[$key];
                }

                if (is_string($value) && $value !== '') {
                    return "HTTP {$status}: {$value}";
                }
            }
        }

        return "HTTP {$status}: ".substr($raw, 0, 500);
    }
}
