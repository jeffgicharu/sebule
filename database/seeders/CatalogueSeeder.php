<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\StockMovementReason;
use App\Inventory\StockLedger;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The shop's collections and products, read from data/catalogue.json.
 *
 * Safe to run again: a collection or product whose slug already exists is
 * left exactly as it is, so nothing staff have changed is overwritten.
 */
class CatalogueSeeder extends Seeder
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function run(): void
    {
        $catalogue = json_decode(
            file_get_contents(__DIR__.'/data/catalogue.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $collections = $this->collections($catalogue['collections']);
        $categories = Category::query()->pluck('id', 'slug');

        foreach ($catalogue['products'] as $entry) {
            if (Product::query()->where('slug', $entry['slug'])->exists()) {
                continue;
            }

            $categoryId = $categories[$entry['category']]
                ?? throw new RuntimeException("Unknown category [{$entry['category']}] for [{$entry['slug']}]. Run the category seeder first.");

            DB::transaction(function () use ($entry, $categoryId, $collections): void {
                $product = $this->product($entry, $categoryId);

                $this->variants($product, $entry);

                if ($entry['collection'] !== null) {
                    $collection = $collections[$entry['collection']];
                    $collection->products()->attach($product, ['position' => $collection->products()->count() + 1]);
                }
            });
        }
    }

    /**
     * @param  array<int, array{slug: string, name: string, description: string}>  $entries
     * @return array<string, Collection> keyed by slug
     */
    private function collections(array $entries): array
    {
        $collections = [];

        foreach ($entries as $index => $entry) {
            $collections[$entry['slug']] = Collection::firstOrCreate(
                ['slug' => $entry['slug']],
                ['name' => $entry['name'], 'description' => $entry['description'], 'position' => $index + 1, 'is_active' => true],
            );
        }

        return $collections;
    }

    /** @param array<string, mixed> $entry */
    private function product(array $entry, int $categoryId): Product
    {
        return Product::create([
            'category_id' => $categoryId,
            'name' => $entry['name'],
            'slug' => $entry['slug'],
            'summary' => $entry['summary'],
            'description' => '<p>'.e($entry['description']).'</p>',
            'materials' => $entry['materials'],
            'care' => $entry['care'],
            'assembly_required' => $entry['assembly_required'],
            'warranty_months' => $entry['warranty_months'],
            'status' => ProductStatus::Published,
        ]);
    }

    /**
     * One variant per option value, or a single variant when the product has no option.
     *
     * @param  array<string, mixed>  $entry
     */
    private function variants(Product $product, array $entry): void
    {
        if ($entry['option'] === null) {
            $this->variant($product, $entry, sku: $entry['slug'], priceDelta: 0, stock: $entry['stock'], isDefault: true, position: 1);

            return;
        }

        $option = $product->options()->create(['name' => $entry['option']['name'], 'position' => 1]);

        foreach ($entry['option']['values'] as $index => $value) {
            $optionValue = $option->values()->create([
                'label' => $value['label'],
                'swatch' => Str::upper($value['hex']),
                'position' => $index + 1,
            ]);

            $variant = $this->variant(
                $product,
                $entry,
                sku: $entry['slug'].'-'.$value['slug'],
                priceDelta: $value['price_delta'],
                stock: $value['stock'],
                isDefault: $value['slug'] === $entry['default'],
                position: $index + 1,
            );

            $variant->optionValues()->attach($optionValue);
        }
    }

    /** @param array<string, mixed> $entry */
    private function variant(Product $product, array $entry, string $sku, int $priceDelta, int $stock, bool $isDefault, int $position): ProductVariant
    {
        $variant = $product->variants()->create([
            'sku' => Str::upper($sku),
            'price' => Money::fromShillings($entry['price'] + $priceDelta),
            'compare_at_price' => $entry['compare_at_price'] === null
                ? null
                : Money::fromShillings($entry['compare_at_price'] + $priceDelta),
            'made_to_order' => $entry['made_to_order'],
            'lead_time_weeks' => $entry['lead_time_weeks'],
            'width_cm' => $entry['size_cm']['w'],
            'depth_cm' => $entry['size_cm']['d'],
            'height_cm' => $entry['size_cm']['h'],
            'weight_kg' => $entry['weight_kg'],
            'is_default' => $isDefault,
            'position' => $position,
        ]);

        if ($stock > 0) {
            $this->ledger->adjust($variant, $stock, StockMovementReason::Received, 'Opening stock');
        }

        return $variant;
    }
}
