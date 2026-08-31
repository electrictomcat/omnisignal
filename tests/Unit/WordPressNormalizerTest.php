<?php

namespace Tests\Unit;

use OmniSignal\Support\Normalizer;
use PHPUnit\Framework\TestCase;

/**
 * The WordPress plugin's identifier normalisation.
 *
 * This is the code that decides what actually reaches Meta and TikTok from a
 * customer's store, and it had no coverage at all. It is deliberately free of
 * WordPress dependencies so it can be tested here rather than only in a live
 * install — a wrong hash is well-formed and matches nobody, which looks exactly
 * like a working integration.
 */
class WordPressNormalizerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (! defined('ABSPATH')) {
            define('ABSPATH', __DIR__);
        }

        require_once __DIR__.'/../../packages/wp-omnisignal/includes/class-omnisignal-normalizer.php';
    }

    public function test_it_lowercases_and_trims_an_email(): void
    {
        $this->assertSame(
            hash('sha256', 'buyer@example.com'),
            \OmniSignal_Normalizer::hash_email('  Buyer@Example.COM '),
        );
    }

    public function test_it_collapses_gmail_dots_and_plus_suffixes(): void
    {
        $canonical = hash('sha256', 'testuser@gmail.com');

        $this->assertSame($canonical, \OmniSignal_Normalizer::hash_email('test.user@gmail.com'));
        $this->assertSame($canonical, \OmniSignal_Normalizer::hash_email('TestUser+promo@gmail.com'));
        $this->assertSame(
            hash('sha256', 'testuser@googlemail.com'),
            \OmniSignal_Normalizer::hash_email('t.e.s.t.u.s.e.r@googlemail.com'),
        );
    }

    public function test_dots_remain_significant_outside_gmail(): void
    {
        $this->assertNotSame(
            \OmniSignal_Normalizer::hash_email('test.user@example.com'),
            \OmniSignal_Normalizer::hash_email('testuser@example.com'),
        );
    }

    public function test_it_rejects_a_malformed_email(): void
    {
        $this->assertNull(\OmniSignal_Normalizer::hash_email('not an address'));
        $this->assertNull(\OmniSignal_Normalizer::hash_email(''));
        $this->assertNull(\OmniSignal_Normalizer::hash_email(null));
    }

    public function test_it_keeps_a_number_that_already_has_a_country_code(): void
    {
        $this->assertSame(
            hash('sha256', '+15551234567'),
            \OmniSignal_Normalizer::hash_phone('+1 (555) 123-4567'),
        );
        $this->assertSame(
            hash('sha256', '+442079460958'),
            \OmniSignal_Normalizer::hash_phone('+44 20 7946 0958'),
        );
    }

    public function test_it_applies_the_configured_calling_code(): void
    {
        $this->assertSame(
            hash('sha256', '+15551234567'),
            \OmniSignal_Normalizer::hash_phone('(555) 123-4567', ['default_calling_code' => '1']),
        );

        // A national trunk zero is dropped rather than embedded in the number.
        $this->assertSame(
            hash('sha256', '+442079460958'),
            \OmniSignal_Normalizer::hash_phone('020 7946 0958', ['default_calling_code' => '44']),
        );
    }

    public function test_it_does_not_add_a_second_country_code(): void
    {
        $this->assertSame(
            hash('sha256', '+15551234567'),
            \OmniSignal_Normalizer::hash_phone('15551234567', ['default_calling_code' => '1']),
        );
    }

    public function test_it_drops_a_number_it_cannot_resolve_rather_than_guessing(): void
    {
        // No country code and none configured: any hash produced here would
        // match nobody, and sending one is worse than sending nothing.
        $this->assertNull(\OmniSignal_Normalizer::hash_phone('(555) 123-4567'));
        $this->assertNull(\OmniSignal_Normalizer::hash_phone('12345'));
        $this->assertNull(\OmniSignal_Normalizer::hash_phone('not a phone number'));
        $this->assertNull(\OmniSignal_Normalizer::hash_phone(null));
    }

    public function test_it_matches_the_php_sdk_for_the_same_input(): void
    {
        // The plugin, the SDK and the Laravel package must agree, or the same
        // customer hashes differently depending on which one sent the event.
        $sdk = new Normalizer('1');

        foreach (['Test.User+promo@Gmail.com', 'buyer@example.com'] as $email) {
            $this->assertSame(
                $sdk->hashEmail($email),
                \OmniSignal_Normalizer::hash_email($email),
                "email normalisation diverged for {$email}",
            );
        }

        foreach (['+15551234567', '(555) 123-4567', '020 7946 0958'] as $phone) {
            $this->assertSame(
                $sdk->hashPhone($phone),
                \OmniSignal_Normalizer::hash_phone($phone, ['default_calling_code' => '1']),
                "phone normalisation diverged for {$phone}",
            );
        }
    }
}
