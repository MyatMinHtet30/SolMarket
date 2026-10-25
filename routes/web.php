<?php
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
Route::post('/language/{locale}', [\App\Http\Controllers\LocaleController::class, 'update'])->name('locale.update');
Route::get('/', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
Route::post('/products', [ProductController::class, 'store'])->middleware('throttle:20,1')->name('products.store');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

// Presentation MVP: no checkout, demo-order or transaction routes are registered.
