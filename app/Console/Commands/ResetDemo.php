<?php

namespace App\Console\Commands;

use App\Demo\DemoData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:reset')]
#[Description('Replace the orders, customers and stock with the demonstration set')]
class ResetDemo extends Command
{
    public function handle(DemoData $demo): int
    {
        // This deletes every order and customer. It runs only where the store is marked as a demonstration.
        if (! config('store.demo')) {
            $this->error('This store is not a demonstration (STORE_DEMO is off), so nothing was changed.');

            return self::FAILURE;
        }

        $made = $demo->reset();

        $this->info("The demonstration data is fresh: {$made['orders']} orders from {$made['customers']} customers, and {$made['messages']} messages.");

        return self::SUCCESS;
    }
}
