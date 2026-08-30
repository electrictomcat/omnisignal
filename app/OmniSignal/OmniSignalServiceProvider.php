<?php

namespace App\OmniSignal;

use App\OmniSignal\Commands\InstallCommand;
use App\OmniSignal\Commands\SyncConversionsCommand;
use App\OmniSignal\Commands\TestConnectionCommand;
use App\OmniSignal\Commands\UploadConversionsCommand;
use App\OmniSignal\Http\Controllers\DashboardController;
use App\OmniSignal\Http\Middleware\CaptureGclid;
use App\OmniSignal\Support\ConsentManager;
use App\OmniSignal\Support\EventResolver;
use App\OmniSignal\Support\UserDataHasher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OmniSignalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('omnisignal.php'), 'omnisignal');
        $this->mergeConfigFrom(config_path('google-ads-conversions.php'), 'google-ads-conversions');

        $this->app->singleton(EventResolver::class);
        $this->app->singleton(ConsentManager::class);
        $this->app->singleton(UserDataHasher::class);

        $this->app->singleton(ConversionManager::class, function ($app) {
            return new ConversionManager($app);
        });

        $this->app->singleton(GoogleAdsConversions::class, function ($app) {
            return new GoogleAdsConversions(
                $app->make(EventResolver::class),
                $app->make(UserDataHasher::class),
            );
        });

        $this->app->singleton(ConversionUploader::class, function ($app) {
            return new ConversionUploader(
                $app->make(EventResolver::class),
                $app->make(ConsentManager::class),
                $app->make(UserDataHasher::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                UploadConversionsCommand::class,
                SyncConversionsCommand::class,
                TestConnectionCommand::class,
            ]);
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('capture-gclid', CaptureGclid::class);

        // Register Dashboard Route if enabled
        if (config('omnisignal.dashboard.enabled', true)) {
            $path = config('omnisignal.dashboard.path', 'ad-conversions');
            $middleware = config('omnisignal.dashboard.middleware', ['web']);

            Route::middleware($middleware)->group(function () use ($path) {
                Route::get($path, DashboardController::class)->name('ad-conversions.dashboard');
                Route::get('dashboard', DashboardController::class)->name('omnisignal.dashboard');
            });
        }

        // Register Blade Directives for Form Inputs
        Blade::directive('googleAdsClickInputs', function () {
            return '<?php
                if ($gclid = \App\OmniSignal\Facades\GoogleAdsConversions::gclid()) {
                    echo \'<input type="hidden" name="gclid" value="\'.e($gclid).\'">\';
                }
                if ($gbraid = \App\OmniSignal\Facades\GoogleAdsConversions::gbraid()) {
                    echo \'<input type="hidden" name="gbraid" value="\'.e($gbraid).\'">\';
                }
                if ($wbraid = \App\OmniSignal\Facades\GoogleAdsConversions::wbraid()) {
                    echo \'<input type="hidden" name="wbraid" value="\'.e($wbraid).\'">\';
                }
            ?>';
        });

        Blade::directive('googleAdsGclid', function () {
            return '<?php
                if ($gclid = \App\OmniSignal\Facades\GoogleAdsConversions::gclid()) {
                    echo \'<input type="hidden" name="gclid" value="\'.e($gclid).\'">\';
                }
            ?>';
        });
    }
}
