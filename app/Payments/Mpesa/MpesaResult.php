<?php

namespace App\Payments\Mpesa;

use App\Enums\PaymentState;

/**
 * What M-Pesa's result codes mean, for the record and in words for the customer.
 */
class MpesaResult
{
    public const PAID = '0';

    /** The codes the store knows to be M-Pesa's last word on a prompt. */
    private const LAST_WORD = [self::PAID, '1', '1001', '1019', '1025', '1032', '1037', '2001'];

    /**
     * Whether a code settles a prompt. M-Pesa also answers with codes that mean
     * the customer has still to respond, 4999 among them, and may add more.
     */
    public static function settles(string $code): bool
    {
        return in_array($code, self::LAST_WORD, true);
    }

    public static function state(string $code): PaymentState
    {
        return match ($code) {
            self::PAID => PaymentState::Paid,
            '1032' => PaymentState::Cancelled,
            '1037', '1019' => PaymentState::TimedOut,
            default => PaymentState::Failed,
        };
    }

    /** What to tell the customer, and what they can do next. Nothing was taken in any of these cases. */
    public static function message(?string $code): string
    {
        return match ($code) {
            '1032' => 'You cancelled the prompt on your phone, so nothing was paid. Send it again when you are ready.',
            '1037' => 'The prompt was not answered in time, or it could not reach your phone. Check that your phone is on and has signal, then send it again.',
            '1019' => 'The prompt ran out of time before it was answered. Send it again and enter your PIN when it appears.',
            '1' => 'There was not enough money in the M-Pesa account. Top up or use another number, then try again.',
            '2001' => 'The M-Pesa PIN was not right, so nothing was paid. Send the prompt again and take your time.',
            '1001' => 'Your phone already has an M-Pesa request open. Wait a minute for it to clear, then try again.',
            '1025', '9999' => 'M-Pesa could not send the prompt just now. Nothing was paid. Please try again in a moment.',
            null => 'We did not hear back from M-Pesa. If you entered your PIN, your payment will show here shortly; if not, send the prompt again.',
            default => 'M-Pesa could not complete the payment and nothing was taken. Please try again.',
        };
    }
}
