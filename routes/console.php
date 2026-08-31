<?php

use App\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Jobs\UploadPendingConversions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Conversion pipeline
|--------------------------------------------------------------------------
|
| Without these two entries the engine is wired but never runs: buffered
| conversions are never flushed or uploaded, and retention never happens.
| The job is ShouldBeUnique, so an overlapping run is a no-op rather than a
| double upload.
|
*/

Schedule::job(new UploadPendingConversions)
    ->hourly()
    ->name('omnisignal:upload-conversions')
    ->onOneServer();

// GDPR retention. Leads still holding an undelivered conversion are held
// back automatically, so this cannot prune away work in progress.
Schedule::command('model:prune', ['--model' => [Lead::class]])
    ->daily()
    ->name('omnisignal:prune-leads')
    ->onOneServer();
