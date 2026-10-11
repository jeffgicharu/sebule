<?php

namespace App\Checkout;

use App\Enums\DeliveryMethod;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Inventory\InsufficientStock;
use App\Inventory\StockLedger;
use App\Messages\TellCustomer;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\County;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a cart into an order awaiting payment.
 *
 * Everything happens in one transaction: the order and its lines are written,
 * the stock for it is taken and the cart is emptied, or none of it happens.
 * The checkout key makes it safe to repeat: the same key always gives the same
 * order, however many times the form is sent.
 */
class PlaceOrder
{
    public function __construct(private readonly StockLedger $stock, private readonly TellCustomer $tell) {}

    /**
     * @param  array<string, mixed>  $details  the customer and delivery details, already validated
     * @param  Customer|null  $customer  the account the order is placed from, when the customer is signed in
     *
     * @throws CartHasChanged
     */
    public function __invoke(Cart $cart, array $details, string $checkoutKey, int $expectedTotal, ?Customer $customer = null): Order
    {
        try {
            return DB::transaction(function () use ($cart, $details, $checkoutKey, $expectedTotal, $customer): Order {
                // Two submissions of the same cart take turns here, and the second finds the first one's order.
                $cart = Cart::query()->lockForUpdate()->find($cart->getKey());

                if ($existing = Order::query()->where('checkout_key', $checkoutKey)->first()) {
                    return $existing;
                }

                if ($cart === null) {
                    throw new CartHasChanged('Your cart has already been checked out.');
                }

                $cart->load('items.variant.product', 'items.variant.optionValues.option');

                if ($cart->items->isEmpty() || $cart->hasProblems()) {
                    throw new CartHasChanged('Something in your cart is no longer available as it was.');
                }

                $method = DeliveryMethod::from($details['method']);
                $county = $method === DeliveryMethod::Delivery
                    ? County::query()->deliveredTo()->with('zone')->find($details['county_id'])
                    : null;

                if ($method === DeliveryMethod::Delivery && $county === null) {
                    throw new CartHasChanged('We no longer deliver to the county you chose.');
                }

                $totals = Totals::for($cart, $method, $county?->zone);

                if ($totals->total !== $expectedTotal) {
                    throw new CartHasChanged('A price changed while you were checking out.');
                }

                $order = Order::create([
                    'number' => $this->number(),
                    'token' => Str::random(40),
                    'checkout_key' => $checkoutKey,
                    'customer_id' => $customer?->getKey(),
                    'status' => OrderStatus::PendingPayment,
                    'customer_name' => $details['name'],
                    'phone' => $details['phone'],
                    'email' => $details['email'] ?? null,
                    'marketing_opt_in' => (bool) ($details['marketing'] ?? false),
                    'delivery_method' => $method,
                    'delivery_zone_id' => $county?->zone?->getKey(),
                    'zone_name' => $county?->zone?->name,
                    'county' => $county?->name,
                    'town' => $county ? $details['town'] : null,
                    'estate' => $county ? $details['estate'] : null,
                    'house' => $county ? ($details['house'] ?? null) : null,
                    'directions' => $county ? ($details['directions'] ?? null) : null,
                    'delivery_days_min' => $county?->zone?->days_min,
                    'delivery_days_max' => $county?->zone?->days_max,
                    'subtotal' => $totals->subtotal,
                    'delivery_fee' => $totals->deliveryFee,
                    'total' => $totals->total,
                    'vat_rate' => $totals->vatRate,
                    'vat_amount' => $totals->vatAmount,
                    'held_until' => now()->addMinutes((int) config('store.hold_minutes')),
                ]);

                $cart->items->each(fn (CartItem $item) => $this->addLine($order, $item));

                $order->record(OrderEventType::Placed);
                $cart->delete();

                ($this->tell)($order, OrderEventType::Placed);

                return $order;
            });
        } catch (InsufficientStock) {
            throw new CartHasChanged('Something in your cart sold out while you were checking out.');
        }
    }

    /** Copy a cart line onto the order and take its stock, unless the piece is made to order. */
    private function addLine(Order $order, CartItem $item): void
    {
        $variant = $item->variant;
        $label = $variant->label();

        $line = $order->items()->create([
            'product_variant_id' => $variant->getKey(),
            'product_name' => $variant->product->name,
            'variant_label' => $label === 'Standard' ? null : $label,
            'sku' => $variant->sku,
            'unit_price' => $item->price(),
            'quantity' => $item->quantity,
            'line_total' => $item->total(),
            'made_to_order' => $variant->made_to_order,
            'lead_time_weeks' => $variant->made_to_order ? $variant->lead_time_weeks : null,
            'stock_taken' => ! $variant->made_to_order,
        ]);

        if ($line->stock_taken) {
            $this->stock->adjust($variant, -$item->quantity, StockMovementReason::Sold, 'Order '.$order->number, source: $order);
        }
    }

    /** An order number such as SB-482913: short enough to read out, and not a running count. */
    private function number(): string
    {
        do {
            $number = 'SB-'.random_int(100000, 999999);
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
