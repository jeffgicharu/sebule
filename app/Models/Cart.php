<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a shopper has put aside to buy. The browser holds the token; nothing
 * else about the shopper is known until checkout.
 */
#[Fillable(['token'])]
class Cart extends Model
{
    use Prunable;

    /**
     * Carts left untouched for longer than the browser remembers them.
     *
     * @return Builder<Cart>
     */
    public function prunable(): Builder
    {
        return static::query()->where('updated_at', '<', now()->subDays((int) config('store.cart_days')));
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    /** The number of pieces in the cart, counting each quantity. */
    public function pieces(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /** The cart's value at today's prices, in cents. */
    public function subtotal(): int
    {
        return (int) $this->items->sum(fn (CartItem $item) => $item->total());
    }

    /** Whether anything in the cart stops it from being checked out as it stands. */
    public function hasProblems(): bool
    {
        return $this->items->contains(fn (CartItem $item) => $item->problem() !== null);
    }
}
