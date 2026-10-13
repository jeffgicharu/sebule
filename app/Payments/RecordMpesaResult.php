<?php

namespace App\Payments;

use App\Enums\OrderEventType;
use App\Enums\PaymentState;
use App\Messages\TellCustomer;
use App\Models\Payment;
use App\Orders\MarkOrderPaid;
use App\Payments\Mpesa\MpesaResult;
use Illuminate\Support\Facades\DB;

/**
 * Writes down what M-Pesa says became of a prompt, whether the news came from
 * Safaricom's confirmation or from the store asking. A result is recorded once:
 * the same news arriving again changes nothing, and only word that the money
 * arrived replaces a failure.
 */
class RecordMpesaResult
{
    public function __construct(private readonly MarkOrderPaid $markOrderPaid, private readonly TellCustomer $tell) {}

    /**
     * @param  int|null  $paidShillings  the amount M-Pesa says was paid, when it says
     * @param  array<string, mixed>  $response  what M-Pesa sent, kept as it came
     */
    public function __invoke(Payment $payment, string $code, string $description, ?string $receipt = null, ?int $paidShillings = null, array $response = []): Payment
    {
        return DB::transaction(function () use ($payment, $code, $description, $receipt, $paidShillings, $response): Payment {
            $payment = Payment::query()->lockForUpdate()->with('order')->findOrFail($payment->getKey());

            if ($payment->state === PaymentState::Paid) {
                // Already settled. A receipt number that arrives later is still worth keeping.
                if ($receipt !== null && $payment->receipt === null && $code === MpesaResult::PAID) {
                    $payment->update(['receipt' => $receipt]);
                }

                return $payment;
            }

            $state = MpesaResult::state($code);

            // The amount comes from the store's own record, never from the message: a paid result for a different sum is refused.
            if ($state === PaymentState::Paid && $paidShillings !== null && $paidShillings * 100 !== $payment->charged) {
                $state = PaymentState::Failed;
                $description = "Paid {$paidShillings} where {$payment->charged} cents was asked. ".$description;
            }

            // A result M-Pesa gave stands, except that money which did arrive is never lost to an earlier
            // word that it had not. One the store assumed, having heard nothing, gives way to any real news.
            if ($payment->state->isFinal() && $payment->result_code !== null && $state !== PaymentState::Paid) {
                return $payment;
            }

            $payment->update([
                'state' => $state,
                'result_code' => $code,
                'result_description' => mb_substr($description, 0, 250),
                'receipt' => $state === PaymentState::Paid ? $receipt : null,
                'response' => $response ?: $payment->response,
                'settled_at' => now(),
            ]);

            if ($state === PaymentState::Paid) {
                ($this->markOrderPaid)($payment->order, $payment);
            } else {
                // One message about a payment that failed is enough, however many times the customer tries.
                $firstFailure = ! $payment->order->events()->where('type', OrderEventType::PaymentFailed)->exists();

                $payment->order->record(OrderEventType::PaymentFailed, $state->getLabel());

                if ($firstFailure && $payment->order->awaitsPayment()) {
                    ($this->tell)($payment->order, OrderEventType::PaymentFailed);
                }
            }

            return $payment;
        });
    }
}
