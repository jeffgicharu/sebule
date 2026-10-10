<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentState;
use App\Jobs\CheckMpesaPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\CloseUnpaidOrder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs every minute. Stock is held for an order only while its customer is
 * paying; when the time is up and nothing was paid, the pieces go back on sale.
 */
#[Signature('orders:release-unpaid')]
#[Description('Return the stock of orders that were not paid in time, and chase prompts with no answer')]
class ReleaseUnpaidOrders extends Command
{
    /** An order is left alone while a prompt this recent is still on its customer's phone. */
    private const PROMPT_GRACE_MINUTES = 3;

    public function handle(CloseUnpaidOrder $close): int
    {
        // A prompt whose check never ran, say because the worker was restarting, is checked now.
        Payment::query()
            ->where('state', PaymentState::Pending)
            ->whereNotNull('checkout_request_id')
            ->where('created_at', '<', now()->subMinutes(2))
            ->where(fn ($query) => $query->whereNull('checked_at')->orWhere('checked_at', '<', now()->subMinutes(2)))
            ->each(fn (Payment $payment) => CheckMpesaPayment::dispatch($payment, CheckMpesaPayment::ATTEMPTS));

        $released = Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('held_until', '<', now())
            ->whereDoesntHave('payments', fn ($payments) => $payments
                ->where('state', PaymentState::Pending)
                ->where('created_at', '>', now()->subMinutes(self::PROMPT_GRACE_MINUTES)))
            ->get()
            ->filter(fn (Order $order) => $close($order))
            ->count();

        $this->info("{$released} unpaid ".($released === 1 ? 'order' : 'orders').' released.');

        return self::SUCCESS;
    }
}
