<?php

namespace Tests\Feature\Shop;

use App\Cart\CartManager;
use App\Enums\ProductStatus;
use App\Inventory\StockLedger;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);
        $this->withoutVite();
    }

    public function test_a_visitor_who_only_looks_gets_no_cart(): void
    {
        $this->get('/')->assertOk()->assertCookieMissing(CartManager::COOKIE);
        $this->get('/cart')->assertOk()->assertSeeText('There is nothing in your cart yet.')->assertCookieMissing(CartManager::COOKIE);

        $this->assertSame(0, Cart::count());
    }

    public function test_adding_a_piece_puts_it_in_the_cart_and_remembers_the_cart_for_months(): void
    {
        $forest = $this->variant('amani-3-seater-sofa', 'Forest');

        $response = $this->post('/cart/items', ['variant' => $forest->id, 'quantity' => 2])
            ->assertRedirect('/cart')
            ->assertSessionHas('shop.notice', 'The Amani 3-seater sofa in Forest is in your cart.');

        $cookie = $response->getCookie(CartManager::COOKIE);

        $this->assertNotNull($cookie);
        $this->assertGreaterThan(now()->addDays(80)->getTimestamp(), $cookie->getExpiresTime());
        $this->assertTrue($cookie->isHttpOnly());

        $this->carry($response)->get('/cart')->assertOk()
            ->assertSeeText('Amani 3-seater sofa')
            ->assertSeeText('Forest')
            ->assertSeeText('KSh 196,000')
            ->assertSeeText('2 pieces')
            ->assertSee('aria-label="Cart, 2 pieces"', escape: false)
            ->assertSee(route('checkout.details'), escape: false);
    }

    public function test_adding_the_same_piece_again_adds_to_its_quantity(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');

        $first = $this->post('/cart/items', ['variant' => $sand->id]);
        $this->carry($first)->post('/cart/items', ['variant' => $sand->id, 'quantity' => 2]);

        $this->assertSame(1, CartItem::count());
        $this->assertSame(3, CartItem::first()->quantity);
    }

    public function test_the_cart_marks_the_piece_that_has_just_arrived_and_only_then(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');
        $forest = $this->variant('amani-3-seater-sofa', 'Forest');

        $first = $this->post('/cart/items', ['variant' => $sand->id]);
        $second = $this->carry($first)->post('/cart/items', ['variant' => $forest->id])
            ->assertSessionHas('shop.added', CartItem::where('product_variant_id', $forest->id)->value('id'));

        $page = $this->carry($second)->get('/cart')->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, 'data-just-added'));
        $this->assertStringContainsString('Forest', Str::before(Str::after($page, 'data-just-added'), '</li>'));
        $this->assertStringContainsString('count-pop', $page);

        // Coming back to the cart later, nothing is new.
        $this->carry($second)->get('/cart')->assertOk()
            ->assertDontSee('data-just-added', escape: false)
            ->assertDontSee('count-pop', escape: false);
    }

    public function test_the_cart_never_holds_more_than_is_in_stock(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');
        $this->stockTo($sand, 2);

        $this->post('/cart/items', ['variant' => $sand->id, 'quantity' => 5])
            ->assertRedirect('/cart')
            ->assertSessionHas('shop.notice', 'Only 2 of the Amani 3-seater sofa in Sand are in stock, so that is how many are in your cart.');

        $this->assertSame(2, CartItem::first()->quantity);
    }

    public function test_a_piece_that_has_sold_out_cannot_be_added(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');
        $this->stockTo($sand, 0);

        $this->from('/p/amani-3-seater-sofa')->post('/cart/items', ['variant' => $sand->id])
            ->assertRedirect('/p/amani-3-seater-sofa')
            ->assertSessionHas('shop.problem', 'Sorry, the Amani 3-seater sofa in Sand has just sold out.');

        $this->assertSame(0, Cart::count());
        $this->get('/p/amani-3-seater-sofa')->assertSeeText('Out of stock')->assertDontSee('Add to cart');
    }

    public function test_a_piece_that_is_not_for_sale_cannot_be_added(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');

        $sand->update(['is_active' => false]);
        $this->post('/cart/items', ['variant' => $sand->id])->assertNotFound();

        $sand->update(['is_active' => true]);
        $sand->product->update(['status' => ProductStatus::Draft]);
        $this->post('/cart/items', ['variant' => $sand->id])->assertNotFound();

        $this->post('/cart/items', ['variant' => 999999])->assertNotFound();
        $this->post('/cart/items', [])->assertSessionHasErrors('variant');
    }

    public function test_a_made_to_order_piece_can_be_ordered_without_stock_up_to_a_limit(): void
    {
        $made = ProductVariant::query()->where('made_to_order', true)->where('is_active', true)->with('product')->firstOrFail();

        $this->post('/cart/items', ['variant' => $made->id, 'quantity' => 40])->assertRedirect('/cart');

        $this->assertSame(config('store.made_to_order_limit'), CartItem::first()->quantity);
    }

    public function test_the_quantity_can_be_changed_and_a_piece_taken_out(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');
        $this->stockTo($sand, 4);

        $added = $this->post('/cart/items', ['variant' => $sand->id]);
        $item = CartItem::first();

        $this->carry($added)->patch("/cart/items/{$item->id}", ['quantity' => 3])->assertRedirect('/cart');
        $this->assertSame(3, $item->fresh()->quantity);

        $this->carry($added)->patch("/cart/items/{$item->id}", ['quantity' => 9])
            ->assertSessionHas('shop.notice', 'Only 4 of the Amani 3-seater sofa in Sand can be ordered at the moment.');
        $this->assertSame(4, $item->fresh()->quantity);

        $this->carry($added)->patch("/cart/items/{$item->id}", ['quantity' => 0])->assertSessionHasErrors('quantity');

        $this->carry($added)->delete("/cart/items/{$item->id}")
            ->assertRedirect('/cart')
            ->assertSessionHas('shop.notice', 'The Amani 3-seater sofa in Sand has been taken out of your cart.');

        $this->assertSame(0, CartItem::count());
        $this->carry($added)->get('/cart')->assertSeeText('There is nothing in your cart yet.');
    }

    public function test_nobody_can_change_a_cart_that_is_not_theirs(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');

        $this->post('/cart/items', ['variant' => $sand->id]);
        $item = CartItem::first();

        // A second browser, with a cart of its own and with none.
        $other = $this->post('/cart/items', ['variant' => $this->variant('amani-armchair', 'Forest')->id]);

        $this->carry($other)->patch("/cart/items/{$item->id}", ['quantity' => 5])->assertNotFound();
        $this->carry($other)->delete("/cart/items/{$item->id}")->assertNotFound();

        $this->defaultCookies = [];
        $this->delete("/cart/items/{$item->id}")->assertNotFound();

        $this->assertSame(1, $item->fresh()->quantity);
    }

    public function test_the_cart_says_when_a_price_has_changed_since_the_piece_was_added(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');

        $added = $this->post('/cart/items', ['variant' => $sand->id]);
        $this->carry($added)->get('/cart')->assertDontSeeText('The price has gone');

        $sand->update(['price' => 10500000]);

        $this->carry($added)->get('/cart')
            ->assertSeeText('The price has gone up from KSh 98,000 since you added this.')
            ->assertSeeText('KSh 105,000')
            ->assertSee(route('checkout.details'), escape: false);

        $sand->update(['price' => 9000000]);
        $this->carry($added)->get('/cart')->assertSeeText('The price has gone down from KSh 98,000 since you added this.');
    }

    public function test_the_cart_stops_checkout_when_stock_has_run_short_and_offers_the_way_out(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');
        $this->stockTo($sand, 5);

        $added = $this->post('/cart/items', ['variant' => $sand->id, 'quantity' => 4]);
        $item = CartItem::first();

        $this->stockTo($sand, 2);

        $this->carry($added)->get('/cart')
            ->assertSeeText('Only 2 are left in stock.')
            ->assertSeeText('Change to 2')
            ->assertSeeText('Sort out the piece marked in your cart before you check out.')
            ->assertDontSee('href="'.route('checkout.details').'"', escape: false);

        $this->carry($added)->get('/checkout')->assertRedirect('/cart');

        $this->carry($added)->patch("/cart/items/{$item->id}", ['quantity' => 2]);
        $this->carry($added)->get('/cart')->assertDontSeeText('left in stock.')->assertSee('href="'.route('checkout.details').'"', escape: false);

        $this->stockTo($sand, 0);
        $this->carry($added)->get('/cart')->assertSeeText('This has sold out since you chose it. Take it out to carry on.');

        $this->stockTo($sand, 3);
        $sand->update(['is_active' => false]);
        $this->carry($added)->get('/cart')->assertSeeText('This piece is no longer for sale. Take it out to carry on.');
    }

    public function test_carts_nobody_has_touched_for_months_are_cleared_away(): void
    {
        $this->post('/cart/items', ['variant' => $this->variant('amani-3-seater-sofa', 'Sand')->id]);

        Cart::query()->toBase()->update(['updated_at' => now()->subDays(100)]);
        $this->post('/cart/items', ['variant' => $this->variant('amani-armchair', 'Forest')->id]);

        $this->artisan('model:prune', ['--model' => [Cart::class]])->assertSuccessful();

        $this->assertSame(1, Cart::count());
        $this->assertSame(1, CartItem::count());
    }

    /** Send the cart cookie a response set, as the browser would on its next request. */
    private function carry(TestResponse $response): static
    {
        return $this->withCookie(CartManager::COOKIE, $response->getCookie(CartManager::COOKIE)->getValue());
    }

    private function variant(string $product, string $label): ProductVariant
    {
        return Product::query()->where('slug', $product)->firstOrFail()
            ->variants()->with('product', 'optionValues.option')->get()
            ->firstOrFail(fn (ProductVariant $variant) => $variant->label() === $label);
    }

    private function stockTo(ProductVariant $variant, int $quantity): void
    {
        app(StockLedger::class)->setTo($variant, $quantity);

        if ($quantity > 0 && $variant->made_to_order) {
            $variant->update(['made_to_order' => false]);
        }
    }
}
