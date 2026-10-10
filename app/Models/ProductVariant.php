<?php

namespace App\Models;

use App\Enums\Availability;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One buyable combination of a product's option values, with its own SKU,
 * price and stock. Prices are whole numbers of cents.
 *
 * Stock is not fillable: it changes only through App\Inventory\StockLedger,
 * which records every movement.
 */
#[Fillable([
    'product_id', 'sku', 'price', 'compare_at_price', 'low_stock_threshold', 'made_to_order',
    'lead_time_weeks', 'width_cm', 'depth_cm', 'height_cm', 'weight_kg', 'is_default', 'is_active', 'position',
])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'stock_on_hand' => 'integer',
            'made_to_order' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // A product shows one variant first, so marking one as default clears the others.
        static::saved(function (ProductVariant $variant): void {
            if ($variant->is_default && ($variant->wasChanged('is_default') || $variant->wasRecentlyCreated)) {
                static::query()
                    ->where('product_id', $variant->product_id)
                    ->whereKeyNot($variant->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsToMany<ProductOptionValue, $this> */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_variant_option_value');
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function availability(): Availability
    {
        return match (true) {
            $this->made_to_order => Availability::MadeToOrder,
            $this->stock_on_hand <= 0 => Availability::OutOfStock,
            $this->stock_on_hand <= $this->low_stock_threshold => Availability::LowStock,
            default => Availability::InStock,
        };
    }

    /**
     * The most of this variant one order can take: what is on hand, or a set
     * number for a piece that is made to order.
     */
    public function purchasableLimit(): int
    {
        return $this->made_to_order
            ? (int) config('store.made_to_order_limit')
            : max(0, $this->stock_on_hand);
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    /** The photo that shows this variant: its own colour or finish first, then the product's. */
    public function photo(): ?Media
    {
        return $this->optionValues
            ->flatMap(fn (ProductOptionValue $value) => $value->getMedia(ProductOptionValue::IMAGES))
            ->first() ?? $this->product->mainPhoto();
    }

    /** The option values joined for display, such as "Sand / 3-seater". */
    public function label(): string
    {
        return $this->optionValues->sortBy(fn (ProductOptionValue $value) => $value->option->position)
            ->pluck('label')
            ->join(' / ') ?: 'Standard';
    }
}
