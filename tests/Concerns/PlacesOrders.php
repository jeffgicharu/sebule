<?php

namespace Tests\Concerns;

use App\Checkout\PlaceOrder;
use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\County;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Payments\TakeTestPayment;
use Illuminate\Support\Str;

/**
 * Orders for tests, placed the way the checkout places them.
 */
trait PlacesOrders
{
    /**
     * The address orders are placed with unless a test gives another. It is an
     * example address, which the store never sends to; a test about sending
     * sets one that is not.
     */
    protected string $customerEmail = 'wanjiru@example.com';

    /** A piece on sale at KSh 45,000 with five in stock, or made to order. */
    protected function piece(string $name = 'Amani sofa', bool $madeToOrder = false, int $stock = 5): ProductVariant
    {
        $factory = ProductVariant::factory()->for(Product::factory()->create(['name' => $name, 'status' => ProductStatus::Published]));

        return ($madeToOrder ? $factory->madeToOrder(4) : $factory->withStock($stock))
            ->create(['price' => 45_000_00, 'sku' => Str::upper(Str::slug($name)).'-'.Str::upper(Str::random(4))])
            ->refresh();
    }

    /**
     * An order awaiting payment for Wanjiru Kamau, delivered to Kilimani in Nairobi
     * unless the details say otherwise.
     *
     * @param  array<string, mixed>  $details
     */
    protected function placeOrder(array $details = [], ?ProductVariant $piece = null, int $quantity = 1): Order
    {
        $piece ??= $this->piece();

        $cart = Cart::create(['token' => (string) Str::uuid()]);
        $cart->items()->create(['product_variant_id' => $piece->id, 'quantity' => $quantity, 'unit_price' => $piece->price]);

        $details += [
            'name' => 'Wanjiru Kamau', 'phone' => '254712345678', 'email' => $this->customerEmail, 'marketing' => false,
            'method' => 'delivery', 'county_id' => County::where('name', 'Nairobi')->value('id'),
            'town' => 'Kilimani', 'estate' => 'Riara Court', 'house' => 'A2', 'directions' => null,
        ];

        $fee = $details['method'] === 'delivery' ? County::with('zone')->findOrFail($details['county_id'])->zone->fee : 0;

        return app(PlaceOrder::class)($cart, $details, (string) Str::uuid(), $piece->price * $quantity + $fee);
    }

    /**
     * The same order, paid for.
     *
     * @param  array<string, mixed>  $details
     */
    protected function paidOrder(array $details = [], ?ProductVariant $piece = null, int $quantity = 1): Order
    {
        $order = $this->placeOrder($details, $piece, $quantity);

        app(TakeTestPayment::class)($order);

        return $order->refresh();
    }
}
