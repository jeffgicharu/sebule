<?php

namespace Tests\Feature\Shop;

use App\Cart\CartManager;
use App\Enums\DeliveryMethod;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Inventory\StockLedger;
use App\Models\Cart;
use App\Models\County;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    /** What a customer in Kiambu types into the details page. */
    private const DETAILS = [
        'name' => 'Wanjiru Kamau',
        'phone' => '0712 345 678',
        'email' => 'wanjiru@example.com',
        'method' => 'delivery',
        'town' => 'Ruaka',
        'estate' => 'Maple Court, Limuru Road',
        'house' => 'B4',
        'directions' => 'Green gate after the petrol station',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);
        $this->withoutVite();
    }

    public function test_checkout_needs_something_in_the_cart(): void
    {
        $this->get('/checkout')->assertRedirect('/cart');
        $this->get('/checkout/review')->assertRedirect('/cart');
    }

    public function test_the_details_page_asks_for_no_account_and_lists_every_county(): void
    {
        $this->fillCart();

        $this->get('/checkout')->assertOk()
            ->assertSeeText('No account needed.')
            ->assertSeeText('Amani 3-seater sofa')
            ->assertSee('data-note="Delivery to Kiambu is KSh 2,500, in 2 to 4 working days."', escape: false)
            ->assertSeeText('Northern and far counties');

        $this->assertSame(47, County::query()->deliveredTo()->count());
    }

    public function test_the_details_are_checked_and_each_mistake_says_what_to_do(): void
    {
        $this->fillCart();

        $this->post('/checkout/details', ['method' => 'delivery', 'phone' => '020 123 4567', 'email' => 'not-an-email'])
            ->assertSessionHasErrors([
                'name' => 'Tell us who the order is for.',
                'phone' => 'Enter a Kenyan mobile number, such as 0712 345 678.',
                'email',
                'county_id' => 'Choose the county we are delivering to.',
                'town' => 'Tell us the town or area.',
                'estate' => 'Tell us the estate, building or street.',
            ])
            ->assertSessionDoesntHaveErrors(['house', 'directions']);

        $this->post('/checkout/details', ['name' => 'Wanjiru Kamau', 'phone' => '0712345678'])
            ->assertSessionHasErrors(['method' => 'Choose delivery or collection.']);

        $this->post('/checkout/details', self::DETAILS + ['county_id' => 999])->assertSessionHasErrors('county_id');
    }

    public function test_collection_needs_no_address_and_an_email_is_optional(): void
    {
        $this->fillCart();

        $this->post('/checkout/details', ['name' => 'Otieno Odhiambo', 'phone' => '+254 110 123 456', 'method' => 'collection', 'town' => 'ignored'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/checkout/review');

        $this->get('/checkout/review')->assertOk()
            ->assertSeeText('Otieno Odhiambo')
            ->assertSeeText('0110 123 456')
            ->assertSeeText('Collection from the showroom')
            ->assertSeeText('Free')
            ->assertSeeTextInOrder(['Total to pay', 'KSh 98,000'])
            ->assertDontSeeText('ignored');
    }

    public function test_the_review_shows_delivery_the_total_and_the_vat_in_it(): void
    {
        $this->fillCart();
        $this->giveDetails();

        $this->get('/checkout/review')->assertOk()
            ->assertSeeText('Wanjiru Kamau')
            ->assertSeeText('0712 345 678')
            ->assertSeeText('B4, Maple Court, Limuru Road, Ruaka')
            ->assertSeeText('Kiambu County')
            ->assertSeeText('Green gate after the petrol station')
            ->assertSeeTextInOrder(['Delivery to Kiambu', 'KSh 2,500'])
            ->assertSeeTextInOrder(['Total to pay', 'KSh 100,500'])
            ->assertSeeText('Includes KSh 13,862.07 VAT at 16%')
            ->assertSeeText('Place order and pay');
    }

    public function test_the_review_cannot_be_reached_before_the_details_are_given(): void
    {
        $this->fillCart();

        $this->get('/checkout/review')->assertRedirect('/checkout');
    }

    public function test_the_details_are_remembered_when_the_customer_goes_back_to_change_them(): void
    {
        $this->fillCart();
        $this->giveDetails();

        $this->get('/checkout')->assertOk()
            ->assertSee('value="Wanjiru Kamau"', escape: false)
            ->assertSee('value="0712 345 678"', escape: false)
            ->assertSee('value="Ruaka"', escape: false);
    }

    public function test_placing_the_order_keeps_its_own_copy_takes_the_stock_and_empties_the_cart(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');
        $before = $sand->stock_on_hand;

        $this->fillCart(quantity: 2);
        $this->giveDetails();

        $response = $this->place();
        $order = Order::with('items', 'events')->sole();

        $response->assertRedirect(route('orders.pay', $order))->assertCookieExpired(CartManager::COOKIE);

        $this->assertMatchesRegularExpression('/^SB-\d{6}$/', $order->number);
        $this->assertSame(40, strlen($order->token));
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('Wanjiru Kamau', $order->customer_name);
        $this->assertSame('254712345678', $order->phone);
        $this->assertSame('wanjiru@example.com', $order->email);
        $this->assertFalse($order->marketing_opt_in);
        $this->assertSame(DeliveryMethod::Delivery, $order->delivery_method);
        $this->assertSame(['Kiambu', 'Ruaka', 'Around Nairobi'], [$order->county, $order->town, $order->zone_name]);
        $this->assertSame([19600000, 250000, 19850000, 1600, 2737931], [$order->subtotal, $order->delivery_fee, $order->total, $order->vat_rate, $order->vat_amount]);
        $this->assertTrue($order->held_until->between(now()->addMinutes(14), now()->addMinutes(16)));

        $line = $order->items->sole();
        $this->assertSame(['Amani 3-seater sofa', 'Sand', $sand->sku, 9800000, 2, 19600000], [$line->product_name, $line->variant_label, $line->sku, $line->unit_price, $line->quantity, $line->line_total]);
        $this->assertTrue($line->stock_taken);

        $this->assertSame($before - 2, $sand->fresh()->stock_on_hand);
        $movement = StockMovement::query()->latest('id')->first();
        $this->assertSame([-2, StockMovementReason::Sold, 'Order '.$order->number], [$movement->quantity, $movement->reason, $movement->note]);
        $this->assertTrue($movement->source->is($order));

        $this->assertSame([OrderEventType::Placed], $order->events->pluck('type')->all());
        $this->assertSame(0, Cart::count());

        // Later changes to the catalogue never rewrite the order.
        $sand->update(['price' => 1]);
        $sand->product->update(['name' => 'Something else']);
        $this->assertSame(9800000, $line->fresh()->unit_price);
        $this->assertSame('Amani 3-seater sofa', $line->fresh()->product_name);
    }

    public function test_a_made_to_order_piece_is_ordered_without_touching_stock(): void
    {
        $made = ProductVariant::query()->where('made_to_order', true)->where('is_active', true)->firstOrFail();

        $this->carry($this->post('/cart/items', ['variant' => $made->id]));
        $this->giveDetails();
        $this->place();

        $line = Order::sole()->items->sole();

        $this->assertTrue($line->made_to_order);
        $this->assertSame($made->lead_time_weeks, $line->lead_time_weeks);
        $this->assertFalse($line->stock_taken);
        $this->assertSame(0, StockMovement::query()->where('reason', StockMovementReason::Sold)->count());
    }

    public function test_the_customer_can_opt_in_to_marketing_and_only_by_ticking_the_box(): void
    {
        $this->fillCart();
        $this->giveDetails(['marketing' => '1']);
        $this->place();

        $this->assertTrue(Order::sole()->marketing_opt_in);
    }

    public function test_placing_the_same_checkout_twice_gives_one_order_and_takes_the_stock_once(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');
        $before = $sand->stock_on_hand;

        $this->fillCart();
        $this->giveDetails();

        $key = session('checkout.key');
        $form = ['key' => $key, 'total' => 10050000];

        $first = $this->post('/checkout', $form);
        $order = Order::sole();

        // A double tap, a refresh, or a retry after the connection dropped.
        $this->post('/checkout', $form)->assertRedirect(route('orders.pay', $order));
        $this->post('/checkout', $form)->assertRedirect(route('orders.pay', $order));

        $first->assertRedirect(route('orders.pay', $order));
        $this->assertSame(1, Order::count());
        $this->assertSame($before - 1, $sand->fresh()->stock_on_hand);
    }

    public function test_a_later_cart_is_left_alone_when_an_old_checkout_is_sent_again(): void
    {
        $this->fillCart();
        $this->giveDetails();

        $form = ['key' => session('checkout.key'), 'total' => 10050000];
        $this->post('/checkout', $form);

        $this->defaultCookies = [];
        $this->carry($this->post('/cart/items', ['variant' => $this->variant('amani-armchair', 'Forest')->id]));

        $this->post('/checkout', $form)->assertRedirect(route('orders.pay', Order::sole()));

        $this->assertSame(1, Cart::count());
        $this->get('/cart')->assertSeeText('Amani armchair');
    }

    public function test_nothing_is_ordered_when_a_price_changes_between_the_review_and_the_button(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');
        $before = $sand->stock_on_hand;

        $this->fillCart();
        $this->giveDetails();
        $this->get('/checkout/review')->assertSeeText('KSh 100,500');

        $sand->update(['price' => 10500000]);

        $this->post('/checkout', ['key' => session('checkout.key'), 'total' => 10050000])
            ->assertRedirect('/cart')
            ->assertSessionHas('shop.problem', 'A price changed while you were checking out. Nothing was ordered. Please check your cart and try again.');

        $this->assertSame(0, Order::count());
        $this->assertSame($before, $sand->fresh()->stock_on_hand);
        $this->assertSame(1, Cart::count());
    }

    public function test_two_customers_cannot_both_buy_the_last_one(): void
    {
        $sand = $this->variant('amani-3-seater-sofa', 'Sand');
        app(StockLedger::class)->setTo($sand, 1);

        // Both put the last sofa in their carts and reach the review page.
        $this->fillCart();
        $this->giveDetails();
        $firstCart = $this->defaultCookies;
        $firstForm = ['key' => session('checkout.key'), 'total' => 10050000];

        $this->defaultCookies = [];
        session()->flush();
        $this->fillCart();
        $this->giveDetails(['name' => 'Otieno Odhiambo', 'phone' => '0722 000 111']);
        $secondForm = ['key' => session('checkout.key'), 'total' => 10050000];

        $this->post('/checkout', $secondForm)->assertRedirect(route('orders.pay', Order::sole()));

        $this->assertSame('Otieno Odhiambo', Order::sole()->customer_name);
        $this->assertSame(0, $sand->fresh()->stock_on_hand);

        // The first customer presses the button a moment later.
        $this->defaultCookies = $firstCart;
        session()->put(['checkout.key' => $firstForm['key'], 'checkout.details' => ['name' => 'Wanjiru Kamau', 'phone' => '254712345678', 'email' => null, 'method' => 'delivery', 'county_id' => County::where('name', 'Kiambu')->value('id'), 'town' => 'Ruaka', 'estate' => 'Maple Court', 'house' => null, 'directions' => null, 'marketing' => false]]);

        $this->post('/checkout', $firstForm)->assertRedirect('/cart')->assertSessionHas('shop.problem');

        $this->assertSame(1, Order::count());
        $this->assertSame(0, $sand->fresh()->stock_on_hand);
        $this->get('/cart')->assertSeeText('This has sold out since you chose it.');
    }

    public function test_a_checkout_that_has_expired_orders_nothing(): void
    {
        $this->fillCart();

        $this->post('/checkout', ['key' => (string) Str::uuid(), 'total' => 10050000])
            ->assertRedirect('/cart')
            ->assertSessionHas('shop.problem');

        $this->post('/checkout', ['key' => 'not-a-key', 'total' => 1])->assertSessionHasErrors('key');

        $this->assertSame(0, Order::count());
    }

    /** Put one sand Amani sofa (KSh 98,000) in a cart and keep the cart for later requests. */
    private function fillCart(int $quantity = 1): void
    {
        $this->carry($this->post('/cart/items', ['variant' => $this->variant('amani-3-seater-sofa', 'Sand')->id, 'quantity' => $quantity]));
    }

    /** @param array<string, mixed> $changes */
    private function giveDetails(array $changes = []): void
    {
        $this->post('/checkout/details', $changes + self::DETAILS + ['county_id' => County::where('name', 'Kiambu')->value('id')])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/checkout/review');
    }

    /** Press "Place order and pay" on the review page as it stands. */
    private function place(): TestResponse
    {
        $page = $this->get('/checkout/review')->assertOk()->getContent();

        preg_match('/name="key" value="([^"]+)"/', $page, $key);
        preg_match('/name="total" value="(\d+)"/', $page, $total);

        return $this->post('/checkout', ['key' => $key[1], 'total' => $total[1]]);
    }

    private function carry(TestResponse $response): void
    {
        $this->withCookie(CartManager::COOKIE, $response->getCookie(CartManager::COOKIE)->getValue());
    }

    private function variant(string $product, string $label): ProductVariant
    {
        return Product::query()->where('slug', $product)->firstOrFail()
            ->variants()->with('product', 'optionValues.option')->get()
            ->firstOrFail(fn (ProductVariant $variant) => $variant->label() === $label);
    }
}
