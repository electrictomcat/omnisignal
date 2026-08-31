<?php

use App\Http\Controllers\CheckoutController;
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

// LemonSqueezy Checkout Redirects / Overlays
Route::get('/checkout/{tier?}', CheckoutController::class)->name('checkout');

// LemonSqueezy Webhook Listener
Route::post('/webhooks/lemonsqueezy', WebhookController::class)->name('webhooks.lemonsqueezy');
