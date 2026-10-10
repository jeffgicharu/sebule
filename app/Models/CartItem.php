<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One variant in a cart. It remembers the price it was added at, so the cart
 * can tell the shopper when the price has moved since.
 */
#[Fillable(['cart_id', 'product_variant_id', 'quantity', 'unit_price'])]
class CartItem extends Model
{
    public const UNAVAILABLE = 'unavailable';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const SHORT = 'short';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** Today's price for one, in cents. */
    public function price(): int
    {
        return $this->variant->price;
    }

    public function total(): int
    {
        return $this->price() * $this->quantity;
    }

    /**
     * The line as a summary shows it.
     *
     * @return array{photo: mixed, name: string, label: string|null, quantity: int, price: int, total: int, note: string|null}
     */
    public function summary(): array
    {
        $label = $this->variant->label();

        return [
            'photo' => $this->variant->photo(),
            'name' => $this->variant->product->name,
            'label' => $label === 'Standard' ? null : $label,
            'quantity' => $this->quantity,
            'price' => $this->price(),
            'total' => $this->total(),
            'note' => $this->variant->made_to_order ? 'Made to order in about '.$this->variant->lead_time_weeks.' weeks' : null,
        ];
    }

    public function priceChanged(): bool
    {
        return $this->unit_price !== $this->price();
    }

    /** The most of this variant one order can take right now. */
    public function limit(): int
    {
        return $this->variant->purchasableLimit();
    }

    /**
     * What stops this line from being bought as it stands, if anything:
     * the piece is no longer sold, it has run out, or fewer are left than asked for.
     */
    public function problem(): ?string
    {
        if (! $this->variant->is_active || $this->variant->product->status !== ProductStatus::Published) {
            return self::UNAVAILABLE;
        }

        return match (true) {
            $this->limit() === 0 => self::OUT_OF_STOCK,
            $this->quantity > $this->limit() => self::SHORT,
            default => null,
        };
    }
}
