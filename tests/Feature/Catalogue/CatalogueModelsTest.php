<?php

namespace Tests\Feature\Catalogue;

use App\Enums\Availability;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_slug_is_made_from_the_name(): void
    {
        $category = Category::factory()->create(['name' => 'Living Room']);

        $this->assertSame('living-room', $category->slug);
    }

    public function test_slugs_stay_unique(): void
    {
        $category = Category::factory()->create();

        $first = Product::factory()->for($category)->create(['name' => 'Amani Sofa']);
        $second = Product::factory()->for($category)->create(['name' => 'Amani Sofa']);

        $this->assertSame('amani-sofa', $first->slug);
        $this->assertSame('amani-sofa-2', $second->slug);
    }

    public function test_a_slug_that_was_typed_in_is_kept(): void
    {
        $product = Product::factory()->create(['name' => 'Amani Sofa', 'slug' => 'amani']);

        $product->update(['name' => 'Amani 3-seater Sofa']);

        $this->assertSame('amani', $product->fresh()->slug);
    }

    public function test_publishing_a_product_stamps_the_time_once(): void
    {
        $product = Product::factory()->draft()->create();
        $this->assertNull($product->published_at);

        $product->update(['status' => ProductStatus::Published]);
        $first = $product->fresh()->published_at;
        $this->assertNotNull($first);

        $this->travel(1)->days();
        $product->update(['name' => 'Renamed']);

        $this->assertTrue($first->equalTo($product->fresh()->published_at));
    }

    public function test_only_published_products_are_in_the_published_scope(): void
    {
        Product::factory()->create();
        Product::factory()->draft()->create();
        Product::factory()->create(['status' => ProductStatus::Archived]);

        $this->assertSame(1, Product::published()->count());
    }

    public function test_a_category_with_children_cannot_be_deleted(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create();

        $this->expectException(QueryException::class);

        $parent->delete();
    }

    public function test_availability_follows_stock_and_the_made_to_order_flag(): void
    {
        $this->assertSame(Availability::OutOfStock, ProductVariant::factory()->create()->availability());
        $this->assertSame(Availability::LowStock, ProductVariant::factory()->withStock(2)->create()->fresh()->availability());
        $this->assertSame(Availability::InStock, ProductVariant::factory()->withStock(3)->create()->fresh()->availability());
        $this->assertSame(Availability::MadeToOrder, ProductVariant::factory()->madeToOrder()->create()->availability());
        $this->assertFalse(Availability::OutOfStock->canBeBought());
        $this->assertTrue(Availability::MadeToOrder->canBeBought());
    }

    public function test_stock_cannot_be_set_through_mass_assignment(): void
    {
        $variant = ProductVariant::factory()->create();

        $variant->update(['stock_on_hand' => 50]);

        $this->assertSame(0, $variant->fresh()->stock_on_hand);
    }

    public function test_the_default_variant_is_the_one_marked_default(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['is_default' => false, 'position' => 1]);
        $default = ProductVariant::factory()->for($product)->create(['is_default' => true, 'position' => 2]);

        $this->assertTrue($product->defaultVariant->is($default));
    }
}
