<?php

namespace App\Payments;

use App\Enums\OrderEventType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Jobs\CheckMpesaPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Mpesa\Daraja;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Sends an M-Pesa prompt for an order to a phone, and arranges for the store
 * to ask what became of it in case Safaricom's confirmation never arrives.
 */
class StartMpesaPayment
{
    /** A prompt stays on the phone for about a minute. While one is open, no second one is sent. */
    public const PROMPT_SECONDS = 75;

    public function __construct(private readonly Daraja $daraja) {}

    public function __invoke(Order $order, string $phone): Payment
    {
        $open = $order->payments()->where('state', PaymentState::Pending)->where('method', PaymentMethod::Mpesa)->first();

        if ($open && $open->created_at->diffInSeconds(now()) < self::PROMPT_SECONDS) {
            return $open;
        }

        // An older prompt nobody answered is closed before another is sent.
        $open?->update(['state' => PaymentState::TimedOut, 'settled_at' => now()]);

        $payment = $order->payments()->create([
            'method' => PaymentMethod::Mpesa,
            'state' => PaymentState::Pending,
            'amount' => $order->total,
            'charged' => self::charge($order),
            'phone' => $phone,
        ]);

        try {
            $answer = $this->daraja->prompt(
                phone: $phone,
                shillings: intdiv($payment->charged, 100),
                reference: $order->number,
                callbackUrl: self::callbackUrl($payment),
            );
        } catch (Throwable $problem) {
            report($problem);

            $payment->update([
                'state' => PaymentState::Failed,
                'result_code' => '9999',
                'result_description' => mb_substr($problem->getMessage(), 0, 250),
                'settled_at' => now(),
            ]);
            $order->record(OrderEventType::PaymentFailed, 'M-Pesa could not send the prompt');

            return $payment;
        }

        $payment->update([
            'merchant_request_id' => $answer['MerchantRequestID'] ?? null,
            'checkout_request_id' => $answer['CheckoutRequestID'],
            'response' => $answer,
        ]);

        // The pieces stay held while the customer is at the prompt, even if the hold was about to run out.
        $order->update(['held_until' => max($order->held_until, now()->addMinutes(3))]);

        CheckMpesaPayment::dispatch($payment)->delay(now()->addSeconds(20));

        return $payment;
    }

    /** What to ask the phone for, in cents: the order's total, unless the store is set to ask for a fixed sum. */
    public static function charge(Order $order): int
    {
        $fixed = config('services.mpesa.charge');

        return filled($fixed) ? (int) $fixed * 100 : $order->total;
    }

    /** Where Safaricom sends its confirmation. The address is signed, so only someone given it can use it. */
    public static function callbackUrl(Payment $payment): string
    {
        return URL::route('payments.mpesa.callback', [
            'payment' => $payment->getKey(),
            'signature' => self::signature($payment),
        ]);
    }

    public static function signature(Payment $payment): string
    {
        return hash_hmac('sha256', 'mpesa-callback|'.$payment->getKey(), (string) config('app.key'));
    }
}
