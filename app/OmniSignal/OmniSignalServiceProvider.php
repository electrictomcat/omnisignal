<?php

namespace App\OmniSignal;

use App\OmniSignal\Commands\BuildPluginCommand;
use App\OmniSignal\Commands\TestEventCommand;
use Illuminate\Support\ServiceProvider;

/**
 * OmniSignal application services.
 *
 * The conversion engine — drivers, uploader, middleware, dashboard, Blade
 * directives and the google-ads:* commands — now comes from
 * electrictomcat/laravel-google-ads-conversions, which auto-registers its own
 * provider. This one only adds what is specific to omnisignal.dev.
 *
 * Until this change the app carried a copy-pasted fork of all 30 engine files.
 * The copy had already drifted from the package and did not have any of the
 * conversion-loss fixes, so every fix had to be made twice.
 */
class OmniSignalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestEventCommand::class,
                BuildPluginCommand::class,
            ]);
        }
    }
}
