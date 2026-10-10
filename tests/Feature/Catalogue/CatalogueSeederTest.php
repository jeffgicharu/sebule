<?php

namespace Tests\Feature\Catalogue;

use App\Enums\Availability;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);
    }

    public function test_it_creates_the_whole_catalogue(): void
    {
        $this->assertSame(80, Product::count());
        $this->assertSame(80, Product::published()->count());
        $this->assertSame(8, Collection::count());
        $this->assertSame(0, Product::doesntHave('variants')->count());
        $this->assertSame(80, ProductVariant::where('is_default', true)->count());
    }

    public function test_every_product_sits_in_a_second_level_category(): void
    {
        $this->assertSame(0, Product::whereHas('category', fn ($query) => $query->whereNull('parent_id'))->count());
        $this->assertSame(0, Category::whereNotNull('parent_id')->doesntHave('products')->count());
    }

    public function test_a_product_with_colours_gets_a_variant_for_each(): void
    {
        $sofa = Product::where('slug', 'amani-3-seater-sofa')->with('variants.optionValues.option', 'options.values')->first();

        $this->assertSame('Colour', $sofa->options->first()->name);
        $this->assertSame(['Sand', 'Forest', 'Clay'], $sofa->options->first()->values->pluck('label')->all());
        $this->assertSame(['AMANI-3-SEATER-SOFA-SAND', 'AMANI-3-SEATER-SOFA-FOREST', 'AMANI-3-SEATER-SOFA-CLAY'], $sofa->variants->pluck('sku')->all());
        $this->assertSame('Sand', $sofa->defaultVariant->label());
        $this->assertSame(9_800_000, $sofa->defaultVariant->price);
        $this->assertSame('#CDBA9A', $sofa->options->first()->values->first()->swatch);
        $this->assertSame('220.0', $sofa->defaultVariant->width_cm);
    }

    public function test_a_dearer_finish_carries_its_price_difference(): void
    {
        $table = Product::where('slug', 'tana-6-seater-dining-table')->first();

        $this->assertSame(8_500_000, $table->variants()->where('sku', 'TANA-6-SEATER-DINING-TABLE-NATURAL')->value('price'));
        $this->assertSame(8_800_000, $table->variants()->where('sku', 'TANA-6-SEATER-DINING-TABLE-WALNUT')->value('price'));
    }

    public function test_a_product_without_options_gets_one_variant(): void
    {
        $product = Product::doesntHave('options')->first();

        $this->assertSame(1, $product->variants()->count());
        $this->assertSame('Standard', $product->variants()->first()->label());
        $this->assertTrue($product->variants()->first()->is_default);
    }

    public function test_opening_stock_goes_through_the_ledger(): void
    {
        $sand = ProductVariant::where('sku', 'AMANI-3-SEATER-SOFA-SAND')->first();
        $clay = ProductVariant::where('sku', 'AMANI-3-SEATER-SOFA-CLAY')->first();

        $this->assertSame(6, $sand->stock_on_hand);
        $this->assertSame('Opening stock', $sand->stockMovements()->first()->note);
        $this->assertSame(6, $sand->stockMovements()->first()->balance_after);

        $this->assertSame(0, $clay->stock_on_hand);
        $this->assertSame(Availability::OutOfStock, $clay->availability());
        $this->assertSame(0, $clay->stockMovements()->count());

        $this->assertSame(ProductVariant::where('stock_on_hand', '>', 0)->count(), StockMovement::count());
    }

    public function test_made_to_order_pieces_have_a_lead_time_and_no_stock(): void
    {
        $madeToOrder = ProductVariant::where('made_to_order', true)->get();

        $this->assertSame(12, $madeToOrder->pluck('product_id')->unique()->count());
        $this->assertSame(0, $madeToOrder->sum('stock_on_hand'));
        $this->assertTrue($madeToOrder->every(fn (ProductVariant $variant) => $variant->lead_time_weeks >= 3));
        $this->assertTrue($madeToOrder->every(fn (ProductVariant $variant) => $variant->availability() === Availability::MadeToOrder));
    }

    public function test_eight_products_are_on_sale(): void
    {
        $onSale = ProductVariant::whereNotNull('compare_at_price')->get();

        $this->assertSame(8, $onSale->pluck('product_id')->unique()->count());
        $this->assertTrue($onSale->every(fn (ProductVariant $variant) => $variant->isOnSale()));
    }

    public function test_collections_hold_their_products_in_order(): void
    {
        $amani = Collection::where('slug', 'amani')->first();

        $this->assertSame(5, $amani->products()->count());
        $this->assertSame('Amani 3-seater sofa', $amani->products()->first()->name);
        $this->assertSame(58, Product::has('collections')->count());
    }

    public function test_corrected_wording_can_be_applied_to_named_products(): void
    {
        Product::whereIn('slug', ['lamu-queen-bed', 'kito-table-lamp'])->update(['summary' => 'Old wording']);

        $this->artisan('catalogue:refresh-copy', ['slugs' => ['lamu-queen-bed']])->assertSuccessful();

        $this->assertStringContainsString('cane panels', Product::where('slug', 'lamu-queen-bed')->value('summary'));
        $this->assertStringContainsString('two panels', Product::where('slug', 'lamu-queen-bed')->value('description'));
        $this->assertSame('Old wording', Product::where('slug', 'kito-table-lamp')->value('summary'));

        $this->artisan('catalogue:refresh-copy', ['slugs' => ['no-such-product']])->assertFailed();
    }

    public function test_running_it_again_changes_nothing(): void
    {
        Product::where('slug', 'kito-table-lamp')->update(['name' => 'Kito lamp, renamed by staff', 'status' => ProductStatus::Draft]);

        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);

        $this->assertSame(80, Product::count());
        $this->assertSame(8, Collection::count());
        $this->assertSame('Kito lamp, renamed by staff', Product::where('slug', 'kito-table-lamp')->value('name'));
        $this->assertSame(5, Collection::where('slug', 'amani')->first()->products()->count());
        $this->assertSame(ProductVariant::where('stock_on_hand', '>', 0)->count(), StockMovement::count());
    }
}
