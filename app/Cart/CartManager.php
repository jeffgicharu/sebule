<?php

namespace App\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finds the cart that belongs to the browser making the request and changes
 * what is in it. A cart is created only when something is first added, so a
 * visitor who only looks around never gets one.
 */
class CartManager
{
    public const COOKIE = 'sebule_cart';

    /** Everything a cart page needs to draw its lines without further queries. */
    private const WITH = [
        'items.variant.product.media',
        'items.variant.product.options.values.media',
        'items.variant.optionValues.option',
        'items.variant.optionValues.media',
    ];

    private ?Cart $cart = null;

    /** The request the cart above was loaded for. Nothing is carried from one request to the next. */
    private ?Request $loadedFor = null;

    public function current(): ?Cart
    {
        $token = $this->token();

        if (! is_string($token) || ! Str::isUuid($token)) {
            return null;
        }

        if ($this->cart?->token !== $token) {
            $this->cart = Cart::query()->where('token', $token)->with(self::WITH)->first();
        }

        return $this->cart;
    }

    /** The number of pieces in the cart, for the header of every page. */
    public function pieces(): int
    {
        $token = $this->token();

        if (! is_string($token) || ! Str::isUuid($token)) {
            return 0;
        }

        if ($this->cart?->token === $token) {
            return $this->cart->pieces();
        }

        return (int) CartItem::query()
            ->whereRelation('cart', 'token', $token)
            ->sum('quantity');
    }

    /**
     * Put a variant in the cart, or more of it. Never more than can be bought:
     * the quantity stops at what is in stock.
     *
     * @return array{item: CartItem|null, limited: bool} no item when none can be bought at all
     */
    public function add(ProductVariant $variant, int $quantity): array
    {
        $limit = $variant->purchasableLimit();

        if ($limit === 0) {
            return ['item' => null, 'limited' => true];
        }

        return DB::transaction(function () use ($variant, $quantity, $limit): array {
            $cart = $this->current() ?? $this->create();

            $item = $cart->items()->where('product_variant_id', $variant->getKey())->lockForUpdate()->first()
                ?? $cart->items()->make(['product_variant_id' => $variant->getKey(), 'quantity' => 0]);

            $wanted = $item->quantity + max(1, $quantity);

            $item->fill(['quantity' => min($wanted, $limit), 'unit_price' => $variant->price])->save();
            $this->changed($cart);

            return ['item' => $item, 'limited' => $wanted > $limit];
        });
    }

    /** Set how many of a line the shopper wants, within what can be bought. Returns the quantity kept. */
    public function setQuantity(CartItem $item, int $quantity): int
    {
        $kept = max(1, min($quantity, max(1, $item->limit())));

        $item->update(['quantity' => $kept, 'unit_price' => $item->price()]);
        $this->changed($item->cart);

        return $kept;
    }

    public function remove(CartItem $item): void
    {
        $cart = $item->cart;
        $item->delete();
        $this->changed($cart);
    }

    /** Let go of a cart that no longer exists, as when its contents have become an order. */
    public function forget(): void
    {
        $this->cart = null;

        Cookie::queue(Cookie::forget(self::COOKIE));
        request()->cookies->remove(self::COOKIE);
    }

    /** The cart token the browser sent, and a clean slate when this is a new request. */
    private function token(): mixed
    {
        if ($this->loadedFor !== request()) {
            $this->cart = null;
            $this->loadedFor = request();
        }

        return request()->cookie(self::COOKIE);
    }

    private function create(): Cart
    {
        $cart = Cart::create(['token' => (string) Str::uuid()]);

        Cookie::queue(self::COOKIE, $cart->token, config('store.cart_days') * 24 * 60);

        // So the rest of this request finds the cart it has just made.
        request()->cookies->set(self::COOKIE, $cart->token);

        return $this->cart = $cart->setRelation('items', $cart->items()->getModel()->newCollection());
    }

    /** Forget what was loaded, and note that the shopper is still using the cart. */
    private function changed(Cart $cart): void
    {
        $cart->touch();
        $this->cart = null;
    }
}
