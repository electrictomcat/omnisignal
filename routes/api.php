<?php

use App\Http\Controllers\Api\LicenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/licenses')->group(function () {
    Route::post('/validate', [LicenseController::class, 'validateKey'])->name('api.licenses.validate');
    Route::post('/activate', [LicenseController::class, 'activate'])->name('api.licenses.activate');
    Route::post('/deactivate', [LicenseController::class, 'deactivate'])->name('api.licenses.deactivate');
});
