<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Enums\StockMovementReason;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Collections\CollectionResource;
use App\Filament\Resources\Collections\Pages\CreateCollection;
use App\Filament\Resources\Collections\Pages\ListCollections;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogueScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create();
        $this->actingAs($this->staff);
    }

    public function test_the_list_screens_load(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        ProductVariant::factory()->for($product)->withStock(4)->create(['price' => 6_500_000]);
        $collection = Collection::factory()->create();

        Livewire::test(ListCategories::class)->assertOk()->assertCanSeeTableRecords([$category]);
        Livewire::test(ListProducts::class)->assertOk()->assertCanSeeTableRecords([$product])->assertSee('KSh 65,000');
        Livewire::test(ListCollections::class)->assertOk()->assertCanSeeTableRecords([$collection]);
    }

    /**
     * Follows the links the list screens actually print, so a mismatch between
     * how a link is built and how the record is looked up cannot slip through.
     */
    public function test_the_edit_links_on_the_list_screens_open(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        $collection = Collection::factory()->create();

        foreach ([
            CategoryResource::class => $category,
            ProductResource::class => $product,
            CollectionResource::class => $collection,
        ] as $resource => $record) {
            $editUrl = $resource::getUrl('edit', ['record' => $record]);

            $this->get($resource::getUrl('index'))->assertOk()->assertSee($editUrl, escape: false);
            $this->get($editUrl)->assertOk();
        }
    }

    public function test_a_category_can_be_created_under_a_parent(): void
    {
        $parent = Category::factory()->create(['name' => 'Living room']);

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Sofas', 'slug' => 'sofas', 'parent_id' => $parent->id, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Category::where('slug', 'sofas')->first()->parent->is($parent));
    }

    public function test_a_slug_must_be_unique_and_well_formed(): void
    {
        Category::factory()->create(['name' => 'Sofas']);

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Sofas', 'slug' => 'sofas'])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Beds', 'slug' => 'Beds & More'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_a_collection_can_be_created_with_products(): void
    {
        $products = Product::factory()->count(2)->create();

        Livewire::test(CreateCollection::class)
            ->fillForm(['name' => 'Coast', 'slug' => 'coast', 'products' => $products->modelKeys(), 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Collection::where('slug', 'coast')->first()->products()->count());
    }

    public function test_a_product_can_be_created_as_a_draft(): void
    {
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Amani 3-seater sofa',
                'slug' => 'amani-3-seater-sofa',
                'category_id' => $category->id,
                'status' => ProductStatus::Draft,
                'summary' => 'A deep, low sofa in woven linen.',
                'warranty_months' => 24,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'amani-3-seater-sofa')->first();

        $this->assertNotNull($product);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame(24, $product->warranty_months);
    }

    public function test_the_edit_screen_loads_with_its_variants(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->assertOk();

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$variant]);
    }

    public function test_variants_can_be_generated_from_the_options(): void
    {
        $product = Product::factory()->create(['name' => 'Amani sofa']);
        $colour = $product->options()->create(['name' => 'Colour', 'position' => 1]);
        $colour->values()->createMany([['label' => 'Sand', 'position' => 1], ['label' => 'Forest', 'position' => 2]]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('generate', data: ['price' => 65000])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(6_500_000, $product->variants()->first()->price);
    }

    public function test_a_variant_price_is_entered_in_shillings_and_stored_in_cents(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['price' => 100_000]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->mountTableAction('edit', $variant)
            ->assertTableActionDataSet(['price' => 1000])
            ->setTableActionData(['price' => 72500])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(7_250_000, $variant->fresh()->price);
    }

    public function test_stock_is_adjusted_through_the_ledger(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('adjustStock', $variant, data: [
                'quantity' => 8,
                'reason' => StockMovementReason::Received->value,
                'note' => 'First delivery',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(8, $variant->fresh()->stock_on_hand);

        $movement = $variant->stockMovements()->first();
        $this->assertSame(8, $movement->quantity);
        $this->assertSame('First delivery', $movement->note);
        $this->assertTrue($movement->user->is($this->staff));
    }

    public function test_removing_more_stock_than_there_is_changes_nothing(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->withStock(1)->create();

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('adjustStock', $variant, data: [
                'quantity' => -3,
                'reason' => StockMovementReason::Damaged->value,
            ])
            ->assertNotified('Not enough stock');

        $this->assertSame(1, $variant->fresh()->stock_on_hand);
        $this->assertSame(0, $variant->stockMovements()->count());
    }

    public function test_product_photos_go_to_the_public_disk(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['gallery' => [UploadedFile::fake()->image('sofa.jpg', 800, 800)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $photo = $product->fresh()->getFirstMedia(Product::GALLERY);

        $this->assertNotNull($photo);
        $this->assertSame('public', $photo->disk);
        Storage::disk('public')->assertExists($photo->getPathRelativeToRoot());
        $this->assertTrue($photo->hasGeneratedConversion('thumb'));
        $this->assertStringNotContainsString('signature=', $photo->getUrl('thumb'));
    }

    public function test_the_products_list_shows_the_photo_of_the_colour_shown_first(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $colour = $product->options()->create(['name' => 'Colour', 'position' => 1]);
        $sand = $colour->values()->create(['label' => 'Sand', 'position' => 1]);
        $variant = ProductVariant::factory()->for($product)->create(['is_default' => true]);
        $variant->optionValues()->attach($sand);

        $this->assertNull($product->fresh()->mainPhoto());

        $photo = $sand->addMedia(UploadedFile::fake()->image('sand.jpg', 600, 600))->toMediaCollection(ProductOptionValue::IMAGES);

        $this->assertTrue($product->fresh()->mainPhoto()->is($photo));
        $this->get(ProductResource::getUrl('index'))->assertOk()->assertSee($photo->getFullUrl('thumb'), escape: false);
    }

    public function test_a_category_in_use_cannot_be_deleted_from_the_list_or_its_own_page(): void
    {
        $inUse = Category::factory()->create();
        Product::factory()->for($inUse)->create();
        $empty = Category::factory()->create();

        Livewire::test(ListCategories::class)
            ->assertTableActionDisabled('delete', $inUse)
            ->assertTableActionEnabled('delete', $empty);

        Livewire::test(EditCategory::class, ['record' => $inUse->getRouteKey()])->assertActionDisabled(DeleteAction::class);
        Livewire::test(EditCategory::class, ['record' => $empty->getRouteKey()])->assertActionEnabled(DeleteAction::class);

        $this->assertTrue($inUse->isInUse());
        $this->assertNotNull($inUse->fresh());
    }

    public function test_categories_are_listed_as_a_tree(): void
    {
        $dining = Category::factory()->create(['name' => 'Dining', 'position' => 2]);
        $living = Category::factory()->create(['name' => 'Living room', 'position' => 1]);
        $tables = Category::factory()->childOf($dining)->create(['name' => 'Dining tables', 'position' => 1]);
        $armchairs = Category::factory()->childOf($living)->create(['name' => 'Armchairs', 'position' => 2]);
        $sofas = Category::factory()->childOf($living)->create(['name' => 'Sofas', 'position' => 1]);

        Livewire::test(ListCategories::class)
            ->assertCanSeeTableRecords([$living, $sofas, $armchairs, $dining, $tables], inOrder: true);
    }

    public function test_marking_a_variant_as_default_clears_the_previous_default(): void
    {
        $product = Product::factory()->create();
        $first = ProductVariant::factory()->for($product)->create(['is_default' => true]);
        $second = ProductVariant::factory()->for($product)->create(['is_default' => false]);

        $second->update(['is_default' => true]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }
}
