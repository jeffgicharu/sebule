<?php

use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CollectionController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\MpesaConfirmationController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/c/{category}', CategoryController::class)->name('shop.category');
Route::get('/collections/{collection}', CollectionController::class)->name('shop.collection');
Route::get('/p/{product}', ProductController::class)->name('shop.product');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');

Route::middleware('throttle:cart')->group(function () {
    Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
});

Route::get('/checkout', [CheckoutController::class, 'details'])->name('checkout.details');
Route::post('/checkout/details', [CheckoutController::class, 'saveDetails'])->middleware('throttle:checkout')->name('checkout.details.save');
Route::get('/checkout/review', [CheckoutController::class, 'review'])->name('checkout.review');
Route::post('/checkout', [CheckoutController::class, 'place'])->middleware(['throttle:checkout', 'throttle:orders'])->name('checkout.place');

Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/{order}/pay', [OrderController::class, 'pay'])->name('orders.pay');
Route::get('/orders/{order}/payment', [OrderController::class, 'status'])->middleware('throttle:payment-status')->name('orders.payment');

// Payment prompts cost the customer attention and the store money, so they are rationed.
Route::middleware('throttle:payment-prompts')->group(function () {
    Route::post('/orders/{order}/pay/phone', [OrderController::class, 'mpesa'])->name('orders.pay.mpesa');
    Route::post('/orders/{order}/pay/test', [OrderController::class, 'test'])->name('orders.pay.test');
    Route::post('/orders/{order}/resume', [OrderController::class, 'resume'])->name('orders.resume');
});

// Safaricom's report on a prompt. It has no session and no CSRF token; the signature in the address stands in for both.
Route::post('/payments/confirmation/{payment}/{signature}', MpesaConfirmationController::class)
    ->whereNumber('payment')
    ->middleware('throttle:payment-confirmations')
    ->name('payments.mpesa.callback');
