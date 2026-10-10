<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductStatus;
use App\Models\Product;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * What each page tells browsers, search engines and link previews about itself.
 */
class PageMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);
        $this->withoutVite();
    }

    public function test_the_home_page_names_the_store_and_describes_it(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertSee('<title>Sebule | Furniture and homeware made in Kenya</title>', escape: false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', escape: false)
            ->assertSee('<meta property="og:type" content="website">', escape: false)
            ->assertSee('<meta property="og:image:width" content="1200">', escape: false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', escape: false)
            ->assertSee('rel="icon"', escape: false)
            ->assertSee('rel="manifest"', escape: false);

        $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]{80,160}">/', $response->getContent());

        $store = $this->node($response, 'FurnitureStore');

        $this->assertSame('Sebule', $store['name']);
        $this->assertSame('Nairobi', $store['address']['addressLocality']);
        $this->assertSame('KES', $store['currenciesAccepted']);
        $this->assertSame('Sebule', $this->node($response, 'WebSite')['name']);
    }

    public function test_a_sub_category_is_titled_with_its_room_and_lists_its_products(): void
    {
        $response = $this->get('/c/sofas')->assertOk()
            ->assertSee('<title>Sofas | Living room | Sebule</title>', escape: false)
            ->assertSee('<link rel="canonical" href="'.route('shop.category', 'sofas').'">', escape: false);

        $this->assertSame(
            ['Sebule', 'Living room', 'Sofas'],
            array_column($this->node($response, 'BreadcrumbList')['itemListElement'], 'name'),
        );

        $list = $this->node($response, 'CollectionPage')['mainEntity'];

        $this->assertSame(6, $list['numberOfItems']);
        $this->assertSame(route('shop.product', 'amani-3-seater-sofa'), $list['itemListElement'][0]['url']);
    }

    public function test_a_filtered_list_is_filed_under_the_address_without_filters(): void
    {
        $this->get('/c/sofas?colour=Forest&sort=price-asc')->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('shop.category', 'sofas').'">', escape: false);
    }

    public function test_a_later_page_of_a_room_has_its_own_title_and_address(): void
    {
        $this->get('/c/living-room?page=2')->assertOk()
            ->assertSee('<title>Living room, page 2 | Sebule</title>', escape: false)
            ->assertSee('<link rel="canonical" href="'.route('shop.category', ['category' => 'living-room', 'page' => 2]).'">', escape: false);
    }

    public function test_a_collection_is_titled_and_described(): void
    {
        $response = $this->get('/collections/amani')->assertOk()
            ->assertSee('<title>Amani collection | Sebule</title>', escape: false);

        $this->assertSame('Amani collection', $this->node($response, 'CollectionPage')['name']);
    }

    public function test_a_product_gives_its_price_and_an_offer_for_each_colour(): void
    {
        $product = Product::query()->where('slug', 'amani-3-seater-sofa')->with('variants')->firstOrFail();

        $response = $this->get('/p/amani-3-seater-sofa')->assertOk()
            ->assertSee('<title>Amani 3-seater sofa | Sebule</title>', escape: false)
            ->assertSee('<meta property="og:type" content="product">', escape: false)
            ->assertSee('<meta property="product:price:amount" content="98000.00">', escape: false)
            ->assertSee('<meta property="product:price:currency" content="KES">', escape: false);

        $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]*KSh 98,000 including VAT, delivered across Kenya\.">/', $response->getContent());

        $described = $this->node($response, 'Product');

        $this->assertSame('Amani 3-seater sofa', $described['name']);
        $this->assertSame('Sebule', $described['brand']['name']);
        $this->assertSame('Living room > Sofas', $described['category']);
        $this->assertSame('AggregateOffer', $described['offers']['@type']);
        $this->assertSame($product->variants->count(), $described['offers']['offerCount']);
        $this->assertSame('98000.00', $described['offers']['lowPrice']);
        $this->assertSame('KES', $described['offers']['offers'][0]['priceCurrency']);
        $this->assertStringStartsWith('https://schema.org/', $described['offers']['offers'][0]['availability']);
    }

    public function test_a_chosen_colour_is_in_the_title_but_not_in_the_address_search_engines_file(): void
    {
        $this->get('/p/amani-3-seater-sofa?v=forest')->assertOk()
            ->assertSee('<title>Amani 3-seater sofa in Forest | Sebule</title>', escape: false)
            ->assertSee('<link rel="canonical" href="'.route('shop.product', 'amani-3-seater-sofa').'">', escape: false);
    }

    public function test_the_sitemap_lists_what_is_on_sale_and_nothing_else(): void
    {
        Product::query()->where('slug', 'amani-armchair')->update(['status' => ProductStatus::Draft]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $addresses = [];

        foreach (simplexml_load_string($response->getContent())->url as $page) {
            $addresses[] = (string) $page->loc;
        }

        $this->assertContains(route('home'), $addresses);
        $this->assertContains(route('shop.category', 'living-room'), $addresses);
        $this->assertContains(route('shop.category', 'sofas'), $addresses);
        $this->assertContains(route('shop.collection', 'amani'), $addresses);
        $this->assertContains(route('shop.product', 'amani-3-seater-sofa'), $addresses);
        $this->assertNotContains(route('shop.product', 'amani-armchair'), $addresses);
        $this->assertSame(count($addresses), count(array_unique($addresses)));
    }

    public function test_search_engines_are_pointed_at_the_sitemap_and_kept_out_of_the_back_office(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('/sitemap.xml', $robots);
    }

    public function test_the_icons_a_browser_asks_for_exist(): void
    {
        foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'manifest.json', 'images/site/share-1200.jpg'] as $file) {
            $this->assertGreaterThan(0, filesize(public_path($file)), $file);
        }

        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Sebule', $manifest['name']);
    }

    public function test_a_missing_page_is_answered_by_the_store_and_not_a_bare_error(): void
    {
        $this->get('/p/no-such-piece')->assertNotFound()
            ->assertSee('<title>Page not found | Sebule</title>', escape: false)
            ->assertSeeText('We cannot find that page.')
            ->assertSee(route('shop.category', 'living-room'), escape: false);
    }

    /**
     * The node of the given type from the page's structured data.
     *
     * @return array<string, mixed>
     */
    private function node(TestResponse $response, string $type): array
    {
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $found), 'The page has no structured data.');

        $data = json_decode($found[1], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org', $data['@context']);

        $node = collect($data['@graph'])->firstWhere('@type', $type);

        $this->assertNotNull($node, "The page does not describe a {$type}.");

        return $node;
    }
}
