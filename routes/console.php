<?php

use App\Models\Cart;
use Illuminate\Support\Facades\Schedule;

// Stock held for an order nobody paid for goes back on sale, and prompts with no answer are chased.
Schedule::command('orders:release-unpaid')->everyMinute()->withoutOverlapping(5);

// Carts nobody has touched for longer than their cookie lasts are of no use to anyone.
Schedule::command('model:prune', ['--model' => [Cart::class]])->daily();

// Products are indexed for search as they change. This catches what no single change announces,
// such as a product joining a collection.
Schedule::command('catalogue:index-search')->dailyAt('03:10');
