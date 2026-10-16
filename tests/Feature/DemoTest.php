<?php

namespace Tests\Feature;

use App\Demo\DemoAccountCannotChange;
use App\Demo\DemoData;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentState;
use App\Enums\StaffRole;
use App\Enums\StockMovementReason;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Stock\Pages\ListStock;
use App\Filament\Resources\Stock\StockResource;
use App\Filament\Widgets\SalesOverview;
use App\Inventory\StockLedger;
use App\Messages\EmailAllowance;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StoreSettings;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A store run as a demonstration: the trading history it opens each day with,
 * the accounts a visitor is let into, and what those accounts cannot do.
 */
class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);
        $this->withoutVite();
        config(['store.demo' => true]);
    }

    public function test_the_reset_refuses_to_run_on_a_store_that_trades(): void
    {
        config(['store.demo' => false]);
        $owner = User::factory()->create(['role' => StaffRole::Owner]);

        $this->artisan('demo:reset')->expectsOutputToContain('nothing was changed')->assertFailed();

        $this->assertSame(0, Order::count());
        $this->assertTrue($owner->fresh()->exists);
    }

    public function test_the_reset_fills_the_store_with_four_months_of_trading(): void
    {
        $this->artisan('demo:reset')->expectsOutputToContain('The demonstration data is fresh')->assertSuccessful();

        $this->assertGreaterThan(200, Order::count());
        $this->assertSame(2, Order::where('status', OrderStatus::PendingPayment)->count());
        $this->assertGreaterThan(150, Order::where('status', OrderStatus::Delivered)->count());

        foreach ([OrderStatus::Paid, OrderStatus::InProduction, OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Cancelled, OrderStatus::Expired] as $status) {
            $this->assertGreaterThan(0, Order::where('status', $status)->count(), $status->value);
        }

        // The first order is about four months old and the newest are from today.
        $this->assertTrue(Order::min('created_at') < now()->subDays(110)->toDateTimeString());
        $this->assertGreaterThan(0, Order::where('created_at', '>=', now()->subHours(5))->count());

        [$today] = SalesOverview::sales(now()->subDay(), now());
        $this->assertGreaterThan(0, $today);
        $this->assertSame(1, Order::query()->owedARefund()->count());
    }

    public function test_every_number_in_the_demonstration_data_is_one_nothing_is_sent_to(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();

        foreach ([Order::class, Customer::class, Payment::class] as $record) {
            $this->assertSame([], $record::query()->whereNotNull('phone')->pluck('phone')->reject(fn (string $phone) => DemoData::invented($phone))->values()->all(), $record);
        }
    }

    public function test_every_demonstration_order_adds_up_and_tells_a_story_that_could_have_happened(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();

        foreach (Order::with('items', 'events', 'payments')->get() as $order) {
            $this->assertSame($order->subtotal, $order->items->sum('line_total'), $order->number);
            $this->assertSame($order->total, $order->subtotal + $order->delivery_fee, $order->number);
            $this->assertSame((int) round($order->total * 1600 / 11600), $order->vat_amount, $order->number);
            $this->assertSame(OrderEventType::Placed, $order->events->first()->type, $order->number);

            // Nothing happens before the order is placed, after now, or out of order.
            $times = $order->events->pluck('created_at');
            $this->assertTrue($times->first()->gte($order->created_at), $order->number);
            $this->assertTrue($times->last()->lte(now()), $order->number);
            $this->assertEquals($times->sort()->values()->all(), $times->values()->all(), $order->number);

            $paid = $order->payments->firstWhere('state', PaymentState::Paid);
            $this->assertSame($order->status->isPaid(), $order->status->isPaid() && $paid !== null, $order->number);

            if ($paid) {
                $this->assertSame($order->total, $paid->amount);
                $this->assertMatchesRegularExpression('/^U[A-Z0-9]{9}$/', $paid->receipt);
            }

            $this->assertSame($order->status->isClosed(), $order->closed_at !== null, $order->number);
        }

        $this->assertSame(Payment::whereNotNull('receipt')->count(), Payment::whereNotNull('receipt')->distinct()->count('receipt'));
        $this->assertSame(Order::count(), Order::distinct()->count('number'));
        $this->assertSame(0, OrderEvent::where('created_at', '>', now())->count());
    }

    public function test_stock_is_counted_afresh_and_orders_on_their_way_hold_what_they_took(): void
    {
        $sofa = ProductVariant::where('sku', 'AMANI-3-SEATER-SOFA-SAND')->firstOrFail();
        (new StockLedger)->adjust($sofa, -3, StockMovementReason::Damaged, 'A visitor wrote these off');

        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertSame(0, ProductVariant::where('stock_on_hand', '<', 0)->count());
        $this->assertSame(0, StockMovement::where('note', 'A visitor wrote these off')->count());

        // Each variant's stock is its fresh count less what open orders hold, and its history says so.
        foreach (ProductVariant::with('stockMovements')->get() as $variant) {
            $this->assertSame($variant->stock_on_hand, (int) $variant->stockMovements->sum('quantity'), $variant->sku);
        }

        $held = Order::whereIn('status', [OrderStatus::Paid, OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::PendingPayment])->with('items')->get()
            ->flatMap->items->where('made_to_order', false);

        $this->assertGreaterThan(0, $held->count());
        $this->assertTrue($held->every->stock_taken);
        $this->assertSame(0, Order::where('status', OrderStatus::Delivered)->whereHas('items', fn ($items) => $items->where('stock_taken', true))->count());
    }

    public function test_an_older_order_still_in_the_workshop_holds_only_stock_that_is_there(): void
    {
        // On this night an order from five weeks back, still waiting on a piece made to order, also held a
        // tallboy that had already gone, and the reset stopped.
        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(3, 30));

        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertSame(0, ProductVariant::where('stock_on_hand', '<', 0)->count());
        $this->assertGreaterThan(0, Order::where('status', OrderStatus::InProduction)->where('created_at', '<', now()->subDays(14))->count());
    }

    public function test_the_reset_gives_the_same_store_every_night_and_clears_what_visitors_left(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();
        $first = [Order::count(), Order::sum('total'), Order::orderBy('id')->pluck('customer_name')->take(20)->all()];

        // A visitor places an order and opens an account.
        Customer::create(['name' => 'A Visitor', 'email' => 'visitor@example.com', 'password' => 'a-good-password']);
        Order::first()->update(['customer_name' => 'Somebody Else']);

        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertSame($first, [Order::count(), Order::sum('total'), Order::orderBy('id')->pluck('customer_name')->take(20)->all()]);
        $this->assertFalse(Customer::where('email', 'visitor@example.com')->exists());
    }

    public function test_the_reset_leaves_the_catalogue_the_pages_the_settings_and_real_staff_alone(): void
    {
        $owner = User::factory()->create(['role' => StaffRole::Owner, 'email' => 'owner@sebule.test']);
        StoreSettings::save(['store.showroom.street' => 'Karen Road']);
        Page::where('slug', 'returns')->update(['title' => 'Returns and exchanges']);
        DeliveryZone::where('name', 'Nairobi')->update(['fee' => 180000]);
        Product::where('slug', 'amani-3-seater-sofa')->update(['name' => 'Amani three-seater']);
        $products = Product::count();

        $this->artisan('demo:reset')->assertSuccessful();
        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertTrue($owner->fresh()->exists);
        $this->assertSame('Karen Road', config('store.showroom.street'));
        $this->assertSame('Returns and exchanges', Page::where('slug', 'returns')->value('title'));
        $this->assertSame(180000, DeliveryZone::where('name', 'Nairobi')->value('fee'));
        $this->assertSame('Amani three-seater', Product::where('slug', 'amani-3-seater-sofa')->value('name'));
        $this->assertSame($products, Product::count());
        $this->assertSame(3, User::where('is_demo', true)->count());
    }

    public function test_the_message_log_opens_with_what_recent_customers_were_written_and_nothing_was_sent(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertGreaterThan(20, Message::count());
        // Every demonstration customer has an example address, which is never sent to.
        $this->assertSame(0, Message::where('status', '!=', 'not_sent')->count());
        $this->assertSame(0, Order::whereNotNull('email')->where('email', 'not like', '%@example.com')->count());
        $this->assertSame(0, Message::where('created_at', '>', now())->count());
    }

    public function test_a_visitor_is_let_into_each_demonstration_staff_account(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();

        foreach (['manager' => 'Njeri Mwangi', 'fulfilment' => 'Otieno Odhiambo', 'support' => 'Brian Kiprop'] as $role => $name) {
            auth()->guard('web')->logout();

            $this->get($role === 'manager' ? '/demo/staff' : '/demo/staff/'.$role)->assertRedirect('/admin');

            $this->assertSame([$name, $role, true], [auth()->user()->name, auth()->user()->role->value, auth()->user()->is_demo]);
            $this->get('/admin')->assertOk();
        }
    }

    public function test_the_way_in_does_not_exist_on_a_store_that_trades_and_never_reaches_a_real_account(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();
        User::factory()->create(['role' => StaffRole::Owner]);

        // There is no demonstration owner, and a real one is never handed out.
        $this->get('/demo/staff/owner')->assertNotFound();
        $this->get('/demo/staff/nonsense')->assertNotFound();
        $this->assertGuest('web');

        config(['store.demo' => false]);

        $this->get('/demo/staff')->assertNotFound();
        $this->get('/demo/customer')->assertNotFound();
        $this->assertGuest('web');
        $this->assertGuest('customer');
    }

    public function test_a_visitor_is_let_into_the_demonstration_customer_with_orders_behind_them(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();

        $this->get('/demo/customer')->assertRedirect(route('account.show'));

        $customer = auth('customer')->user();
        $this->assertSame(['Wanjiru Kamau', true], [$customer->name, $customer->is_demo]);

        $this->get(route('account.show'))->assertOk()->assertSeeText('Order '.$customer->orders()->first()->number);
        $this->get(route('account.addresses.index'))->assertOk()->assertSeeText('A2, Riara Court')->assertSeeText('B4, Maple Court');
        $this->assertSame(5, $customer->orders()->count());
    }

    public function test_the_demonstration_customer_cannot_be_locked_out_of(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();
        $this->get('/demo/customer');
        $before = Customer::where('is_demo', true)->first()->only(['name', 'email', 'password']);

        $this->put(route('account.details.update'), ['name' => 'Taken Over', 'email' => 'mine@example.com'])
            ->assertSessionHas('shop.problem', 'This is a demonstration account, so its name, email and password stay as they are. Its addresses and orders are yours to try.');
        $this->put(route('account.password.change'), ['current_password' => 'whatever', 'password' => 'a-new-password'])
            ->assertSessionHas('shop.problem');

        $this->assertSame($before, Customer::where('is_demo', true)->first()->only(['name', 'email', 'password']));
    }

    public function test_a_demonstration_manager_can_open_everything_and_change_nothing_customers_see(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();
        $this->get('/demo/staff');
        $product = Product::where('slug', 'amani-3-seater-sofa')->firstOrFail();

        foreach (['/admin/products', ProductResource::getUrl('edit', ['record' => $product]), '/admin/pages', '/admin/store-settings', '/admin/home-page', '/admin/delivery-zones', '/admin/orders', '/admin/customers', '/admin/stock'] as $screen) {
            $this->get($screen)->assertOk();
        }

        $attempts = [
            fn () => $product->update(['name' => 'Vandalised']),
            fn () => $product->variants()->first()->update(['price' => 100]),
            fn () => $product->delete(),
            fn () => Page::first()->update(['body' => 'Vandalised']),
            fn () => Page::first()->delete(),
            fn () => DeliveryZone::first()->update(['fee' => 0]),
            fn () => StoreSettings::save(['store.whatsapp' => '254700000000']),
            fn () => $product->media()->first()?->delete() ?? throw new DemoAccountCannotChange,
            fn () => User::factory()->create(['role' => StaffRole::Owner]),
            fn () => auth()->user()->update(['role' => StaffRole::Owner]),
        ];

        foreach ($attempts as $number => $attempt) {
            try {
                $attempt();
                $this->fail("Attempt {$number} changed something a demonstration account must not.");
            } catch (DemoAccountCannotChange) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('Amani 3-seater sofa', $product->fresh()->name);
        $this->assertNull(config('store.whatsapp'));
        $this->assertSame(StaffRole::Manager, auth()->user()->fresh()->role);
    }

    public function test_a_change_a_demonstration_account_may_not_make_is_refused_in_plain_words(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();
        $this->get('/demo/staff');
        $page = Page::where('slug', 'returns')->firstOrFail();

        // In the back office the refusal is a notice on the screen the visitor is on.
        Livewire::test(EditPage::class, ['record' => $page->id])
            ->fillForm(['title' => 'Vandalised'])
            ->call('save')
            ->assertOk()
            ->assertNotified('That change was not saved');

        // Anywhere else it is a page that says the same, not an error page of the framework's.
        $response = app(ExceptionHandler::class)->render(request(), new DemoAccountCannotChange);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('That change was not saved.', $response->getContent());
        $this->assertStringContainsString('cannot change what customers see', $response->getContent());

        $this->assertSame('Returns', $page->fresh()->title);
    }

    public function test_demonstration_staff_work_orders_and_stock_as_real_staff_do(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();
        $this->get('/demo/staff/fulfilment');

        $order = Order::where('status', OrderStatus::Paid)->whereDoesntHave('items', fn ($items) => $items->where('made_to_order', true))->firstOrFail();

        Livewire::test(ViewOrder::class, ['record' => $order->number])->callAction('to_ready');
        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);

        $variant = ProductVariant::where('made_to_order', false)->where('stock_on_hand', '>', 2)->firstOrFail();
        $before = $variant->stock_on_hand;

        Livewire::test(ListStock::class)
            ->callAction(TestAction::make('adjustStock')->table($variant), ['quantity' => -1, 'reason' => StockMovementReason::Damaged->value, 'note' => 'Scratched in the showroom'])
            ->assertHasNoActionErrors();

        $this->assertSame($before - 1, $variant->fresh()->stock_on_hand);
    }

    public function test_the_catalogue_belongs_to_owners_and_managers_and_stock_to_fulfilment_too(): void
    {
        $product = Product::first();

        foreach ([StaffRole::Fulfilment, StaffRole::Support] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            foreach (['/admin/products', ProductResource::getUrl('edit', ['record' => $product]), '/admin/categories', '/admin/collections'] as $screen) {
                $this->get($screen)->assertForbidden();
            }

            $this->get(StockResource::getUrl('index'))->assertStatus($role === StaffRole::Fulfilment ? 200 : 403);
            $this->get('/admin/stock-history')->assertStatus($role === StaffRole::Fulfilment ? 200 : 403);
        }

        $this->actingAs(User::factory()->create(['role' => StaffRole::Manager]));
        $this->get('/admin/products')->assertOk();
        $this->get(StockResource::getUrl('index'))->assertOk();

        $sand = ProductVariant::where('sku', 'AMANI-3-SEATER-SOFA-SAND')->firstOrFail();
        $made = ProductVariant::where('made_to_order', true)->firstOrFail();

        Livewire::test(ListStock::class)
            ->searchTable('AMANI-3-SEATER-SOFA-SAND')
            ->assertCanSeeTableRecords([$sand])
            ->searchTable('')
            ->filterTable('level', 'made')
            ->assertCanSeeTableRecords([$made])
            ->assertCanNotSeeTableRecords([$sand]);
    }

    public function test_an_order_email_is_never_sent_to_an_example_address(): void
    {
        config(['mail.default' => 'smtp']);

        foreach (['someone@example.com', 'someone@mail.example.org', 'someone@sebule.test'] as $address) {
            $this->assertSame('This address is an example, not a real mailbox.', EmailAllowance::refusal($address));
        }

        $this->assertNull(EmailAllowance::refusal('someone@gmail.com'));
        $this->assertNull(EmailAllowance::refusal('example.com@gmail.com'));
    }
}
