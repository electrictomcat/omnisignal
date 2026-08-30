<?php

namespace App\OmniSignal\Facades;

use DateTimeInterface;
use App\OmniSignal\Testing\GoogleAdsConversionsFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void record(string $eventName, ?float $value = null, ?string $currency = null, ?string $gclid = null, ?string $gbraid = null, ?string $wbraid = null, ?string $orderId = null, DateTimeInterface|int|string|null $conversionDateTime = null, array|bool|null $consent = null, array $userIdentifiers = [])
 * @method static string|null gclid()
 * @method static string|null gbraid()
 * @method static string|null wbraid()
 * @method static string|null clickId()
 * @method static void forgetGclid()
 * @method static void bufferLeadData(string $clickId, array $data)
 * @method static void syncToDatabase()
 * @method static int forgetVisitor(string $visitorId)
 * @method static void assertRecorded(string|\Closure $eventName, ?float $value = null)
 * @method static void assertNotRecorded(string|\Closure $eventName)
 * @method static void assertNothingRecorded()
 * @method static void assertRecordedCount(int $count)
 *
 * @see \App\OmniSignal\GoogleAdsConversions
 * @see GoogleAdsConversionsFake
 */
class GoogleAdsConversions extends Facade
{
    /**
     * Replace the bound instance with a fake for testing.
     */
    public static function fake(?string $fakeGclid = 'fake-gclid-12345'): GoogleAdsConversionsFake
    {
        $fake = new GoogleAdsConversionsFake($fakeGclid);

        static::swap($fake);
        app()->instance(\App\OmniSignal\GoogleAdsConversions::class, $fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \App\OmniSignal\GoogleAdsConversions::class;
    }
}
