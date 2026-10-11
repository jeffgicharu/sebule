<?php

namespace Tests\Feature\Shop;

use App\Catalogue\ProductListing;
use App\Catalogue\ProductSearch;
use App\Catalogue\SearchIndex;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Searching the shop: what is found, what is forgiven, and what comes first.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);
        $this->withoutVite();
    }

    public function test_a_search_finds_products_by_name_and_puts_those_first(): void
    {
        $this->get(route('shop.search', ['q' => 'coffee table']))->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', escape: false)
            ->assertSeeText('pieces for “coffee table”')
            ->assertSeeText('Ngong coffee table')
            ->assertSeeText('Tsavo coffee table')
            ->assertDontSeeText('Amani 3-seater sofa');

        $found = $this->names('coffee table');

        $this->assertStringContainsString('coffee table', strtolower($found[0]));
        $this->assertContains('Ngong coffee table', $found);
    }

    public function test_a_slip_of_the_fingers_still_finds_what_was_meant(): void
    {
        foreach (['soffa' => 'Amani 3-seater sofa', 'dinning table' => 'Tana 6-seater dining table', 'cofee' => 'Ngong coffee table', 'wardrob' => 'Lamu 2-door wardrobe', 'outdor' => 'Diani outdoor sofa'] as $typed => $meant) {
            $this->assertContains($meant, $this->names($typed), $typed);
        }

        $this->get(route('shop.search', ['q' => 'soffa']))->assertOk()->assertSeeText('Amani 3-seater sofa');
    }

    public function test_every_word_has_to_match(): void
    {
        $found = $this->names('lamu bed');

        $this->assertNotEmpty($found);
        $this->assertContains('Lamu queen bed', $found);
        $this->assertNotContains('Mara queen bed', $found);
        $this->assertNotContains('Lamu cane armchair', array_slice($found, 0, 3));
    }

    public function test_a_short_word_is_not_mistaken_for_its_neighbour(): void
    {
        $found = $this->names('lamu');

        $this->assertContains('Lamu queen bed', $found);
        $this->assertNotContains('Kito table lamp', $found);
    }

    public function test_products_are_found_by_room_category_collection_colour_and_material(): void
    {
        $this->assertContains('Lamu queen bed', $this->names('bedroom'));
        $this->assertContains('Kito table lamp', $this->names('lamps'));
        $this->assertContains('Tana 6-seater dining table', $this->names('tana'));
        $this->assertContains('Naivasha 3-seater sofa', $this->names('olive'));
        $this->assertContains('Tana 6-seater dining table', $this->names('mvule'));
    }

    public function test_everyday_colour_words_find_the_stores_own_colours(): void
    {
        $green = $this->names('green sofa');
        $grey = $this->names('grey sofa');

        $this->assertContains('Naivasha 3-seater sofa', $green);
        $this->assertContains('Naivasha 3-seater sofa', $grey);
        $this->assertSame($grey, $this->names('gray sofa'));
        $this->assertNotContains('Tana 6-seater dining table', $green);
    }

    public function test_products_not_on_sale_are_never_found(): void
    {
        Product::where('slug', 'ngong-coffee-table')->firstOrFail()->update(['status' => ProductStatus::Draft]);
        ProductVariant::whereRelation('product', 'slug', 'tsavo-coffee-table')->update(['is_active' => false]);

        $found = $this->names('coffee table');

        $this->assertNotContains('Ngong coffee table', $found);
        $this->assertNotContains('Tsavo coffee table', $found);
        $this->assertContains('Ngong round coffee table', $found);
    }

    public function test_a_search_offers_the_categories_it_names(): void
    {
        $this->assertSame(['Dining tables'], ProductSearch::categories('dinning table')->pluck('name')->all());
        $this->assertSame(['Sofas'], ProductSearch::categories('soffa')->pluck('name')->all());
        $this->assertSame(['Bedroom', 'Beds', 'Bedside tables'], ProductSearch::categories('bed')->pluck('name')->all());
        $this->assertSame([], ProductSearch::categories('lamu')->pluck('name')->all());

        $sofas = Category::where('name', 'Sofas')->firstOrFail();

        $this->get(route('shop.search', ['q' => 'sofa']))->assertOk()
            ->assertSeeText('Go straight to')
            ->assertSee(route('shop.category', $sofas));

        Category::where('name', 'Sofas')->update(['is_active' => false]);
        $this->assertSame([], ProductSearch::categories('sofa')->pluck('name')->all());
    }

    public function test_the_search_page_with_nothing_typed_asks_and_offers_the_rooms(): void
    {
        foreach ([[], ['q' => ''], ['q' => '   '], ['q' => 'a']] as $query) {
            $this->get(route('shop.search', $query))->assertOk()
                ->assertSeeText('What are you looking for?')
                ->assertSee(route('shop.category', 'living-room'))
                ->assertDontSeeText('pieces for');
        }
    }

    public function test_a_search_that_finds_nothing_says_so_and_offers_the_rooms(): void
    {
        $this->get(route('shop.search', ['q' => 'trampoline']))->assertOk()
            ->assertSeeText('Nothing matched “trampoline”.')
            ->assertSee(route('shop.category', 'bedroom'))
            ->assertDontSeeText('Sort by');
    }

    public function test_what_was_typed_is_shown_safely_and_kept_in_the_box(): void
    {
        $this->get(route('shop.search', ['q' => '<script>alert(1)</script> sofa']))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('value="&lt;script&gt;alert(1)&lt;/script&gt; sofa"', escape: false);

        // The signs that mean "anything" to the database are only themselves here.
        $this->assertSame([], $this->names('%%'));
        $this->assertSame([], $this->names('__'));
        $this->assertSame([], $this->names("sofa'; drop table products; --x"));
        $this->assertGreaterThan(0, Product::count());
    }

    public function test_a_very_long_search_is_cut_to_what_is_kept(): void
    {
        $this->assertLessThanOrEqual(80, strlen(ProductSearch::tidy(str_repeat('sofa ', 40))));
        $this->assertSame('sofa sofa', ProductSearch::tidy("  sofa \n\t sofa  "));
        $this->assertCount(6, ProductSearch::words('one two three four five six seven eight'));

        $this->get(route('shop.search', ['q' => str_repeat('sofa ', 400)]))->assertOk();
    }

    public function test_results_can_be_sorted_by_price_and_paged_without_losing_the_search(): void
    {
        $response = $this->get(route('shop.search', ['q' => 'sofa', 'sort' => 'price-asc']))->assertOk()
            ->assertSee('<option value="price-asc" selected>', escape: false);

        $prices = $response->viewData('products')->pluck('price_from')->all();
        $sorted = $prices;
        sort($sorted);

        $this->assertSame($sorted, $prices);
        $this->assertGreaterThan(1, count($prices));

        $paged = $this->get(route('shop.search', ['q' => 'table']))->assertOk();
        $this->assertTrue($paged->viewData('products')->hasPages());
        $paged->assertSee('q=table&amp;page=2', escape: false);

        // An unknown sort is ignored.
        $this->get(route('shop.search', ['q' => 'sofa', 'sort' => 'cheapest; drop']))->assertOk()
            ->assertSee('<option value="relevance" selected>', escape: false);
    }

    public function test_a_product_is_found_by_its_new_name_as_soon_as_it_is_renamed(): void
    {
        $product = Product::where('slug', 'ngong-coffee-table')->firstOrFail();

        $this->assertSame([], $this->names('ottoman'));

        $product->update(['name' => 'Ngong ottoman']);

        $this->assertSame(['Ngong ottoman'], $this->names('ottoman'));
    }

    public function test_a_new_colour_and_a_renamed_category_change_what_a_product_is_found_by(): void
    {
        $product = Product::where('slug', 'ngong-coffee-table')->firstOrFail();

        $product->options()->firstOrFail()->values()->create(['label' => 'Mustard', 'position' => 9]);
        $this->assertContains('Ngong coffee table', $this->names('yellow'));

        $product->category->update(['name' => 'Cocktail tables']);
        $this->assertContains('Ngong coffee table', $this->names('cocktail'));
    }

    public function test_the_index_command_catches_what_no_single_change_announces(): void
    {
        $product = Product::where('slug', 'ngong-coffee-table')->firstOrFail();
        $collection = Collection::create(['name' => 'Savannah', 'slug' => 'savannah', 'is_active' => true]);
        $collection->products()->attach($product);

        $this->assertSame([], $this->names('savannah'));

        $this->artisan('catalogue:index-search')->expectsOutputToContain('Wrote the search text of 80 products.')->assertSuccessful();

        $this->assertSame(['Ngong coffee table'], $this->names('savannah'));
        $this->assertStringContainsString('savannah', SearchIndex::textFor($product->fresh()));
    }

    public function test_the_header_of_every_page_leads_to_search(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('aria-label="Search"', escape: false)->assertSee(route('shop.search'));
    }

    public function test_searching_is_rationed(): void
    {
        foreach (range(1, 40) as $search) {
            $this->get(route('shop.search', ['q' => 'sofa']))->assertOk();
        }

        $this->get(route('shop.search', ['q' => 'sofa']))->assertTooManyRequests();
    }

    /**
     * The names found for a search, best match first.
     *
     * @return list<string>
     */
    private function names(string $typed): array
    {
        $products = $this->get(route('shop.search', ['q' => $typed]))->assertOk()->viewData('products');

        if ($products === null || $products->isEmpty()) {
            return [];
        }

        return ProductSearch::apply(ProductListing::cards(), $typed)
            ->orderByDesc('relevance')->orderBy('products.id')->get()->pluck('name')->all();
    }
}
