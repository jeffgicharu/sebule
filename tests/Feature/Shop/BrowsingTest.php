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
}
