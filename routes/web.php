<?php

use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CollectionController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/c/{category}', CategoryController::class)->name('shop.category');
Route::get('/collections/{collection}', CollectionController::class)->name('shop.collection');
Route::get('/p/{product}', ProductController::class)->name('shop.product');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
