<?php

namespace App\Catalogue;

use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a variant for every combination of a product's option values that
 * does not have one yet. Existing variants are left alone, so it is safe to
 * run again after an option value is added.
 */
class VariantGenerator
{
    /**
     * @param  int  $price  price in cents given to each new variant
     * @return Collection<int, ProductVariant> the variants that were created
     */
    public function generate(Product $product, int $price = 0): Collection
    {
        return DB::transaction(function () use ($product, $price): Collection {
            $product->load(['options.values', 'variants.optionValues']);

            $combinations = $this->combinations($product);

            $existing = $product->variants
                ->map(fn (ProductVariant $variant) => $this->signature($variant->optionValues->pluck('id')->all()))
                ->all();

            $position = (int) $product->variants->max('position');
            $hasDefault = $product->variants->contains('is_default', true);
            $created = collect();

            foreach ($combinations as $values) {
                $valueIds = $values->pluck('id')->all();

                if (in_array($this->signature($valueIds), $existing, true)) {
                    continue;
                }

                $variant = $product->variants()->create([
                    'sku' => $this->sku($product, $values),
                    'price' => $price,
                    'is_default' => ! $hasDefault,
                    'position' => ++$position,
                ]);

                $variant->optionValues()->sync($valueIds);

                $hasDefault = true;
                $created->push($variant);
            }

            return $created;
        });
    }

    /**
     * Every way of picking one value from each option. A product with no
     * options has exactly one combination: the empty one.
     *
     * @return array<int, Collection<int, ProductOptionValue>>
     */
    private function combinations(Product $product): array
    {
        $combinations = [collect()];

        foreach ($product->options as $option) {
            if ($option->values->isEmpty()) {
                continue;
            }

            $next = [];

            foreach ($combinations as $combination) {
                foreach ($option->values as $value) {
                    $next[] = $combination->concat([$value]);
                }
            }

            $combinations = $next;
        }

        return array_map(fn (Collection $values) => $values->values(), $combinations);
    }

    /** @param array<int, int|string> $valueIds */
    private function signature(array $valueIds): string
    {
        sort($valueIds);

        return implode('-', $valueIds);
    }

    /** @param Collection<int, ProductOptionValue> $values */
    private function sku(Product $product, Collection $values): string
    {
        $parts = collect([$product->slug])
            ->concat($values->map(fn (ProductOptionValue $value) => Str::slug($value->label)))
            ->map(fn (string $part) => Str::upper($part))
            ->join('-');

        $sku = $parts;
        $suffix = 2;

        while (ProductVariant::query()->where('sku', $sku)->exists()) {
            $sku = $parts.'-'.$suffix++;
        }

        return $sku;
    }
}
