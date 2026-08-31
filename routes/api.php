<?php

use App\Http\Controllers\Api\ConversionIngestController;
use App\Http\Controllers\Api\LicenseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Licence API
|--------------------------------------------------------------------------
|
| Throttled per IP. Without a limit these endpoints let anyone brute-force
| licence keys at whatever rate the server would answer. Activation and
| deactivation are held tighter than validation, which every install calls on
| a schedule.
|
*/

Route::prefix('v1/licenses')->group(function () {
    Route::post('/validate', [LicenseController::class, 'validateKey'])
        ->middleware('throttle:licenses-validate')
        ->name('api.licenses.validate');

    Route::post('/activate', [LicenseController::class, 'activate'])
        ->middleware('throttle:licenses-write')
        ->name('api.licenses.activate');

    Route::post('/deactivate', [LicenseController::class, 'deactivate'])
        ->middleware('throttle:licenses-write')
        ->name('api.licenses.deactivate');
});

/*
|--------------------------------------------------------------------------
| Hosted conversion ingest
|--------------------------------------------------------------------------
|
| Sites post here for channels whose credentials cannot live on their own
| server. Authentication is a per-domain token derived from the licence key,
| so a compromised site cannot leak the key or touch the customer's other
| domains.
|
*/

Route::prefix('v1/conversions')->group(function () {
    Route::post('/', ConversionIngestController::class)
        ->middleware('throttle:conversions-ingest')
        ->name('api.conversions.ingest');

    Route::post('/channels', [ConversionIngestController::class, 'channels'])
        ->middleware('throttle:conversions-ingest')
        ->name('api.conversions.channels');
});
