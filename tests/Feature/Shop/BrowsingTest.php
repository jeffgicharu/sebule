<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Furniture for the way Kenya lives.')
            ->assertSee('Shop by room')
            ->assertSee(route('shop.category', 'living-room'), escape: false)
            ->assertSee(route('shop.collection', 'amani'), escape: false)
            ->assertSee('On offer this month')
            ->assertSee('Website by');
    }

    public function test_a_room_lists_the_products_of_its_sub_categories(): void
    {
        $this->get('/c/living-room')
            ->assertOk()
            ->assertSee('Living room')
            ->assertSee('20 products')
            ->assertSee('Amani 3-seater sofa')
            ->assertSee('Page 1 of 2');

        $this->get('/c/living-room?page=2')->assertOk()->assertSee('Page 2 of 2');
    }

    public function test_a_sub_category_lists_only_its_own_products(): void
    {
        $this->get('/c/sofas')
            ->assertOk()
            ->assertSee('6 products')
            ->assertSee('Naivasha 2-seater sofa')
            ->assertDontSee('Amani armchair')
            ->assertSee('KSh 98,000');
    }

    public function test_filters_narrow_the_list_and_stay_in_the_address(): void
    {
        $this->get('/c/sofas?colour=Forest')
            ->assertOk()
            ->assertSee('2 products')
            ->assertSee('Amani 3-seater sofa')
            ->assertDontSee('Elgon 3-seater sofa')
            ->assertSee('Clear filters');

        $this->get('/c/sofas?availability=made-to-order')
            ->assertSee('2 products')
            ->assertSee('Naivasha 3-seater sofa')
            ->assertDontSee('Watamu 2-seater sofa');

        $this->get('/c/sofas?price=over-100000')
            ->assertSee('2 products')
            ->assertSee('Elgon 3-seater sofa');

        $this->get('/c/sofas?price=under-20000')
            ->assertOk()
            ->assertSee('Nothing matches those filters.');
    }

    public function test_products_can_be_sorted_by_price(): void
    {
        $this->get('/c/sofas?sort=price-asc')->assertSeeInOrder(['Watamu 2-seater sofa', 'Amani 2-seater sofa', 'Elgon 3-seater sofa']);
        $this->get('/c/sofas?sort=price-desc')->assertSeeInOrder(['Elgon 3-seater sofa', 'Naivasha 3-seater sofa', 'Watamu 2-seater sofa']);
    }

    public function test_a_card_marks_offers_and_made_to_order_pieces(): void
    {
        $this->get('/c/sofas')
            ->assertSee('Offer')
            ->assertSee('Made to order')
            ->assertSee('KSh 84,000');
    }

    public function test_a_collection_page_lists_its_pieces(): void
    {
        $this->get('/collections/amani')
            ->assertOk()
            ->assertSee('5 pieces')
            ->assertSeeInOrder(['Amani 3-seater sofa', 'Amani 2-seater sofa', 'Amani armchair']);
    }

    public function test_a_product_page_shows_the_default_variant(): void
    {
        $this->get('/p/amani-3-seater-sofa')
            ->assertOk()
            ->assertSee('Amani 3-seater sofa')
            ->assertSee('KSh 98,000')
            ->assertSee('In stock and ready to deliver')
            ->assertSee('220 wide')
            ->assertSee('AMANI-3-SEATER-SOFA-SAND')
            ->assertSee('Amani collection')
            ->assertSee('2 years')
            ->assertSee('More sofas');
    }

    public function test_choosing_a_colour_changes_the_variant_shown(): void
    {
        $this->get('/p/amani-3-seater-sofa?v=clay')
            ->assertOk()
            ->assertSee('AMANI-3-SEATER-SOFA-CLAY')
            ->assertSee('Out of stock in Clay');

        $this->get('/p/amani-3-seater-sofa?v=forest')
            ->assertSee('AMANI-3-SEATER-SOFA-FOREST')
            ->assertSee('In stock and ready to deliver');

        $this->get('/p/tana-6-seater-dining-table?v=dark-walnut')
            ->assertSee('KSh 88,000')
            ->assertSee('Only 2 left in stock');

        $this->get('/p/amani-3-seater-sofa?v=not-a-colour')->assertOk()->assertSee('AMANI-3-SEATER-SOFA-SAND');
    }

    public function test_a_made_to_order_piece_shows_its_lead_time(): void
    {
        $this->get('/p/lamu-king-bed')->assertOk()->assertSee('Made to order in about 4 weeks');
    }

    public function test_a_product_on_offer_shows_its_earlier_price(): void
    {
        $this->get('/p/amani-2-seater-sofa')->assertSee('KSh 72,000')->assertSee('KSh 84,000');
    }

    public function test_unpublished_products_and_hidden_categories_are_not_found(): void
    {
        Product::where('slug', 'kito-table-lamp')->update(['status' => ProductStatus::Draft]);
        Category::where('slug', 'mirrors')->update(['is_active' => false]);
        Collection::where('slug', 'kito')->update(['is_active' => false]);

        $this->get('/p/kito-table-lamp')->assertNotFound();
        $this->get('/c/mirrors')->assertNotFound();
        $this->get('/collections/kito')->assertNotFound();
        $this->get('/c/table-lamps')->assertOk()->assertDontSee('Kito table lamp')->assertSee('3 products');
        $this->get('/p/no-such-product')->assertNotFound();
    }

    public function test_the_menu_follows_changes_staff_make(): void
    {
        $this->get('/')->assertSee('Pendant lights');

        Category::where('slug', 'pendant-lights')->first()->update(['name' => 'Ceiling lights']);

        $this->get('/')->assertSee('Ceiling lights')->assertDontSee('Pendant lights');
    }
}
