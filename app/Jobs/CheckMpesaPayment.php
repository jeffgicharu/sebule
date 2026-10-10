<?php

namespace App\Jobs;

use App\Enums\OrderEventType;
use App\Enums\PaymentState;
use App\Models\Payment;
use App\Payments\Mpesa\Daraja;
use App\Payments\RecordMpesaResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Asks M-Pesa what became of a prompt, so an order is settled one way or the
 * other even when Safaricom's confirmation never reaches the store.
 */
class CheckMpesaPayment implements ShouldQueue
{
    use Queueable;

    /** How many times to ask before deciding nobody answered the prompt. */
    public const ATTEMPTS = 10;

    public function __construct(public Payment $payment, public int $attempt = 1) {}

    public function handle(Daraja $daraja, RecordMpesaResult $record): void
    {
        $payment = $this->payment->fresh();

        if ($payment === null || ! $payment->isPending() || $payment->checkout_request_id === null) {
            return;
        }

        try {
            $result = $daraja->result($payment->checkout_request_id);
        } catch (Throwable $problem) {
            report($problem);
            $result = null;
        }

        $payment->update(['checked_at' => now()]);

        if ($result !== null) {
            $record($payment, $result['code'], $result['description']);

            return;
        }

        if ($this->attempt < self::ATTEMPTS) {
            self::dispatch($payment, $this->attempt + 1)->delay(now()->addSeconds(15));

            return;
        }

        // Nothing heard after several minutes. If M-Pesa does report later, its word replaces this.
        $payment->update(['state' => PaymentState::TimedOut, 'settled_at' => now()]);
        $payment->order->record(OrderEventType::PaymentFailed, 'No answer from M-Pesa');
    }
}
