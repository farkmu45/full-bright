<?php

use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HeartbeatController;
use App\Http\Controllers\LabsController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PaymentCallbackController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $mode = (string) config('analytics.mode');
    $number = preg_replace('/\D+/', '', (string) config('analytics.whatsapp_number'));
    $whatsappUrl = $number ? 'https://wa.me/'.$number.'?text='.urlencode((string) config('analytics.whatsapp_default_message')) : '#pricing';

    return Inertia::render("demo/{$mode}", [
        'whatsappUrl' => $whatsappUrl,
        'whatsappNumber' => $number,
        'pricingMode' => request()->query('mode') === 'tutor' ? 'tutor' : 'self',
        'externalCheckoutUrl' => config('analytics.external_checkout_url'),
        'paymentMode' => config('analytics.payment_mode'),
        'productName' => config('analytics.product_name'),
        'productPrice' => config('analytics.product_price'),
    ]);
})->name('home');

Route::middleware('throttle:120,1')->group(function () {
    Route::post('/analytics/track', [AnalyticsController::class, 'track'])->name('analytics.track');
    Route::post('/analytics/heartbeat', HeartbeatController::class)->name('analytics.heartbeat');
});

if (config('analytics.mode') === 'form') {
    Route::post('/lead', [LeadController::class, 'store'])->middleware('throttle:20,1')->name('lead.store');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('checkout.store');
    Route::get('/payment/return', [CheckoutController::class, 'returnPage'])->name('payment.return');
    Route::post('/payment/callback', [PaymentCallbackController::class, 'handle'])->name('payment.callback');
    $thankYouPath = '/'.ltrim((string) config('analytics.thank_you_path'), '/');
    Route::inertia($thankYouPath, 'demo/thank-you')->name('thank-you');
}

Route::middleware('auth')->group(function () {
    Route::redirect('dashboard', '/admin')->name('dashboard');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/export', [AnalyticsController::class, 'export'])->name('analytics.export');
    Route::get('/labs', [LabsController::class, 'index'])->name('labs');
    Route::post('/labs/clear-cache', [LabsController::class, 'clearCache'])->name('labs.clear-cache');
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('/orders/{order}/mark-paid', [AdminOrderController::class, 'markAsPaid'])->name('orders.mark-paid');
});
