<?php

use App\Models\Cart;
use Illuminate\Support\Facades\Schedule;

// Stock held for an order nobody paid for goes back on sale, and prompts with no answer are chased.
Schedule::command('orders:release-unpaid')->everyMinute()->withoutOverlapping(5);

// Carts nobody has touched for longer than their cookie lasts are of no use to anyone.
Schedule::command('model:prune', ['--model' => [Cart::class]])->daily();
