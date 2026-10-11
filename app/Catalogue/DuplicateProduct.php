<?php

namespace App\Catalogue;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Makes a copy of a product to start a similar one from: its details, its
 * options and their choices, and its variants with their prices and sizes.
 *
 * The copy is a draft with a name and an address of its own, new SKUs and no
 * stock, and it has no photos: those belong to the piece that was photographed.
 */
class DuplicateProduct
{
    public function __invoke(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            // Read afresh: a product taken from a list carries counts and totals that are not columns of its own.
            $product = Product::query()->with('options.values', 'variants.optionValues', 'collections')->findOrFail($product->getKey());

            $copy = $product->replicate(['slug', 'status', 'published_at', 'search_text']);
            $copy->fill(['name' => 'Copy of '.$product->name, 'status' => ProductStatus::Draft, 'published_at' => null]);
            $copy->save();

            // The copy's choices, by the original choice each stands for, so each variant can be given the right ones.
            $choices = [];

            foreach ($product->options as $option) {
                $optionCopy = $copy->options()->create($option->only(['name', 'position']));

                foreach ($option->values as $value) {
                    $choices[$value->id] = $optionCopy->values()->create($value->only(['label', 'swatch', 'position']))->id;
                }
            }

            foreach ($product->variants as $variant) {
                $variantCopy = $copy->variants()->create(
                    ['sku' => $this->sku($variant->sku)] + $variant->only([
                        'price', 'compare_at_price', 'low_stock_threshold', 'made_to_order', 'lead_time_weeks',
                        'width_cm', 'depth_cm', 'height_cm', 'weight_kg', 'is_default', 'is_active', 'position',
                    ]),
                );

                $variantCopy->optionValues()->attach($variant->optionValues->map(fn ($value) => $choices[$value->id])->all());
            }

            $copy->collections()->attach($product->collections->modelKeys());
            SearchIndex::refresh($copy);

            return $copy;
        });
    }

    /** The original's SKU with "-COPY" after it, and a number as well if that is taken. */
    private function sku(string $original): string
    {
        $sku = $original.'-COPY';

        for ($number = 2; ProductVariant::query()->where('sku', $sku)->exists(); $number++) {
            $sku = $original.'-COPY-'.$number;
        }

        return $sku;
    }
}
