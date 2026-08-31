<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('/docs', function () {
    return view('docs');
})->name('docs');

Route::get('/kb', function () {
    return redirect()->route('docs');
});

// Self-Service Customer License & Account Portal
Route::get('/portal', [PortalController::class, 'index'])->name('portal');
Route::post('/portal/lookup', [PortalController::class, 'lookup'])->name('portal.lookup');
Route::post('/portal/deactivate', [PortalController::class, 'deactivateDomain'])->name('portal.deactivate');
Route::get('/account', function () {
    return redirect()->route('portal');
});

// Legal, Refund & Compliance Pages
Route::get('/refunds', function () {
    return view('refunds');
})->name('refunds');

Route::get('/terms', function () {
    return view('terms');
})->name('terms');

Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');

// LemonSqueezy Checkout Redirects / Overlays
Route::get('/checkout/{tier?}', CheckoutController::class)->name('checkout');

// LemonSqueezy Webhook Listener
Route::post('/webhooks/lemonsqueezy', WebhookController::class)->name('webhooks.lemonsqueezy');
