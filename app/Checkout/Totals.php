<?php

namespace App\Checkout;

use App\Enums\DeliveryMethod;
use App\Models\Cart;
use App\Models\DeliveryZone;

/**
 * What an order comes to, in cents. Prices already include VAT, so the VAT
 * figure is the part of the total that is tax, not something added on top.
 */
final readonly class Totals
{
    public int $total;

    public int $vatAmount;

    public function __construct(
        public int $subtotal,
        public int $deliveryFee,
        public int $vatRate,
    ) {
        $this->total = $subtotal + $deliveryFee;
        $this->vatAmount = (int) round($this->total * $vatRate / (10000 + $vatRate));
    }

    public static function for(Cart $cart, DeliveryMethod $method, ?DeliveryZone $zone): self
    {
        return new self(
            subtotal: $cart->subtotal(),
            deliveryFee: $method === DeliveryMethod::Delivery ? (int) $zone?->fee : 0,
            vatRate: (int) config('store.vat_rate'),
        );
    }
}
