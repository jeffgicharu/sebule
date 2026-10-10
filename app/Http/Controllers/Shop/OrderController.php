<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Orders\PieceNoLongerAvailable;
use App\Orders\ResumeOrder;
use App\Payments\Mpesa\Daraja;
use App\Payments\StartMpesaPayment;
use App\Payments\TakeTestPayment;
use App\Support\Phone;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * An order as its customer sees it. Every address here carries the order's
 * secret token, so the link in a message is all a customer needs: no account.
 */
class OrderController extends Controller
{
    /** The order and how it is getting on. Also where a customer lands once it is paid. */
    public function show(Order $order): View
    {
        return view('shop.order.show', ['order' => $order->load('items.variant.product.media', 'items.variant.optionValues.media', 'events', 'payments')]);
    }

    public function pay(Order $order, Daraja $daraja): View|RedirectResponse
    {
        if ($order->status->isPaid() || $order->status === OrderStatus::Cancelled) {
            return redirect()->route('orders.show', $order);
        }

        $order->load('items', 'payments');

        return view('shop.order.pay', [
            'order' => $order,
            'waiting' => $order->openPayment(),
            'last' => $order->payments->first(),
            'mpesa' => $daraja->isConfigured(),
            'charge' => StartMpesaPayment::charge($order),
        ]);
    }

    /** Send the M-Pesa prompt. Sending it again while one is open does nothing new. */
    public function mpesa(Request $request, Order $order, StartMpesaPayment $start, Daraja $daraja): RedirectResponse
    {
        abort_unless($daraja->isConfigured(), 404);

        if (! $order->awaitsPayment()) {
            return redirect()->route('orders.pay', $order);
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (Phone::normalise((string) $value) === null) {
                    $fail('Enter the Safaricom number to send the prompt to, such as 0712 345 678.');
                }
            }],
        ]);

        $start($order, Phone::normalise($data['phone']));

        return redirect()->route('orders.pay', $order);
    }

    /** Complete the order without money changing hands, where the store allows it. */
    public function test(Order $order, TakeTestPayment $take): RedirectResponse
    {
        abort_unless(config('store.test_payments'), 404);

        if ($order->awaitsPayment()) {
            $take($order);
        }

        return redirect()->route('orders.pay', $order);
    }

    /** Hold the pieces again for an order that ran out of time. */
    public function resume(Order $order, ResumeOrder $resume): RedirectResponse
    {
        try {
            $resume($order);
        } catch (PieceNoLongerAvailable $gone) {
            return redirect()->route('orders.pay', $order)
                ->with('shop.problem', "The {$gone->piece} has sold out since you placed this order, so it could not be held again.");
        }

        return redirect()->route('orders.pay', $order);
    }

    /** What the payment page asks while it waits: has anything changed? */
    public function status(Order $order): JsonResponse
    {
        $latest = $order->payments()->first();

        return response()->json([
            'order' => $order->status->value,
            'payment' => $latest?->state->value,
            'method' => $latest?->method === PaymentMethod::Mpesa ? 'mpesa' : $latest?->method->value,
        ])->header('Cache-Control', 'no-store');
    }
}
