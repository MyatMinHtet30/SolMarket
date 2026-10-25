<?php

// Historical demo routes only. This file is NOT included by the application.
// Presentation guards also block the retained controller and RPC services.
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:90,1')->group(function () {
    Route::post('/products/{product}/demo-orders', [\App\Http\Controllers\DemoPaymentController::class, 'store'])->name('demo-orders.store');
    Route::get('/demo-orders/{demoOrder}', [\App\Http\Controllers\DemoPaymentController::class, 'show'])->name('demo-orders.show');
    Route::post('/demo-orders/{demoOrder}/submit', [\App\Http\Controllers\DemoPaymentController::class, 'submit'])->name('demo-orders.submit');
    Route::post('/demo-orders/{demoOrder}/verify', [\App\Http\Controllers\DemoPaymentController::class, 'verify'])->name('demo-orders.verify');
    Route::post('/demo-orders/{demoOrder}/cancel', [\App\Http\Controllers\DemoPaymentController::class, 'cancel'])->name('demo-orders.cancel');
});
