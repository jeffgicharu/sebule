<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductOptionValue;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BrowsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);
        $this->withoutVite();
    }

    public function test_the_home_page_shows_rooms_collections_and_offers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeText('Furniture for the way Kenya lives.')
            ->assertSeeText('Shop by room')
            ->assertSee(route('shop.category', 'living-room'), escape: false)
            ->assertSee(route('shop.collection', 'amani'), escape: false)
            ->assertSeeText('On offer this month');
    }

    public function test_a_room_lists_the_products_of_its_sub_categories(): void
    {
        $this->get('/c/living-room')
            ->assertOk()
            ->assertSeeText('Living room')
            ->assertSeeText('20 products')
            ->assertSeeText('Amani 3-seater sofa')
            ->assertSeeText('Page 1 of 2');

        $this->get('/c/living-room?page=2')->assertOk()->assertSeeText('Page 2 of 2');
    }

    public function test_a_sub_category_lists_only_its_own_products(): void
    {
        $this->get('/c/sofas')
            ->assertOk()
            ->assertSeeText('6 products')
            ->assertSeeText('Naivasha 2-seater sofa')
            ->assertDontSeeText('Amani armchair')
            ->assertSeeText('KSh 98,000');
    }

    public function test_filters_narrow_the_list_and_stay_in_the_address(): void
    {
        $this->get('/c/sofas?colour=Forest')
            ->assertOk()
            ->assertSeeText('2 products')
            ->assertSeeText('Amani 3-seater sofa')
            ->assertDontSeeText('Elgon 3-seater sofa')
            ->assertSeeText('Clear filters');

        $this->get('/c/sofas?availability=made-to-order')
            ->assertSeeText('2 products')
            ->assertSeeText('Naivasha 3-seater sofa')
            ->assertDontSeeText('Watamu 2-seater sofa');

        $this->get('/c/sofas?price=over-100000')
            ->assertSeeText('2 products')
            ->assertSeeText('Elgon 3-seater sofa');

        $this->get('/c/sofas?price=under-20000')
            ->assertOk()
            ->assertSeeText('Nothing matches those filters.');
    }

    public function test_products_can_be_sorted_by_price(): void
    {
        $this->get('/c/sofas?sort=price-asc')->assertSeeTextInOrder(['Watamu 2-seater sofa', 'Amani 2-seater sofa', 'Elgon 3-seater sofa']);
        $this->get('/c/sofas?sort=price-desc')->assertSeeTextInOrder(['Elgon 3-seater sofa', 'Naivasha 3-seater sofa', 'Watamu 2-seater sofa']);
    }

    public function test_a_card_marks_offers_and_made_to_order_pieces(): void
    {
        $this->get('/c/sofas')
            ->assertSeeText('Offer')
            ->assertSeeText('Made to order')
            ->assertSeeText('KSh 84,000');
    }

    public function test_a_collection_page_lists_its_pieces(): void
    {
        $this->get('/collections/amani')
            ->assertOk()
            ->assertSeeText('5 pieces')
            ->assertSeeTextInOrder(['Amani 3-seater sofa', 'Amani 2-seater sofa', 'Amani armchair']);
    }

    public function test_a_product_page_shows_the_default_variant(): void
    {
        $this->get('/p/amani-3-seater-sofa')
            ->assertOk()
            ->assertSeeText('Amani 3-seater sofa')
            ->assertSeeText('KSh 98,000')
            ->assertSeeText('In stock and ready to deliver')
            ->assertSeeText('220 wide')
            ->assertSeeText('AMANI-3-SEATER-SOFA-SAND')
            ->assertSeeText('Amani collection')
            ->assertSeeText('2 years')
            ->assertSeeText('More sofas');
    }

    public function test_choosing_a_colour_changes_the_variant_shown(): void
    {
        $this->get('/p/amani-3-seater-sofa?v=clay')
            ->assertOk()
            ->assertSeeText('AMANI-3-SEATER-SOFA-CLAY')
            ->assertSeeText('Out of stock in Clay');

        $this->get('/p/amani-3-seater-sofa?v=forest')
            ->assertSeeText('AMANI-3-SEATER-SOFA-FOREST')
            ->assertSeeText('In stock and ready to deliver');

        $this->get('/p/tana-6-seater-dining-table?v=dark-walnut')
            ->assertSeeText('KSh 88,000')
            ->assertSeeText('Only 2 left in stock');

        $this->get('/p/amani-3-seater-sofa?v=not-a-colour')->assertOk()->assertSeeText('AMANI-3-SEATER-SOFA-SAND');
    }

    public function test_a_made_to_order_piece_shows_its_lead_time(): void
    {
        $this->get('/p/lamu-king-bed')->assertOk()->assertSeeText('Made to order in about 4 weeks');
    }

    public function test_a_product_on_offer_shows_its_earlier_price(): void
    {
        $this->get('/p/amani-2-seater-sofa')->assertSeeText('KSh 72,000')->assertSeeText('KSh 84,000');
    }

    public function test_unpublished_products_and_hidden_categories_are_not_found(): void
    {
        Product::where('slug', 'kito-table-lamp')->update(['status' => ProductStatus::Draft]);
        Category::where('slug', 'mirrors')->update(['is_active' => false]);
        Collection::where('slug', 'kito')->update(['is_active' => false]);

        $this->get('/p/kito-table-lamp')->assertNotFound();
        $this->get('/c/mirrors')->assertNotFound();
        $this->get('/collections/kito')->assertNotFound();
        $this->get('/c/table-lamps')->assertOk()->assertDontSeeText('Kito table lamp')->assertSeeText('3 products');
        $this->get('/p/no-such-product')->assertNotFound();
    }

    public function test_the_menu_follows_changes_staff_make(): void
    {
        $this->get('/')->assertSeeText('Pendant lights');

        Category::where('slug', 'pendant-lights')->first()->update(['name' => 'Ceiling lights']);

        $this->get('/')->assertSeeText('Ceiling lights')->assertDontSeeText('Pendant lights');
    }

    public function test_a_room_photo_says_which_colour_it_shows_when_another_is_chosen(): void
    {
        Storage::fake('public');

        $sofa = Product::where('slug', 'amani-3-seater-sofa')->with('options.values')->firstOrFail();
        $forest = $sofa->options->first()->values->firstWhere('label', 'Forest');

        $forest->addMedia(UploadedFile::fake()->image('amani-sofa-3s_forest_studio-34.jpg', 64, 64))->toMediaCollection(ProductOptionValue::IMAGES);
        $sofa->addMedia(UploadedFile::fake()->image('amani-sofa-3s_sand_room.jpg', 64, 64))->toMediaCollection(Product::GALLERY);

        $this->get('/p/amani-3-seater-sofa?v=forest')->assertOk()->assertSeeText('The room photo shows the piece in Sand.');
        $this->get('/p/amani-3-seater-sofa')->assertOk()->assertDontSeeText('The room photo shows');
    }

    public function test_a_list_can_be_narrowed_by_material_and_only_offers_the_materials_it_holds(): void
    {
        $living = $this->get(route('shop.category', 'living-room'))->assertOk();
        $offered = $living->viewData('materials');

        $this->assertArrayHasKey('mvule', $offered);
        $this->assertArrayHasKey('linen', $offered);
        // Nothing in the living room is stoneware, so it is not offered there.
        $this->assertArrayNotHasKey('stoneware', $offered);

        $linen = $this->get(route('shop.category', ['category' => 'living-room', 'material' => 'linen']))->assertOk()
            ->assertSee('<option value="linen" selected>', escape: false)
            ->viewData('products');

        $this->assertGreaterThan(0, $linen->total());
        $this->assertLessThan($living->viewData('products')->total(), $linen->total());
        $this->assertTrue(collect($linen->items())->every(fn ($product) => str_contains(strtolower($product->materials), 'linen')));

        // A material that is not one of ours changes nothing, whatever is typed into the address.
        $this->assertSame(
            $living->viewData('products')->total(),
            $this->get(route('shop.category', ['category' => 'living-room', 'material' => "x%' or 1=1 --"]))->assertOk()->viewData('products')->total(),
        );
    }

    public function test_a_list_can_be_narrowed_by_how_wide_the_piece_is(): void
    {
        $all = $this->get(route('shop.category', 'living-room'))->viewData('products')->total();
        $counts = [];

        foreach (['under-60' => [0, 60], '60-120' => [60, 120], '120-180' => [120, 180], 'over-180' => [180, 100000]] as $band => [$from, $below]) {
            $found = $this->get(route('shop.category', ['category' => 'living-room', 'width' => $band]))->assertOk()->viewData('products');
            $counts[$band] = $found->total();

            foreach ($found->items() as $product) {
                $this->assertTrue(
                    $product->variants()->where('is_active', true)->where('width_cm', '>=', $from)->where('width_cm', '<', $below)->exists(),
                    $product->name.' in '.$band,
                );
            }
        }

        $this->assertGreaterThan(0, $counts['over-180']);
        $this->assertGreaterThanOrEqual($all, array_sum($counts));
        $this->get(route('shop.category', ['category' => 'living-room', 'width' => 'over-180']))->assertSee('<option value="over-180" selected>', escape: false);
    }

    public function test_most_popular_puts_what_has_sold_most_lately_first(): void
    {
        $slow = Product::where('slug', 'amani-3-seater-sofa')->firstOrFail();
        $seller = Product::where('slug', 'naivasha-3-seater-sofa')->firstOrFail();
        $older = Product::where('slug', 'amani-2-seater-sofa')->firstOrFail();

        $sell = function (Product $product, int $quantity, int $daysAgo, string $status = 'delivered'): void {
            $variant = $product->variants()->firstOrFail();
            $orderId = DB::table('orders')->insertGetId([
                'number' => 'SB-'.random_int(100000, 999999), 'token' => Str::random(40), 'checkout_key' => (string) Str::uuid(), 'status' => $status,
                'customer_name' => 'Wanjiru Kamau', 'phone' => '254712345678', 'delivery_method' => 'collection',
                'subtotal' => 1, 'delivery_fee' => 0, 'total' => 1, 'vat_rate' => 1600, 'vat_amount' => 0,
                'paid_at' => now()->subDays($daysAgo), 'created_at' => now()->subDays($daysAgo), 'updated_at' => now(),
            ]);
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'sku' => $variant->sku,
                'unit_price' => 1, 'quantity' => $quantity, 'line_total' => $quantity, 'created_at' => now(), 'updated_at' => now(),
            ]);
        };

        $sell($slow, 1, 3);
        $sell($seller, 5, 10);
        $sell($older, 40, 200);              // Too long ago to count.
        $sell($slow, 30, 2, 'cancelled');    // Cancelled, so not a sale.

        $names = collect($this->get(route('shop.category', ['category' => 'sofas', 'sort' => 'popular']))->assertOk()
            ->assertSee('<option value="popular" selected>', escape: false)
            ->viewData('products')->items())->pluck('name')->all();

        $this->assertSame(['Naivasha 3-seater sofa', 'Amani 3-seater sofa'], array_slice($names, 0, 2));
        $this->assertGreaterThan(1, array_search('Amani 2-seater sofa', $names));
    }
}
