<?php

namespace Tests\Feature\Messages;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\StaffRole;
use App\Jobs\DeliverMessage;
use App\Mail\CustomerEmail;
use App\Messages\TellCustomer;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Orders\CancelOrder;
use App\Orders\MoveOrderOn;
use App\Payments\RecordMpesaResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Concerns\PlacesOrders;
use Tests\TestCase;

/**
 * What the store writes to a customer about an order, when it sends it, and
 * what it keeps.
 */
class CustomerMessagesTest extends TestCase
{
    use PlacesOrders, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // Example addresses are never sent to, so these customers have addresses at the store's own domain,
        // which could be real. The mailer is faked, so nothing leaves.
        $this->customerEmail = 'wanjiru@sebule.jeffgicharu.com';

        // Tests run with a mailer that keeps mail in memory; the store treats that as "not set up to send".
        config(['mail.default' => 'smtp', 'store.email.reply_to' => 'hello@sebule.test']);
        Mail::fake();

        $this->staff = User::factory()->create(['role' => StaffRole::Fulfilment]);
    }

    public function test_placing_an_order_emails_the_customer_a_link_to_pay(): void
    {
        $this->travelTo('2026-10-14 15:00:00');

        $order = $this->placeOrder();

        $email = $this->emails($order)->sole();

        $this->assertSame(['placed', 'wanjiru@sebule.jeffgicharu.com', "We have your order {$order->number}", MessageStatus::Sent],
            [$email->kind, $email->recipient, $email->subject, $email->status]);
        $this->assertNotNull($email->sent_at);

        foreach ([$email->html, $email->body] as $written) {
            $this->assertStringContainsString('Thank you, Wanjiru. One step is left.', $written);
            $this->assertStringContainsString('Pay KSh 46,500 by M-Pesa to confirm it.', $written);
            $this->assertStringContainsString('held for you until '.$order->held_until->format('g:i a').' today', $written);
            $this->assertStringContainsString(route('orders.pay', $order), $written);
            $this->assertStringContainsString('Amani sofa', $written);
            $this->assertStringContainsString('Riara Court, Kilimani, Nairobi', $written);
            $this->assertStringContainsString('KSh 6,413.79 VAT', $written);
        }

        Mail::assertSent(CustomerEmail::class, fn (CustomerEmail $mail) => $mail->hasTo('wanjiru@sebule.jeffgicharu.com')
            && $mail->hasSubject("We have your order {$order->number}")
            && $mail->hasReplyTo('hello@sebule.test'));
    }

    public function test_a_hold_that_ends_after_midnight_is_written_with_its_date(): void
    {
        $this->travelTo('2026-10-14 23:55:00');

        $order = $this->placeOrder();

        $this->assertStringContainsString('held for you until '.$order->held_until->format('g:i a').' on 15 October', $this->emails($order)->sole()->body);
    }

    public function test_account_emails_say_nothing_about_prices(): void
    {
        $customer = Customer::create(['name' => 'Wanjiru Kamau', 'email' => 'wanjiru@sebule.jeffgicharu.com', 'password' => 'a-good-password']);

        $this->post(route('account.password.email'), ['email' => $customer->email]);

        $email = Message::query()->where('recipient', $customer->email)->sole();

        $this->assertStringContainsString('Sebule, Ngong Road, Nairobi.', $email->html);
        $this->assertStringNotContainsString('VAT', $email->html);
    }

    public function test_each_step_of_an_order_writes_to_the_customer(): void
    {
        $order = $this->paidOrder(piece: $this->piece('Tana dining table', madeToOrder: true));
        $move = app(MoveOrderOn::class);

        foreach ([OrderStatus::InProduction, OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Delivered] as $step) {
            $order = $move($order, $step, $this->staff);
        }

        $this->assertSame([
            "We have your order {$order->number}",
            "Payment received for order {$order->number}",
            "Order {$order->number} is being made",
            "Order {$order->number} is ready for delivery",
            "Order {$order->number} is on its way",
            "Order {$order->number} has been delivered",
        ], $this->emails($order)->pluck('subject')->all());

        $this->assertTrue($this->emails($order)->every(fn (Message $email) => $email->status === MessageStatus::Sent));
        Mail::assertSentCount(6);

        $paid = $this->emails($order)->firstWhere('kind', 'paid');
        $this->assertStringContainsString('Thank you, Wanjiru. Your order is confirmed.', $paid->body);
        $this->assertStringContainsString('We will call 0712 345 678 to agree a delivery day. Delivery to Nairobi takes 1 to 3 working days.', $paid->body);
        $this->assertStringContainsString('Pieces that are made to order take about 4 weeks.', $paid->body);
        $this->assertStringContainsString(route('orders.show', $order), $paid->body);

        $this->assertStringContainsString('Your order has left for A2, Riara Court, Kilimani, Nairobi. The driver will call 0712 345 678 before arriving.',
            $this->emails($order)->firstWhere('kind', 'out_for_delivery')->body);
    }

    public function test_an_order_that_is_collected_is_told_where_and_when_to_collect_it(): void
    {
        $order = $this->paidOrder(['method' => 'collection', 'county_id' => null]);
        $move = app(MoveOrderOn::class);
        $order = $move($order, OrderStatus::Ready, $this->staff);
        $order = $move($order, OrderStatus::Delivered, $this->staff);

        $ready = $this->emails($order)->firstWhere('kind', 'ready');

        $this->assertSame("Order {$order->number} is ready to collect", $ready->subject);
        $this->assertStringContainsString('Collect it from the Sebule showroom, Ngong Road, Nairobi, Monday to Saturday, 9 am to 6 pm.', $ready->body);
        $this->assertStringContainsString("Give this order number when you arrive: {$order->number}.", $ready->body);
        $this->assertSame("Order {$order->number} has been collected", $this->emails($order)->firstWhere('kind', 'delivered')->subject);
        $this->assertStringContainsString('We will write to you as soon as it is ready to collect', $this->emails($order)->firstWhere('kind', 'paid')->body);
    }

    public function test_a_payment_by_mpesa_is_acknowledged_with_its_receipt(): void
    {
        $order = $this->placeOrder();
        $payment = $this->prompt($order);

        app(RecordMpesaResult::class)($payment, '0', 'The service request is processed successfully.', 'TJA5K2PXQR', 46500);

        $this->assertStringContainsString('We have your payment by M-Pesa, receipt TJA5K2PXQR.', $this->emails($order)->firstWhere('kind', 'paid')->body);
    }

    public function test_a_failed_payment_is_written_about_once_however_many_times_it_fails(): void
    {
        $order = $this->placeOrder();

        app(RecordMpesaResult::class)($this->prompt($order, 'ws_CO_1'), '1032', 'Request cancelled by user');
        app(RecordMpesaResult::class)($this->prompt($order, 'ws_CO_2'), '1', 'The balance is insufficient for the transaction');

        $failed = $this->emails($order)->where('kind', 'payment_failed');

        $this->assertCount(1, $failed);
        $this->assertSame("Your payment for order {$order->number} did not go through", $failed->first()->subject);
        $this->assertStringContainsString('Nothing was taken from you. Your pieces are still held for you until', $failed->first()->body);
        $this->assertStringContainsString(route('orders.pay', $order), $failed->first()->body);
        $this->assertSame(2, $order->events()->where('type', OrderEventType::PaymentFailed)->count());
    }

    public function test_a_cancelled_order_tells_the_customer_why_and_what_happens_to_their_money(): void
    {
        $unpaid = app(CancelOrder::class)($this->placeOrder(), 'The fabric is no longer made.', $this->staff);
        $cancelled = $this->emails($unpaid)->firstWhere('kind', 'cancelled');

        $this->assertStringContainsString('The fabric is no longer made.', $cancelled->body);
        $this->assertStringContainsString('Nothing was taken from you.', $cancelled->body);

        $order = $this->placeOrder();
        app(RecordMpesaResult::class)($this->prompt($order), '0', 'Processed', 'TJA5K2PXQR', 46500);
        $paid = app(CancelOrder::class)($order, 'The frame was damaged in the workshop.', User::factory()->create());

        $this->assertStringContainsString('Your payment will be sent back to the M-Pesa number that paid, within two working days.',
            $this->emails($paid)->firstWhere('kind', 'cancelled')->body);
    }

    public function test_a_customer_who_gave_no_email_is_sent_none(): void
    {
        $order = $this->paidOrder(['email' => null]);

        $this->assertCount(0, $this->emails($order));
        Mail::assertNothingSent();

        // The text messages are still written down, unsent.
        $texts = Message::where('order_id', $order->id)->where('channel', MessageChannel::Sms)->orderBy('id')->get();

        $this->assertSame(['placed', 'paid'], $texts->pluck('kind')->all());
        $this->assertSame('254712345678', $texts[0]->recipient);
        $this->assertSame(MessageStatus::NotSent, $texts[0]->status);
        $this->assertSame('Text messages are switched off.', $texts[0]->error);
        $this->assertSame("Sebule: we have your order {$order->number}. Pay KSh 46,500 to confirm it: ".route('orders.pay', $order), $texts[0]->body);
        $this->assertLessThanOrEqual(160, strlen($texts[1]->body));
    }

    public function test_where_email_is_not_set_up_the_message_is_kept_and_marked_not_sent(): void
    {
        config(['mail.default' => 'log']);

        $order = $this->placeOrder();
        $email = $this->emails($order)->sole();

        $this->assertSame([MessageStatus::NotSent, 'Email sending is not switched on.'], [$email->status, $email->error]);
        $this->assertStringContainsString('One step is left', $email->html);
        Mail::assertNothingSent();
    }

    public function test_one_address_cannot_be_sent_more_than_its_share_in_a_day(): void
    {
        config(['store.email.address_daily_limit' => 2]);

        $order = $this->paidOrder();
        app(MoveOrderOn::class)($order, OrderStatus::Ready, $this->staff);

        $this->assertSame([MessageStatus::Sent, MessageStatus::Sent, MessageStatus::NotSent], $this->emails($order)->pluck('status')->all());
        $this->assertSame('This address has had its limit of emails for today.', $this->emails($order)->last()->error);
        Mail::assertSentCount(2);

        // Another customer is still written to.
        $other = $this->placeOrder(['email' => 'achieng@sebule.jeffgicharu.com']);
        $this->assertSame(MessageStatus::Sent, $this->emails($other)->sole()->status);
    }

    public function test_the_store_stops_sending_when_it_reaches_its_limit_for_the_day(): void
    {
        config(['store.email.daily_limit' => 1]);

        $first = $this->placeOrder(['email' => 'achieng@sebule.jeffgicharu.com']);
        $second = $this->placeOrder(['email' => 'kamau@sebule.jeffgicharu.com']);

        $this->assertSame(MessageStatus::Sent, $this->emails($first)->sole()->status);
        $this->assertSame([MessageStatus::NotSent, 'The store has reached its limit of emails for today.'],
            [$this->emails($second)->sole()->status, $this->emails($second)->sole()->error]);

        // Yesterday's mail does not count against today.
        Message::query()->update(['created_at' => now()->subDay()]);
        $this->assertSame(MessageStatus::Sent, $this->emails($this->placeOrder(['email' => 'njeri@sebule.jeffgicharu.com']))->sole()->status);
    }

    public function test_an_email_the_service_refuses_is_marked_as_not_delivered(): void
    {
        $order = $this->placeOrder();
        $email = $this->emails($order)->sole();
        $email->update(['status' => MessageStatus::Waiting, 'sent_at' => null]);

        (new DeliverMessage($email))->failed(new RuntimeException('550 The recipient address was rejected.'));

        $this->assertSame([MessageStatus::Failed, '550 The recipient address was rejected.'], [$email->refresh()->status, $email->error]);
    }

    public function test_an_email_already_sent_is_not_sent_again(): void
    {
        $order = $this->placeOrder();
        $email = $this->emails($order)->sole();

        (new DeliverMessage($email))->handle();

        Mail::assertSentCount(1);
    }

    public function test_things_the_customer_did_themselves_are_not_written_about(): void
    {
        $order = $this->placeOrder();
        $before = Message::count();

        app(TellCustomer::class)($order, OrderEventType::Resumed);
        app(TellCustomer::class)($order, OrderEventType::Expired);

        $this->assertSame($before, Message::count());
    }

    /** @return Collection<int, Message> */
    private function emails(Order $order)
    {
        return Message::where('order_id', $order->id)->where('channel', MessageChannel::Email)->orderBy('id')->get();
    }

    private function prompt(Order $order, string $id = 'ws_CO_1'): Payment
    {
        return $order->payments()->create([
            'method' => PaymentMethod::Mpesa, 'state' => PaymentState::Pending, 'amount' => $order->total, 'charged' => $order->total,
            'phone' => '254712345678', 'checkout_request_id' => $id,
        ]);
    }
}
