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

/*
|--------------------------------------------------------------------------
| Self-service licence portal
|--------------------------------------------------------------------------
|
| Access is proven by control of the purchase email. `lookup` only ever sends
| a link; `show` requires the signature on that link; `deactivate` acts on the
| session established by it. Both write routes are throttled so the form
| cannot be used to enumerate customers or spray mail.
|
*/
Route::get('/portal', [PortalController::class, 'index'])->name('portal');

Route::get('/portal/licences', [PortalController::class, 'show'])
    ->middleware('signed')
    ->name('portal.show');

Route::post('/portal/lookup', [PortalController::class, 'lookup'])
    ->middleware('throttle:5,1')
    ->name('portal.lookup');

Route::post('/portal/deactivate', [PortalController::class, 'deactivateDomain'])
    ->middleware('throttle:20,1')
    ->name('portal.deactivate');

Route::get('/account', function () {
    return redirect()->route('portal');
});

// Post-purchase landing page. Buyers used to be sent to the internal
// analytics dashboard here.
Route::get('/thanks', function () {
    return view('thanks');
})->name('checkout.thanks');

/*
|--------------------------------------------------------------------------
| Legal, refund & compliance pages
|--------------------------------------------------------------------------
*/
Route::get('/refunds', function () {
    return view('refunds');
})->name('refunds');

Route::get('/terms', function () {
    return view('terms');
})->name('terms');

Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');

// Lemon Squeezy checkout redirects / overlays
Route::get('/checkout/{tier?}', CheckoutController::class)
    ->middleware('throttle:30,1')
    ->name('checkout');

// Lemon Squeezy webhook listener (HMAC-verified in the controller)
Route::post('/webhooks/lemonsqueezy', WebhookController::class)->name('webhooks.lemonsqueezy');
