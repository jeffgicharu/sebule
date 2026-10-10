<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\RecordMpesaResult;
use App\Payments\StartMpesaPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where Safaricom reports what became of a prompt.
 *
 * The message is not signed by Safaricom, so it is trusted only as far as it
 * can be checked: the address must carry the signature given out with that one
 * prompt, the message must name that prompt, and the amount paid must be the
 * amount the store asked for. Whatever arrives, the answer is the same short
 * acknowledgement, and a message that arrives twice is recorded once.
 */
class MpesaConfirmationController extends Controller
{
    public function __invoke(Request $request, Payment $payment, string $signature, RecordMpesaResult $record): JsonResponse
    {
        abort_unless(hash_equals(StartMpesaPayment::signature($payment), $signature), 404);

        $result = $request->input('Body.stkCallback');

        if (is_array($result)
            && filled($payment->checkout_request_id)
            && ($result['CheckoutRequestID'] ?? null) === $payment->checkout_request_id
            && array_key_exists('ResultCode', $result)) {
            $details = collect($result['CallbackMetadata']['Item'] ?? [])->pluck('Value', 'Name');

            $record(
                $payment,
                code: (string) $result['ResultCode'],
                description: (string) ($result['ResultDesc'] ?? ''),
                receipt: $details->has('MpesaReceiptNumber') ? (string) $details['MpesaReceiptNumber'] : null,
                paidShillings: $details->has('Amount') ? (int) round((float) $details['Amount']) : null,
                response: $request->all(),
            );
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
