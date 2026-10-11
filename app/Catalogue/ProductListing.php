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
        'popular' => 'Most popular',
    ];

    /** How far back "most popular" looks at what has sold, in days. */
    private const POPULAR_DAYS = 90;

    /** @var array<string, array{0: int, 1: int|null, 2: string}> lower and upper bound in centimetres, and the label */
    public const WIDTH_BANDS = [
        'under-60' => [0, 60, 'Under 60 cm wide'],
        '60-120' => [60, 120, '60 to 120 cm wide'],
        '120-180' => [120, 180, '120 to 180 cm wide'],
        'over-180' => [180, null, 'Over 180 cm wide'],
    ];

    /**
     * The materials a customer can ask for, by the word that names each in a product's materials.
     *
     * @var array<string, string>
     */
    public const MATERIALS = [
        'mvule' => 'Mvule',
        'mahogany' => 'Mahogany',
        'oak' => 'Meru oak',
        'cypress' => 'Cypress',
        'cane' => 'Cane',
        'rattan' => 'Rattan',
        'sisal' => 'Sisal',
        'linen' => 'Linen',
        'cotton' => 'Cotton',
        'leather' => 'Leather',
        'steel' => 'Steel',
        'stoneware' => 'Stoneware',
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
     * @param  array{sort?: string|null, price?: string|null, colour?: string|null, availability?: string|null, material?: string|null, width?: string|null}  $filters
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

        if (isset(self::MATERIALS[$filters['material'] ?? ''])) {
            $query->where('products.materials', 'ilike', '%'.$filters['material'].'%');
        }

        if ($band = self::WIDTH_BANDS[$filters['width'] ?? ''] ?? null) {
            $query->whereHas('variants', function (Builder $variants) use ($band): void {
                $variants->where('is_active', true)->where('width_cm', '>=', $band[0]);

                if ($band[1] !== null) {
                    $variants->where('width_cm', '<', $band[1]);
                }
            });
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
            // By how many have been bought and paid for lately, and then in the usual order.
            'popular' => $query->orderByRaw(
                '(select coalesce(sum(order_items.quantity), 0) from order_items
                    join product_variants on product_variants.id = order_items.product_variant_id
                    join orders on orders.id = order_items.order_id
                    where product_variants.product_id = products.id
                        and orders.paid_at >= ? and orders.status <> ?) desc',
                [now()->subDays(self::POPULAR_DAYS), 'cancelled'],
            )->orderBy('products.id'),
            default => $query->orderBy('products.id'),
        };
    }

    /**
     * The materials found among a set of products, in the order they are listed above.
     *
     * @param  Builder<Product>  $products
     * @return array<string, string> the word in the address => the name shown
     */
    public static function materials(Builder $products): array
    {
        $described = $products->clone()->reorder()->toBase()->pluck('products.materials')->filter()->map(fn (string $text) => mb_strtolower($text));

        return array_filter(self::MATERIALS, fn (string $word) => $described->contains(fn (string $text) => str_contains($text, $word)), ARRAY_FILTER_USE_KEY);
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
