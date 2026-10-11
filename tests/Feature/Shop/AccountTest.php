<?php

namespace Tests\Feature\Shop;

use App\Cart\CartManager;
use App\Enums\MessageStatus;
use App\Enums\StaffRole;
use App\Mail\CustomerEmail;
use App\Messages\TellAccountHolder;
use App\Models\Cart;
use App\Models\County;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\PlacesOrders;
use Tests\TestCase;

/**
 * Customer accounts: opening one, signing in and out, the pages inside, a
 * forgotten password, and the cart and checkout of a signed-in customer.
 */
class AccountTest extends TestCase
{
    use PlacesOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_visitor_opens_an_account_and_is_signed_in(): void
    {
        $this->get(route('account.register'))->assertOk()
            ->assertSeeText('You never need one to buy from us.')
            ->assertSee('<meta name="robots" content="noindex">', escape: false);

        $this->post(route('account.register.store'), [
            'name' => ' Wanjiru Kamau ', 'email' => 'Wanjiru@Example.com', 'phone' => '0712 345 678', 'password' => 'a-good-password',
        ])->assertRedirect(route('account.show'))->assertSessionHas('shop.notice', 'Your account is open, Wanjiru. Welcome to Sebule.');

        $customer = Customer::sole();

        $this->assertSame(['Wanjiru Kamau', 'wanjiru@example.com', '254712345678', false], [$customer->name, $customer->email, $customer->phone, $customer->marketing_opt_in]);
        $this->assertTrue(Hash::check('a-good-password', $customer->password));
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
    }

    public function test_offers_are_sent_only_to_a_customer_who_ticked_the_box(): void
    {
        $this->post(route('account.register.store'), ['name' => 'Achieng Otieno', 'email' => 'achieng@example.com', 'password' => 'a-good-password', 'marketing' => '1']);

        $this->assertTrue(Customer::sole()->marketing_opt_in);

        // And they can stop them from their details, whenever they like.
        $this->put(route('account.details.update'), ['name' => 'Achieng Otieno', 'email' => 'achieng@example.com', 'marketing' => '0'])
            ->assertSessionHas('shop.notice', 'Your details are saved.');

        $this->assertFalse(Customer::sole()->marketing_opt_in);
    }

    public function test_an_account_needs_a_name_a_usable_email_and_a_password_worth_having(): void
    {
        $this->customer();

        $this->post(route('account.register.store'), ['name' => '', 'email' => 'WANJIRU@example.com', 'phone' => '12345', 'password' => 'short'])
            ->assertSessionHasErrors([
                'name' => 'Tell us your name.',
                'email' => 'There is already an account with that email address. Sign in instead, or reset the password if you have forgotten it.',
                'phone' => 'Enter a Kenyan mobile number, such as 0712 345 678, or leave it empty.',
                'password' => 'Use at least 8 characters for your password.',
            ]);

        $this->assertSame(1, Customer::count());
        $this->assertGuest('customer');
    }

    public function test_a_customer_signs_in_with_their_email_and_password(): void
    {
        $customer = $this->customer();

        $this->get(route('account.login'))->assertOk()->assertSeeText('Keep me signed in on this device');

        $this->post(route('account.login.store'), ['email' => 'Wanjiru@example.com', 'password' => 'a-good-password'])
            ->assertRedirect(route('account.show'));

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_a_wrong_password_and_an_unknown_address_get_the_same_answer(): void
    {
        $this->customer();
        $same = 'That email address and password do not match an account.';

        $this->from(route('account.login'))->post(route('account.login.store'), ['email' => 'wanjiru@example.com', 'password' => 'not-the-password'])
            ->assertRedirect(route('account.login'))->assertSessionHasErrors(['email' => $same]);
        $this->post(route('account.login.store'), ['email' => 'nobody@example.com', 'password' => 'a-good-password'])
            ->assertSessionHasErrors(['email' => $same]);

        $this->assertGuest('customer');
    }

    public function test_guessing_a_password_is_stopped_after_a_few_tries(): void
    {
        $this->customer();

        foreach (range(1, 5) as $try) {
            $this->post(route('account.login.store'), ['email' => 'wanjiru@example.com', 'password' => 'guess-'.$try])->assertRedirect();
        }

        $this->post(route('account.login.store'), ['email' => 'wanjiru@example.com', 'password' => 'a-good-password'])->assertTooManyRequests();
        $this->assertGuest('customer');
    }

    public function test_the_account_pages_are_for_a_signed_in_customer_only(): void
    {
        foreach (['account.show', 'account.details', 'account.addresses.index', 'account.addresses.create'] as $page) {
            $this->get(route($page))->assertRedirect(route('account.login'));
        }

        $this->actingAs($this->customer(), 'customer');

        foreach (['account.show', 'account.details', 'account.addresses.index', 'account.addresses.create'] as $page) {
            $this->get(route($page))->assertOk()->assertSee('<meta name="robots" content="noindex">', escape: false);
        }

        // Somebody already signed in has no use for the sign-in pages.
        $this->get(route('account.login'))->assertRedirect(route('account.show'));
        $this->get(route('account.register'))->assertRedirect(route('account.show'));
    }

    public function test_a_customer_is_never_staff_and_staff_are_never_a_customer(): void
    {
        $this->actingAs($this->customer(), 'customer');
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->post(route('account.logout'));
        $this->actingAs(User::factory()->create(['role' => StaffRole::Owner]), 'web');

        $this->get(route('account.show'))->assertRedirect(route('account.login'));
        $this->get(route('home'))->assertOk()->assertSee('Sign in to your account');
    }

    public function test_signing_out_ends_the_customers_session_and_leaves_staff_signed_in(): void
    {
        $staff = User::factory()->create(['role' => StaffRole::Owner]);
        $this->actingAs($staff, 'web')->actingAs($this->customer(), 'customer');

        $this->post(route('account.logout'))->assertRedirect(route('home'))->assertSessionHas('shop.notice', 'You are signed out.');

        $this->assertGuest('customer');
        $this->assertAuthenticatedAs($staff, 'web');
    }

    public function test_the_header_shows_who_is_signed_in(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('aria-label="Sign in to your account"', escape: false)->assertSee(route('account.login'));

        $this->actingAs($this->customer(), 'customer');

        $this->get(route('home'))->assertOk()
            ->assertSee('aria-label="Your account, signed in as Wanjiru Kamau"', escape: false)
            ->assertDontSee('aria-label="Sign in to your account"', escape: false);
    }

    public function test_the_orders_page_lists_the_orders_placed_from_the_account_and_nobody_elses(): void
    {
        $customer = $this->customer();
        $mine = $this->placeOrder();
        $mine->update(['customer_id' => $customer->id]);
        $guest = $this->placeOrder(['name' => 'Achieng Otieno']);
        $someoneElse = $this->placeOrder();
        $someoneElse->update(['customer_id' => $this->customer('kamau@example.com')->id]);

        $this->actingAs($customer, 'customer')->get(route('account.show'))->assertOk()
            ->assertSeeText('Order '.$mine->number)
            ->assertSeeText('Awaiting payment')
            ->assertSeeText('KSh 46,500')
            ->assertSee(route('orders.show', $mine))
            ->assertDontSeeText($guest->number)
            ->assertDontSeeText($someoneElse->number);

        $this->actingAs($this->customer('njeri@example.com'), 'customer')->get(route('account.show'))->assertOk()
            ->assertSeeText('No orders yet');
    }

    public function test_a_customer_saves_addresses_and_the_first_becomes_the_usual_one(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $this->post(route('account.addresses.store'), $this->address())
            ->assertRedirect(route('account.addresses.index'))->assertSessionHas('shop.notice', 'The address is saved.');
        $this->post(route('account.addresses.store'), $this->address(['county_id' => $this->county('Kiambu'), 'town' => 'Ruaka', 'estate' => 'Maple Court', 'house' => '']));

        [$home, $second] = $customer->addresses()->orderBy('id')->get()->all();

        $this->assertSame([true, false], [$home->is_default, $second->is_default]);
        $this->assertSame('A2, Riara Court, Kilimani, Nairobi', $home->line());
        $this->assertSame('Maple Court, Ruaka, Kiambu', $second->line());

        $this->get(route('account.addresses.index'))->assertOk()
            ->assertSeeTextInOrder(['Your usual address', 'A2, Riara Court', 'Maple Court', 'Make this my usual address']);

        // Choosing another usual address moves the mark, and removing the usual one passes it on.
        $this->post(route('account.addresses.default', $second))->assertRedirect(route('account.addresses.index'));
        $this->assertSame([false, true], [$home->refresh()->is_default, $second->refresh()->is_default]);

        $this->delete(route('account.addresses.destroy', $second))->assertSessionHas('shop.notice', 'The address is removed.');
        $this->assertTrue($home->refresh()->is_default);
        $this->assertSame(1, $customer->addresses()->count());
    }

    public function test_an_address_can_be_changed_and_made_the_usual_one(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $this->post(route('account.addresses.store'), $this->address());
        $this->post(route('account.addresses.store'), $this->address(['town' => 'Lavington']));
        $second = $customer->addresses()->where('town', 'Lavington')->sole();

        $this->get(route('account.addresses.edit', $second))->assertOk()->assertSee('value="Lavington"', escape: false);

        $this->put(route('account.addresses.update', $second), $this->address(['town' => 'Karen', 'directions' => 'Green gate', 'is_default' => '1']))
            ->assertRedirect(route('account.addresses.index'));

        $this->assertSame(['Karen', 'Green gate', true], [$second->refresh()->town, $second->directions, $second->is_default]);
        $this->assertSame(1, $customer->addresses()->where('is_default', true)->count());
    }

    public function test_an_address_needs_a_county_we_deliver_to_a_town_and_an_estate(): void
    {
        $this->actingAs($this->customer(), 'customer');
        County::where('name', 'Lamu')->update(['delivery_zone_id' => null]);

        $this->post(route('account.addresses.store'), ['county_id' => $this->county('Lamu'), 'town' => '', 'estate' => ''])
            ->assertSessionHasErrors(['county_id' => 'We do not deliver to that county yet.', 'town' => 'Tell us the town or area.', 'estate' => 'Tell us the estate, building or street.']);

        $this->assertSame(0, CustomerAddress::count());
    }

    public function test_one_customers_addresses_are_out_of_reach_of_another(): void
    {
        $this->actingAs($this->customer(), 'customer')->post(route('account.addresses.store'), $this->address());
        $address = CustomerAddress::sole();

        $this->actingAs($this->customer('kamau@example.com'), 'customer');

        $this->get(route('account.addresses.edit', $address))->assertNotFound();
        $this->put(route('account.addresses.update', $address), $this->address(['town' => 'Mine now']))->assertNotFound();
        $this->post(route('account.addresses.default', $address))->assertNotFound();
        $this->delete(route('account.addresses.destroy', $address))->assertNotFound();

        $this->assertSame('Kilimani', $address->refresh()->town);
    }

    public function test_an_account_keeps_only_so_many_addresses(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        foreach (range(1, 8) as $number) {
            $this->post(route('account.addresses.store'), $this->address(['house' => 'House '.$number]));
        }

        $this->post(route('account.addresses.store'), $this->address(['house' => 'One too many']))->assertRedirect(route('account.addresses.index'));
        $this->get(route('account.addresses.create'))->assertRedirect(route('account.addresses.index'));

        $this->assertSame(8, $customer->addresses()->count());
    }

    public function test_a_customer_changes_their_details_but_cannot_take_anothers_email(): void
    {
        $customer = $this->customer();
        $this->customer('kamau@example.com');
        $this->actingAs($customer, 'customer');

        $this->put(route('account.details.update'), ['name' => 'Wanjiru K. Kamau', 'email' => 'wk@example.com', 'phone' => '+254 733 900 100'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Wanjiru K. Kamau', 'wk@example.com', '254733900100'], [$customer->refresh()->name, $customer->email, $customer->phone]);

        $this->put(route('account.details.update'), ['name' => 'Wanjiru', 'email' => 'kamau@example.com'])
            ->assertSessionHasErrors(['email' => 'Another account already uses that email address.']);
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $this->put(route('account.password.change'), ['current_password' => 'a-wrong-guess', 'password' => 'another-good-one'])
            ->assertSessionHasErrorsIn('password', ['current_password' => 'That is not your current password.']);
        $this->assertTrue(Hash::check('a-good-password', $customer->refresh()->password));

        $this->put(route('account.password.change'), ['current_password' => 'a-good-password', 'password' => 'another-good-one'])
            ->assertSessionHas('shop.notice', 'Your password is changed.');

        $this->assertTrue(Hash::check('another-good-one', $customer->refresh()->password));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_a_forgotten_password_is_replaced_through_a_link_sent_by_email(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $customer = $this->customer();

        $this->get(route('account.password.request'))->assertOk();
        $this->post(route('account.password.email'), ['email' => 'Wanjiru@example.com'])
            ->assertSessionHas('shop.notice', 'If that address has an account, a link to choose a new password is on its way to it. The link works for one hour.');

        // The message log keeps the email but not the link that opens the account.
        $logged = Message::sole();

        $this->assertSame([$customer->id, 'password_reset', 'wanjiru@example.com', MessageStatus::Sent], [$logged->customer_id, $logged->kind, $logged->recipient, $logged->status]);
        $this->assertStringContainsString(TellAccountHolder::PRIVATE_LINK, $logged->html);
        $this->assertStringNotContainsString('reset-password', $logged->html.$logged->body);

        $link = null;
        Mail::assertSent(CustomerEmail::class, function (CustomerEmail $mail) use (&$link): bool {
            $html = $mail->render();
            preg_match('~href="([^"]*/account/reset-password/[^"]+)"~', $html, $found);
            $link = html_entity_decode($found[1] ?? '');

            return $mail->hasTo('wanjiru@example.com') && $link !== '' && ! str_contains($html, TellAccountHolder::PRIVATE_LINK);
        });

        $this->get($link)->assertOk()->assertSee('value="wanjiru@example.com"', escape: false)->assertSee('<meta name="referrer" content="no-referrer">', escape: false);

        $token = Str::of(parse_url($link, PHP_URL_PATH))->afterLast('/')->toString();

        $this->post(route('account.password.update'), ['token' => $token, 'email' => 'wanjiru@example.com', 'password' => 'short'])
            ->assertSessionHasErrors(['password' => 'Use at least 8 characters for your password.']);
        $this->post(route('account.password.update'), ['token' => $token, 'email' => 'wanjiru@example.com', 'password' => 'a-brand-new-password'])
            ->assertRedirect(route('account.login'))->assertSessionHas('shop.notice', 'Your password is changed. Sign in with the new one.');

        $this->assertTrue(Hash::check('a-brand-new-password', $customer->refresh()->password));

        // The link works once.
        $this->post(route('account.password.update'), ['token' => $token, 'email' => 'wanjiru@example.com', 'password' => 'yet-another-password'])
            ->assertSessionHasErrors(['email' => 'This link has expired or has already been used. Ask for a new one below.']);
        $this->assertTrue(Hash::check('a-brand-new-password', $customer->refresh()->password));
    }

    public function test_asking_for_a_link_for_an_address_with_no_account_says_the_same_and_sends_nothing(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $this->post(route('account.password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('shop.notice', 'If that address has an account, a link to choose a new password is on its way to it. The link works for one hour.');

        Mail::assertNothingSent();
        $this->assertSame(0, Message::count());
    }

    public function test_a_cart_filled_before_signing_in_becomes_the_accounts_cart(): void
    {
        $customer = $this->customer();
        $piece = $this->piece();
        $this->carry($this->post(route('cart.items.store'), ['variant' => $piece->id, 'quantity' => 2]));

        $this->post(route('account.login.store'), ['email' => 'wanjiru@example.com', 'password' => 'a-good-password']);

        $cart = Cart::sole();

        $this->assertSame($customer->id, $cart->customer_id);
        $this->assertSame(2, $cart->pieces());
    }

    public function test_a_cart_follows_its_customer_to_another_device_and_takes_in_what_was_there(): void
    {
        $customer = $this->customer();
        $sofa = $this->piece('Amani sofa', stock: 3);
        $basket = $this->piece('Kilifi planter basket');

        // On the laptop, signed in: two sofas.
        $this->actingAs($customer, 'customer');
        $this->post(route('cart.items.store'), ['variant' => $sofa->id, 'quantity' => 2]);
        $kept = Cart::sole();
        $this->assertSame($customer->id, $kept->customer_id);

        // On the phone, not yet signed in: two more sofas and a basket.
        $this->app['auth']->guard('customer')->logout();
        $this->defaultCookies = [];
        $this->carry($this->post(route('cart.items.store'), ['variant' => $sofa->id, 'quantity' => 2]));
        $this->post(route('cart.items.store'), ['variant' => $basket->id, 'quantity' => 1]);
        $this->assertSame(2, Cart::count());

        $signedIn = $this->post(route('account.login.store'), ['email' => 'wanjiru@example.com', 'password' => 'a-good-password']);

        // One cart, holding both, with the sofas stopped at the three in stock.
        $cart = Cart::sole();

        $this->assertTrue($cart->is($kept));
        $this->assertSame([3, 1], [$cart->items()->where('product_variant_id', $sofa->id)->value('quantity'), $cart->items()->where('product_variant_id', $basket->id)->value('quantity')]);
        $this->assertSame($kept->token, $signedIn->getCookie(CartManager::COOKIE)->getValue());

        $this->carry($signedIn);
        $this->get(route('cart.show'))->assertOk()->assertSeeText('Amani sofa')->assertSeeText('Kilifi planter basket');
    }

    public function test_signing_out_takes_the_accounts_cart_off_the_device(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $added = $this->post(route('cart.items.store'), ['variant' => $this->piece()->id, 'quantity' => 1]);
        $this->carry($added);

        $this->post(route('account.logout'));

        // The browser still holds the old cookie in this test; the cart must not answer to it.
        $this->get(route('cart.show'))->assertOk()->assertDontSeeText('Amani sofa');
        $this->assertSame(1, Cart::where('customer_id', $customer->id)->count());

        // It is waiting when they sign in again, even on a browser with no cookie at all.
        $this->defaultCookies = [];
        $this->actingAs($customer, 'customer')->get(route('cart.show'))->assertOk()->assertSeeText('Amani sofa');
    }

    public function test_checkout_starts_from_what_the_account_holds(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $this->post(route('account.addresses.store'), $this->address(['directions' => 'Green gate opposite the pharmacy']));
        $this->post(route('account.addresses.store'), $this->address(['county_id' => $this->county('Kiambu'), 'town' => 'Ruaka', 'estate' => 'Maple Court', 'house' => 'B4']));
        $second = $customer->addresses()->where('town', 'Ruaka')->sole();
        $this->carry($this->post(route('cart.items.store'), ['variant' => $this->piece()->id, 'quantity' => 1]));

        $this->get(route('checkout.details'))->assertOk()
            ->assertSeeText('Signed in as Wanjiru Kamau.')
            ->assertDontSeeText('Have an account? Sign in')
            ->assertSee('value="Wanjiru Kamau"', escape: false)
            ->assertSee('value="0712 345 678"', escape: false)
            ->assertSee('value="wanjiru@example.com"', escape: false)
            ->assertSee('value="Kilimani"', escape: false)
            ->assertSee('value="Riara Court"', escape: false)
            ->assertSeeText('Green gate opposite the pharmacy')
            ->assertSeeText('Use B4, Maple Court, Ruaka, Kiambu')
            ->assertSeeText('Keep this address in my account for next time.');

        $this->get(route('checkout.details', ['address' => $second->id]))->assertOk()
            ->assertSee('value="Ruaka"', escape: false)
            ->assertSee('value="Maple Court"', escape: false)
            ->assertDontSee('value="Kilimani"', escape: false);

        // An address that is not theirs changes nothing.
        $this->get(route('checkout.details', ['address' => 999999]))->assertOk()->assertSee('value="Kilimani"', escape: false);
    }

    public function test_an_order_placed_while_signed_in_belongs_to_the_account_and_can_keep_its_address(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $this->carry($this->post(route('cart.items.store'), ['variant' => $this->piece()->id, 'quantity' => 1]));

        $this->post(route('checkout.details.save'), [
            'name' => 'Wanjiru Kamau', 'phone' => '0712 345 678', 'email' => 'wanjiru@example.com', 'method' => 'delivery',
            'county_id' => $this->county('Nairobi'), 'town' => 'Kilimani', 'estate' => 'Riara Court', 'house' => 'A2', 'keep_address' => '1',
        ])->assertRedirect(route('checkout.review'));

        $review = $this->get(route('checkout.review'))->assertOk();
        preg_match('/name="key" value="([^"]+)"/', $review->getContent(), $key);

        $this->post(route('checkout.place'), ['key' => $key[1], 'total' => 46_500_00])->assertRedirect();

        $order = Order::sole();

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('A2, Riara Court, Kilimani, Nairobi', $customer->addresses()->sole()->line());
        $this->assertTrue($customer->addresses()->sole()->is_default);
        $this->assertSame(0, Cart::count());
        $this->get(route('account.show'))->assertOk()->assertSeeText('Order '.$order->number);
    }

    public function test_a_guest_is_offered_sign_in_at_checkout_and_comes_back_to_it(): void
    {
        $this->customer();
        $this->carry($this->post(route('cart.items.store'), ['variant' => $this->piece()->id, 'quantity' => 1]));

        $this->get(route('checkout.details'))->assertOk()
            ->assertSeeText('No account needed.')
            ->assertSee(route('account.login', ['next' => 'checkout']))
            ->assertDontSeeText('Keep this address in my account');

        $this->get(route('account.login', ['next' => 'checkout']))->assertOk()->assertSee('name="next" value="checkout"', escape: false);

        $this->post(route('account.login.store'), ['email' => 'wanjiru@example.com', 'password' => 'a-good-password', 'next' => 'checkout'])
            ->assertRedirect(route('checkout.details'));

        // Anything else in "next" is ignored, so the form cannot be used to send people elsewhere.
        $this->post(route('account.logout'));
        $this->post(route('account.login.store'), ['email' => 'wanjiru@example.com', 'password' => 'a-good-password', 'next' => 'https://example.com'])
            ->assertRedirect(route('account.show'));
    }

    private function customer(string $email = 'wanjiru@example.com'): Customer
    {
        return Customer::create(['name' => 'Wanjiru Kamau', 'email' => $email, 'phone' => '254712345678', 'password' => 'a-good-password']);
    }

    private function county(string $name): int
    {
        return County::where('name', $name)->value('id');
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function address(array $changes = []): array
    {
        return $changes + ['county_id' => $this->county('Nairobi'), 'town' => 'Kilimani', 'estate' => 'Riara Court', 'house' => 'A2', 'directions' => ''];
    }

    private function carry(TestResponse $response): void
    {
        $this->withCookie(CartManager::COOKIE, $response->getCookie(CartManager::COOKIE)->getValue());
    }
}
