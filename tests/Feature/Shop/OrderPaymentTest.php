<?php

namespace Tests\Feature\Shop;

use App\Checkout\PlaceOrder;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\StockMovementReason;
use App\Inventory\StockLedger;
use App\Jobs\CheckMpesaPayment;
use App\Models\Cart;
use App\Models\County;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Payments\Mpesa\Daraja;
use App\Payments\RecordMpesaResult;
use App\Payments\StartMpesaPayment;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Paying for an order: the M-Pesa prompt, Safaricom's confirmation, the store's
 * own check when no confirmation comes, and what happens when nobody pays.
 */
class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $sofa;

    /** How many prompts the pretend Safaricom has accepted in this test. */
    private int $prompts = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);
        $this->withoutVite();

        config([
            'services.mpesa.consumer_key' => 'key',
            'services.mpesa.consumer_secret' => 'secret',
            'services.mpesa.passkey' => 'passkey',
            'services.mpesa.shortcode' => '174379',
            'services.mpesa.charge' => null,
            'store.test_payments' => false,
        ]);

        Queue::fake();

        $this->sofa = Product::query()->where('slug', 'amani-3-seater-sofa')->firstOrFail()
            ->variants()->with('product', 'optionValues.option')->get()
            ->firstOrFail(fn (ProductVariant $variant) => $variant->label() === 'Sand');
    }

    public function test_the_payment_page_shows_what_is_owed_and_how_long_the_pieces_are_held(): void
    {
        $order = $this->order();

        $this->get(route('orders.pay', $order))->assertOk()
            ->assertSeeText('Order '.$order->number.' for Wanjiru Kamau')
            ->assertSeeText('Pay with M-Pesa')
            ->assertSee('value="0712 345 678"', escape: false)
            ->assertSeeTextInOrder(['Total', 'KSh 100,500'])
            ->assertSeeText('Your pieces are held for you until '.$order->held_until->format('g:i a').'.')
            ->assertDontSeeText('test payment')
            ->assertDontSeeText("Safaricom's test system");
    }

    public function test_an_order_is_reached_only_by_its_secret_link(): void
    {
        $order = $this->order();

        $this->get('/orders/'.$order->id)->assertNotFound();
        $this->get('/orders/'.$order->number.'/pay')->assertNotFound();
        $this->get('/orders/'.Str::random(40))->assertNotFound();
        $this->get(route('orders.show', $order))->assertOk()->assertSee('<meta name="robots" content="noindex">', escape: false);
    }

    public function test_sending_the_prompt_asks_safaricom_for_the_orders_total_and_waits(): void
    {
        $this->fakeDaraja();
        $order = $this->order();

        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0722 000 111'])->assertRedirect(route('orders.pay', $order));

        $payment = Payment::sole();

        $this->assertSame([PaymentMethod::Mpesa, PaymentState::Pending, 10050000, 10050000, '254722000111', 'ws_CO_1'],
            [$payment->method, $payment->state, $payment->amount, $payment->charged, $payment->phone, $payment->checkout_request_id]);

        Http::assertSent(function (Request $request) use ($order, $payment): bool {
            if (! str_contains($request->url(), 'stkpush')) {
                return false;
            }

            $callback = $request['CallBackURL'];

            return $request['Amount'] === 100500
                && $request['PhoneNumber'] === '254722000111'
                && $request['PartyA'] === '254722000111'
                && $request['BusinessShortCode'] === '174379'
                && $request['AccountReference'] === $order->number
                && $request['Password'] === base64_encode('174379'.'passkey'.$request['Timestamp'])
                && $callback === StartMpesaPayment::callbackUrl($payment)
                // Safaricom refuses an address with its own names in it.
                && ! Str::contains($callback, ['mpesa', 'safaricom', 'query'], ignoreCase: true)
                && $request->hasHeader('Authorization', 'Bearer token-1');
        });

        Queue::assertPushed(CheckMpesaPayment::class, fn (CheckMpesaPayment $job) => $job->payment->is($payment));

        $this->get(route('orders.pay', $order))->assertOk()
            ->assertSeeText('Check your phone')
            ->assertSeeText('0722 000 111')
            ->assertSee('data-payment-watch="'.route('orders.payment', $order).'"', escape: false)
            ->assertSee('http-equiv="refresh"', escape: false);

        $this->getJson(route('orders.payment', $order))->assertOk()->assertExactJson(['order' => 'pending_payment', 'payment' => 'pending', 'method' => 'mpesa']);
    }

    public function test_the_store_can_be_set_to_ask_for_a_token_amount_and_says_so_at_the_payment_step(): void
    {
        config(['services.mpesa.charge' => 1]);
        $this->fakeDaraja();
        $order = $this->order();

        $this->get(route('orders.pay', $order))
            ->assertSeeText("The prompt asks for KSh 1, not the full total: payments on this site run on Safaricom's test system, and no goods are delivered.");

        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'stkpush') && $request['Amount'] === 1);
        $this->assertSame([10050000, 100], [Payment::sole()->amount, Payment::sole()->charged]);

        // A confirmation for the one shilling asked settles the whole order.
        $this->confirm(Payment::sole(), amount: 1)->assertOk();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_a_second_press_while_the_prompt_is_open_sends_nothing_more(): void
    {
        $this->fakeDaraja();
        $order = $this->order();

        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678']);
        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678']);

        $this->assertSame(1, Payment::count());
        Http::assertSentCount(2);

        // After the prompt has had its minute, a new one goes out and the old one is closed.
        $this->travel(2)->minutes();
        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678']);

        $this->assertSame([PaymentState::TimedOut, PaymentState::Pending], Payment::query()->orderBy('id')->pluck('state')->all());
    }

    public function test_the_number_for_the_prompt_must_be_a_kenyan_mobile(): void
    {
        $order = $this->order();

        $this->post(route('orders.pay.mpesa', $order), ['phone' => '12345'])
            ->assertSessionHasErrors(['phone' => 'Enter the Safaricom number to send the prompt to, such as 0712 345 678.']);

        $this->assertSame(0, Payment::count());
    }

    public function test_safaricoms_confirmation_marks_the_order_paid_and_is_recorded_once(): void
    {
        $this->fakeDaraja();
        $order = $this->order();
        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678']);
        $payment = Payment::sole();

        $this->confirm($payment, receipt: 'TJA5K2PXQR')->assertOk()->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        $this->confirm($payment, receipt: 'TJA5K2PXQR')->assertOk();
        $this->confirm($payment, receipt: 'TJA5K2PXQR')->assertOk();

        $order->refresh();
        $payment->refresh();

        $this->assertSame([PaymentState::Paid, 'TJA5K2PXQR', '0'], [$payment->state, $payment->receipt, $payment->result_code]);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNull($order->held_until);
        $this->assertSame([OrderEventType::Placed, OrderEventType::Paid], $order->events->pluck('type')->all());
        $this->assertSame('M-Pesa, receipt TJA5K2PXQR', $order->events->last()->note);

        $this->get(route('orders.pay', $order))->assertRedirect(route('orders.show', $order));
        $this->get(route('orders.show', $order))->assertOk()
            ->assertSeeText('Thank you, Wanjiru.')
            ->assertSeeText('Payment received')
            ->assertSeeText('receipt TJA5K2PXQR')
            ->assertSeeText('B4, Maple Court, Ruaka, Kiambu');

        $this->getJson(route('orders.payment', $order))->assertExactJson(['order' => 'paid', 'payment' => 'paid', 'method' => 'mpesa']);
    }

    public function test_a_confirmation_is_believed_only_as_far_as_it_can_be_checked(): void
    {
        $this->fakeDaraja();
        $order = $this->order();
        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678']);
        $payment = Payment::sole();

        // Sent to an address nobody was given.
        $this->postJson("/payments/confirmation/{$payment->id}/".str_repeat('a', 64), $this->confirmation($payment))->assertNotFound();

        // About some other prompt.
        $this->confirm($payment, checkoutId: 'ws_CO_other')->assertOk();

        // Claiming a different sum from the one asked for.
        $this->confirm($payment, amount: 5)->assertOk();

        // Not a confirmation at all.
        $this->postJson(StartMpesaPayment::callbackUrl($payment), ['hello' => 'world'])->assertOk();

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(PaymentState::Failed, $payment->fresh()->state);
        $this->assertNull($payment->fresh()->receipt);
    }

    public function test_each_way_a_payment_fails_has_its_own_message_and_a_way_forward(): void
    {
        $messages = [
            '1032' => [PaymentState::Cancelled, 'You cancelled the prompt on your phone, so nothing was paid.'],
            '1037' => [PaymentState::TimedOut, 'The prompt could not reach your phone.'],
            '1019' => [PaymentState::TimedOut, 'The prompt ran out of time before it was answered.'],
            '1' => [PaymentState::Failed, 'There was not enough money in the M-Pesa account.'],
            '2001' => [PaymentState::Failed, 'The M-Pesa PIN was not right, so nothing was paid.'],
            '1001' => [PaymentState::Failed, 'Your phone already has an M-Pesa request open.'],
        ];

        foreach ($messages as $code => [$state, $message]) {
            $this->fakeDaraja(checkoutId: 'ws_CO_'.$code);
            $order = $this->order();
            $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678'])->assertSessionHasNoErrors()->assertRedirect(route('orders.pay', $order));
            $payment = $order->payments()->sole();

            $this->confirm($payment, code: (int) $code)->assertOk();

            $this->assertSame($state, $payment->fresh()->state, "code {$code}");
            $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);

            $this->get(route('orders.pay', $order))->assertOk()
                ->assertSeeText('The payment did not go through')
                ->assertSeeText($message)
                ->assertSeeText('Send the prompt to my phone');
        }
    }

    public function test_safaricom_refusing_the_request_is_told_to_the_customer_plainly(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => '3599']),
            '*/stkpush/*' => Http::response(['requestId' => 'x', 'errorCode' => '500.001.1001', 'errorMessage' => 'Unable to lock subscriber'], 500),
        ]);
        $order = $this->order();

        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678'])->assertRedirect(route('orders.pay', $order));

        $this->assertSame(PaymentState::Failed, Payment::sole()->state);
        Queue::assertNotPushed(CheckMpesaPayment::class);

        $this->get(route('orders.pay', $order))
            ->assertSeeText('M-Pesa could not send the prompt just now. Nothing was paid.')
            ->assertSeeText('Send the prompt to my phone');
    }

    public function test_when_no_confirmation_comes_the_store_asks_and_settles_the_order_itself(): void
    {
        $this->fakeDaraja(result: ['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'The service request is processed successfully.']);
        $order = $this->order();
        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678']);
        $payment = Payment::sole();

        (new CheckMpesaPayment($payment))->handle(app(Daraja::class), app(RecordMpesaResult::class));

        $this->assertSame(PaymentState::Paid, $payment->fresh()->state);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        // The receipt number, when the confirmation does turn up later, is still worth keeping.
        $this->confirm($payment, receipt: 'TJA5K2PXQR')->assertOk();
        $this->assertSame('TJA5K2PXQR', $payment->fresh()->receipt);
        $this->assertSame(1, $order->events()->where('type', OrderEventType::Paid)->count());
    }

    public function test_the_store_keeps_asking_while_the_prompt_is_unanswered_and_gives_up_in_the_end(): void
    {
        $this->fakeDaraja(result: ['errorCode' => '500.001.1001', 'errorMessage' => 'The transaction is being processed'], resultStatus: 500);
        $order = $this->order();
        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678']);
        $payment = Payment::sole();

        $check = fn (int $attempt) => (new CheckMpesaPayment($payment, $attempt))->handle(app(Daraja::class), app(RecordMpesaResult::class));

        $check(1);
        $this->assertSame(PaymentState::Pending, $payment->fresh()->state);
        Queue::assertPushed(CheckMpesaPayment::class, fn (CheckMpesaPayment $job) => $job->attempt === 2);

        $check(CheckMpesaPayment::ATTEMPTS);
        $this->assertSame(PaymentState::TimedOut, $payment->fresh()->state);
        $this->get(route('orders.pay', $order))->assertSeeText('We did not hear back from M-Pesa.');

        // Money that did go through is never lost to the store having given up on it.
        $this->confirm($payment, receipt: 'TJA5K2PXQR')->assertOk();
        $this->assertSame(PaymentState::Paid, $payment->fresh()->state);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_an_order_nobody_pays_for_gives_its_stock_back_when_the_time_is_up(): void
    {
        $before = $this->sofa->stock_on_hand;
        $order = $this->order(quantity: 2);

        $this->assertSame($before - 2, $this->sofa->fresh()->stock_on_hand);

        $this->artisan('orders:release-unpaid')->expectsOutputToContain('0 unpaid orders released.');
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);

        $this->travel(16)->minutes();
        $this->artisan('orders:release-unpaid')->expectsOutputToContain('1 unpaid order released.');

        $order->refresh();
        $this->assertSame(OrderStatus::Expired, $order->status);
        $this->assertNotNull($order->closed_at);
        $this->assertSame($before, $this->sofa->fresh()->stock_on_hand);
        $this->assertSame([2, StockMovementReason::Released], [StockMovement::latest('id')->first()->quantity, StockMovement::latest('id')->first()->reason]);
        $this->assertSame(OrderEventType::Expired, $order->events->last()->type);

        // Running it again changes nothing.
        $this->artisan('orders:release-unpaid')->expectsOutputToContain('0 unpaid orders released.');
        $this->assertSame($before, $this->sofa->fresh()->stock_on_hand);
    }

    public function test_an_order_is_not_released_while_its_customer_is_at_the_prompt(): void
    {
        $this->fakeDaraja();
        $order = $this->order();

        $this->travel(14)->minutes();
        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678']);

        $this->travel(2)->minutes();
        $this->artisan('orders:release-unpaid');
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);

        $this->confirm(Payment::sole(), receipt: 'TJA5K2PXQR');
        $this->travel(30)->minutes();
        $this->artisan('orders:release-unpaid');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_a_customer_can_pick_up_an_order_that_ran_out_of_time(): void
    {
        $before = $this->sofa->stock_on_hand;
        $order = $this->order();

        $this->travel(16)->minutes();
        $this->artisan('orders:release-unpaid');

        $this->get(route('orders.pay', $order))->assertOk()
            ->assertSeeText('This order ran out of time')
            ->assertSeeText('Hold my pieces again')
            ->assertDontSeeText('Send the prompt to my phone');

        $this->get(route('orders.show', $order))->assertSeeText('This order was not paid in time.');

        $this->post(route('orders.resume', $order))->assertRedirect(route('orders.pay', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertTrue($order->held_until->isFuture());
        $this->assertSame($before - 1, $this->sofa->fresh()->stock_on_hand);
        $this->assertSame(10050000, $order->total);

        $this->get(route('orders.pay', $order))->assertSeeText('Send the prompt to my phone');
    }

    public function test_an_order_cannot_be_picked_up_once_its_pieces_have_sold(): void
    {
        $order = $this->order();

        $this->travel(16)->minutes();
        $this->artisan('orders:release-unpaid');
        app(StockLedger::class)->setTo($this->sofa, 0);

        $this->post(route('orders.resume', $order))
            ->assertRedirect(route('orders.pay', $order))
            ->assertSessionHas('shop.problem', 'The Amani 3-seater sofa, Sand has sold out since you placed this order, so it could not be held again.');

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(0, $this->sofa->fresh()->stock_on_hand);
    }

    public function test_money_that_arrives_after_the_hold_ran_out_still_buys_the_order(): void
    {
        $this->fakeDaraja();
        $before = $this->sofa->stock_on_hand;
        $order = $this->order();
        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678']);
        $payment = Payment::sole();

        $this->travel(30)->minutes();
        $this->artisan('orders:release-unpaid');
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame($before, $this->sofa->fresh()->stock_on_hand);

        $this->confirm($payment, receipt: 'TJA5K2PXQR')->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame($before - 1, $this->sofa->fresh()->stock_on_hand);
        $this->assertTrue($order->items()->sole()->stock_taken);
    }

    public function test_an_order_can_be_completed_with_a_test_payment_only_where_the_store_allows_it(): void
    {
        $order = $this->order();

        $this->post(route('orders.pay.test', $order))->assertNotFound();

        config(['store.test_payments' => true]);

        $this->get(route('orders.pay', $order))->assertSeeText('Complete with a test payment')->assertSeeText('No money moves, and no goods are delivered.');

        $this->post(route('orders.pay.test', $order))->assertRedirect(route('orders.pay', $order));
        $this->post(route('orders.pay.test', $order));

        $payment = Payment::sole();
        $this->assertSame([PaymentMethod::Test, PaymentState::Paid, 10050000, 0], [$payment->method, $payment->state, $payment->amount, $payment->charged]);
        $this->assertStringStartsWith('TEST', $payment->receipt);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->get(route('orders.show', $order))->assertSeeText('Paid by a test payment');
    }

    public function test_without_mpesa_set_up_the_prompt_cannot_be_sent(): void
    {
        config(['services.mpesa.consumer_key' => null, 'store.test_payments' => true]);
        $order = $this->order();

        $this->get(route('orders.pay', $order))->assertOk()->assertDontSeeText('Send the prompt to my phone')->assertSeeText('Complete your order');
        $this->post(route('orders.pay.mpesa', $order), ['phone' => '0712345678'])->assertNotFound();
    }

    public function test_an_order_being_collected_has_its_own_timeline(): void
    {
        config(['store.test_payments' => true]);
        $order = $this->order(collect: true);

        $this->get(route('orders.show', $order))->assertOk()
            ->assertSeeText('One step left, Wanjiru.')
            ->assertSeeTextInOrder(['Order placed', 'Payment received', 'Ready to collect', 'Collected'])
            ->assertDontSeeText('Out for delivery')
            ->assertSeeText('Collection from the showroom');

        $this->post(route('orders.pay.test', $order));

        $this->get(route('orders.show', $order))->assertSeeText('We will text 0712 345 678 when it is ready to collect.');
    }

    /** Safaricom accepts the prompt, and answers a status question as given. */
    private function fakeDaraja(string $checkoutId = 'ws_CO_1', ?array $result = null, int $resultStatus = 200): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => '3599']),
            // Every prompt gets an id of its own, as it does from Safaricom.
            '*/stkpush/v1/processrequest' => fn () => Http::response([
                'MerchantRequestID' => 'mr-1',
                'CheckoutRequestID' => ++$this->prompts === 1 ? $checkoutId : $checkoutId.'-'.$this->prompts,
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CustomerMessage' => 'Success. Request accepted for processing',
            ]),
            '*/stkpushquery/v1/query' => Http::response($result ?? ['errorCode' => '500.001.1001', 'errorMessage' => 'The transaction is being processed'], $result ? $resultStatus : 500),
        ]);
    }

    /** What Safaricom posts when a prompt has been answered. */
    private function confirmation(Payment $payment, int $code = 0, ?string $receipt = 'TJA5K2PXQR', ?int $amount = null, ?string $checkoutId = null): array
    {
        $callback = [
            'MerchantRequestID' => 'mr-1',
            'CheckoutRequestID' => $checkoutId ?? $payment->checkout_request_id,
            'ResultCode' => $code,
            'ResultDesc' => $code === 0 ? 'The service request is processed successfully.' : 'Request cancelled by user',
        ];

        if ($code === 0) {
            $callback['CallbackMetadata'] = ['Item' => [
                ['Name' => 'Amount', 'Value' => (float) ($amount ?? intdiv($payment->charged, 100))],
                ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
                ['Name' => 'TransactionDate', 'Value' => 20261010231500],
                ['Name' => 'PhoneNumber', 'Value' => 254712345678],
            ]];
        }

        return ['Body' => ['stkCallback' => $callback]];
    }

    private function confirm(Payment $payment, int $code = 0, ?string $receipt = 'TJA5K2PXQR', ?int $amount = null, ?string $checkoutId = null): TestResponse
    {
        return $this->postJson(StartMpesaPayment::callbackUrl($payment), $this->confirmation($payment, $code, $receipt, $amount, $checkoutId));
    }

    /** An order for sand Amani sofas, delivered to Kiambu (KSh 2,500) or collected. */
    private function order(int $quantity = 1, bool $collect = false): Order
    {
        $cart = Cart::create(['token' => (string) Str::uuid()]);
        $cart->items()->create(['product_variant_id' => $this->sofa->id, 'quantity' => $quantity, 'unit_price' => $this->sofa->price]);

        $details = [
            'name' => 'Wanjiru Kamau', 'phone' => '254712345678', 'email' => null, 'marketing' => false,
            'method' => $collect ? 'collection' : 'delivery',
            'county_id' => $collect ? null : County::where('name', 'Kiambu')->value('id'),
            'town' => 'Ruaka', 'estate' => 'Maple Court', 'house' => 'B4', 'directions' => null,
        ];

        return app(PlaceOrder::class)($cart, $details, (string) Str::uuid(), $this->sofa->price * $quantity + ($collect ? 0 : 250000));
    }
}
