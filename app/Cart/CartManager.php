<?php

namespace App\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // An account's cart is shown only to that account. A browser still holding its cookie after the customer has gone gets nothing.
        if ($this->cart !== null && $this->cart->customer_id !== null && $this->cart->customer_id !== Auth::guard('customer')->id()) {
            $this->forget();

            return null;
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

        $customer = Auth::guard('customer')->id();

        return (int) CartItem::query()
            ->whereHas('cart', fn (Builder $cart) => $cart
                ->where('token', $token)
                ->where(fn (Builder $owner) => $owner->whereNull('customer_id')->when($customer, fn (Builder $mine) => $mine->orWhere('customer_id', $customer))))
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

    /**
     * A customer has signed in on this browser. Their cart is the one kept
     * with their account, so it follows them from device to device, and
     * whatever this browser was carrying goes into it.
     */
    public function signedIn(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $carried = $this->current();
            $kept = Cart::query()->where('customer_id', $customer->getKey())->lockForUpdate()->first();

            if ($carried !== null && $kept === null && $carried->customer_id === null) {
                // Nothing was waiting in the account, so the browser's cart becomes the account's.
                $carried->update(['customer_id' => $customer->getKey()]);

                return;
            }

            if ($kept === null) {
                $this->forgetUnlessGuest($carried);

                return;
            }

            if ($carried !== null && $carried->customer_id === null && ! $carried->is($kept)) {
                $this->pour($carried, $kept);
            }

            $this->carry($kept);
        });
    }

    /**
     * A customer is signing out. A browser left signed out should not show
     * the next person their cart; it waits in the account.
     */
    public function signedOut(): void
    {
        if ($this->current()?->customer_id !== null) {
            $this->forget();
        }
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

            // A signed-in customer whose browser has lost its cookie still has the cart kept with their account.
            if (! Str::isUuid((string) request()->cookie(self::COOKIE)) && ($customer = Auth::guard('customer')->id())) {
                if ($kept = Cart::query()->where('customer_id', $customer)->first()) {
                    $this->carry($kept);
                }
            }
        }

        return request()->cookie(self::COOKIE);
    }

    private function create(): Cart
    {
        $cart = Cart::create(['token' => (string) Str::uuid(), 'customer_id' => Auth::guard('customer')->id()]);

        $this->carry($cart);

        return $this->cart = $cart->setRelation('items', $cart->items()->getModel()->newCollection());
    }

    /** Have this browser carry a cart from now on. */
    private function carry(Cart $cart): void
    {
        Cookie::queue(self::COOKIE, $cart->token, config('store.cart_days') * 24 * 60);

        // So the rest of this request finds the cart it has just been given.
        request()->cookies->set(self::COOKIE, $cart->token);

        $this->cart = null;
    }

    /** Move every line of one cart into another, never past what can be bought, and delete the emptied cart. */
    private function pour(Cart $from, Cart $into): void
    {
        $from->items()->with('variant')->get()->each(function (CartItem $line) use ($into): void {
            $limit = $line->variant?->purchasableLimit() ?? 0;

            if ($limit === 0) {
                return;
            }

            $existing = $into->items()->where('product_variant_id', $line->product_variant_id)->lockForUpdate()->first()
                ?? $into->items()->make(['product_variant_id' => $line->product_variant_id, 'quantity' => 0]);

            $existing->fill(['quantity' => min($existing->quantity + $line->quantity, $limit), 'unit_price' => $line->variant->price])->save();
        });

        $from->delete();
        $into->touch();
    }

    /** A cart that belongs to some other account is not this customer's to carry. */
    private function forgetUnlessGuest(?Cart $carried): void
    {
        if ($carried !== null && $carried->customer_id !== null) {
            $this->forget();
        }
    }

    /** Forget what was loaded, and note that the shopper is still using the cart. */
    private function changed(Cart $cart): void
    {
        $cart->touch();
        $this->cart = null;
    }
}
