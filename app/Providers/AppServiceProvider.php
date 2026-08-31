<?php

namespace App\Providers;

use App\Listeners\ReportConversionFailure;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionUploadFailed;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();

        // A conversion the engine could not deliver has to reach a human.
        Event::listen(ConversionUploadFailed::class, ReportConversionFailure::class);
    }

    /**
     * Rate limits for the licence API.
     *
     * Validation is called by every install on a schedule, so it gets more
     * room than the write endpoints. Both are additionally keyed on the
     * submitted licence key so one abusive IP cannot exhaust the budget for a
     * legitimate customer behind the same NAT, and so key-guessing is capped
     * regardless of how many IPs it comes from.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('licenses-validate', function (Request $request) {
            return [
                Limit::perMinute(30)->by('ip:'.$request->ip()),
                Limit::perMinute(10)->by('key:'.sha1((string) $request->input('license_key'))),
            ];
        });

        RateLimiter::for('licenses-write', function (Request $request) {
            return [
                Limit::perMinute(10)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('key:'.sha1((string) $request->input('license_key'))),
            ];
        });
    }
}
