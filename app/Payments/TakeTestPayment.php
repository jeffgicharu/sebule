<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\MarkOrderPaid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Completes an order without money changing hands, where the store allows it.
 */
class TakeTestPayment
{
    public function __construct(private readonly MarkOrderPaid $markOrderPaid) {}

    public function __invoke(Order $order): Payment
    {
        return DB::transaction(function () use ($order): Payment {
            $payment = $order->payments()->create([
                'method' => PaymentMethod::Test,
                'state' => PaymentState::Paid,
                'amount' => $order->total,
                'charged' => 0,
                'receipt' => 'TEST'.Str::upper(Str::random(8)),
                'settled_at' => now(),
            ]);

            ($this->markOrderPaid)($order, $payment);

            return $payment;
        });
    }
}
