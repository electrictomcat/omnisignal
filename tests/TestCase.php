<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Signing secret used by webhook tests.
     *
     * The webhook handler rejects anything unsigned, so tests have to sign
     * their payloads exactly as Lemon Squeezy does.
     */
    protected string $lemonSqueezySecret = 'test-signing-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.lemonsqueezy.signing_secret', $this->lemonSqueezySecret);
    }

    /**
     * Build the HMAC header for a Lemon Squeezy webhook payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function lemonSqueezyHeaders(array $payload): array
    {
        return [
            'X-Signature' => hash_hmac('sha256', json_encode($payload), $this->lemonSqueezySecret),
        ];
    }

    /**
     * POST a correctly signed Lemon Squeezy webhook.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function postSignedWebhook(array $payload): TestResponse
    {
        return $this->withHeaders($this->lemonSqueezyHeaders($payload))
            ->postJson('/webhooks/lemonsqueezy', $payload);
    }
}
