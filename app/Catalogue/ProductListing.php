<?php

namespace App\Catalogue;

use App\Models\Product;
use App\Models\ProductOptionValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the product lists the shop shows: what a card needs loaded, and the
 * filters and sort orders a customer can pick, all of which live in the URL.
 */
class ProductListing
{
    /** @var array<string, string> */
    public const SORTS = [
        'featured' => 'Featured',
        'price-asc' => 'Price, low to high',
        'price-desc' => 'Price, high to low',
        'newest' => 'Newest',
    ];

    /** @var array<string, array{0: int, 1: int|null, 2: string}> lower and upper bound in shillings, and the label */
    public const PRICE_BANDS = [
        'under-20000' => [0, 20_000, 'Under KSh 20,000'],
        '20000-50000' => [20_000, 50_000, 'KSh 20,000 to 50,000'],
        '50000-100000' => [50_000, 100_000, 'KSh 50,000 to 100,000'],
        'over-100000' => [100_000, null, 'Over KSh 100,000'],
    ];

    /** @var array<string, string> */
    public const AVAILABILITY = [
        'in-stock' => 'In stock',
        'made-to-order' => 'Made to order',
    ];

    /**
     * Published products with everything a product card shows.
     *
     * @return Builder<Product>
     */
    public static function cards(): Builder
    {
        $active = fn (Builder $query) => $query->where('is_active', true);

        return Product::query()
            ->published()
            ->whereHas('variants', $active)
            ->with(['media', 'defaultVariant.optionValues.media', 'options.values', 'category'])
            ->withMin(['variants as price_from' => $active], 'price')
            ->withMax(['variants as price_to' => $active], 'price')
            ->withMax(['variants as was_price' => $active], 'compare_at_price')
            ->withSum(['variants as stock_total' => $active], 'stock_on_hand')
            ->withMax(['variants as made_to_order_any' => $active], 'lead_time_weeks');
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array{sort?: string|null, price?: string|null, colour?: string|null, availability?: string|null}  $filters
     * @return Builder<Product>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        if ($band = self::PRICE_BANDS[$filters['price'] ?? ''] ?? null) {
            $query->whereHas('variants', function (Builder $variants) use ($band): void {
                $variants->where('is_active', true)->where('price', '>=', $band[0] * 100);

                if ($band[1] !== null) {
                    $variants->where('price', '<', $band[1] * 100);
                }
            });
        }

        if (filled($filters['colour'] ?? null)) {
            $query->whereHas('options.values', fn (Builder $values) => $values->where('label', $filters['colour']));
        }

        match ($filters['availability'] ?? null) {
            'in-stock' => $query->whereHas('variants', fn (Builder $variants) => $variants->where('is_active', true)->where('stock_on_hand', '>', 0)),
            'made-to-order' => $query->whereHas('variants', fn (Builder $variants) => $variants->where('is_active', true)->where('made_to_order', true)),
            default => null,
        };

        return match ($filters['sort'] ?? 'featured') {
            'price-asc' => $query->orderBy('price_from')->orderBy('products.id'),
            'price-desc' => $query->orderByDesc('price_from')->orderBy('products.id'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('products.id'),
            default => $query->orderBy('products.id'),
        };
    }

    /**
     * The colours and finishes on offer within a set of products, most common first.
     *
     * @param  Builder<Product>  $products
     * @return Collection<int, object{label: string, swatch: string|null}>
     */
    public static function colours(Builder $products): Collection
    {
        return ProductOptionValue::query()
            ->selectRaw('product_option_values.label, MIN(product_option_values.swatch) AS swatch, COUNT(DISTINCT product_options.product_id) AS products')
            ->join('product_options', 'product_options.id', '=', 'product_option_values.product_option_id')
            ->whereIn('product_options.product_id', $products->clone()->reorder()->select('products.id'))
            ->groupBy('product_option_values.label')
            ->orderByDesc('products')
            ->orderBy('product_option_values.label')
            ->limit(14)
            ->toBase()
            ->get();
    }
}
