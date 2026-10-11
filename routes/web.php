<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\PasswordResetController;
use App\Http\Controllers\Account\RegisterController;
use App\Http\Controllers\Account\SessionController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CollectionController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\MpesaConfirmationController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SearchController;
use App\Http\Controllers\Shop\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/c/{category}', CategoryController::class)->name('shop.category');
Route::get('/collections/{collection}', CollectionController::class)->name('shop.collection');
Route::get('/p/{product}', ProductController::class)->name('shop.product');
Route::get('/search', SearchController::class)->middleware('throttle:search')->name('shop.search');
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

/*
 * Customer accounts. Nobody needs one to buy. Signing in and out use the
 * "customer" guard, which has nothing to do with the back office's.
 */
Route::prefix('account')->name('account.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:account-register')->name('register.store');
        Route::get('/sign-in', [SessionController::class, 'create'])->name('login');
        Route::post('/sign-in', [SessionController::class, 'store'])->middleware('throttle:account-login')->name('login.store');
        Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:account-reset')->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:account-new-password')->name('password.update');
    });

    Route::middleware('auth:customer')->group(function () {
        Route::get('/', [AccountController::class, 'show'])->name('show');
        Route::post('/sign-out', [SessionController::class, 'destroy'])->name('logout');

        Route::get('/details', [AccountController::class, 'details'])->name('details');
        Route::put('/details', [AccountController::class, 'update'])->name('details.update');
        Route::put('/password', [AccountController::class, 'updatePassword'])->name('password.change');

        Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
        Route::get('/addresses/new', [AddressController::class, 'create'])->name('addresses.create');
        Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
        Route::get('/addresses/{address}/edit', [AddressController::class, 'edit'])->name('addresses.edit');
        Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
        Route::post('/addresses/{address}/default', [AddressController::class, 'makeDefault'])->name('addresses.default');
    });
});

// The store's own pages, by their names: /delivery, /returns, /about. Last, so every address above is tried first.
Route::get('/{page}', PageController::class)->where('page', '(?!admin$)[a-z0-9-]+')->name('shop.page');
