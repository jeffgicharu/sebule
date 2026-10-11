<?php

namespace App\Http\Controllers\Shop;

use App\Cart\CartManager;
use App\Checkout\CartHasChanged;
use App\Checkout\PlaceOrder;
use App\Checkout\Totals;
use App\Enums\DeliveryMethod;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutDetailsRequest;
use App\Models\Cart;
use App\Models\County;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryZone;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Checkout in two pages that work without script: the customer's details, then
 * a review of the whole order with its delivery fee before anything is placed.
 */
class CheckoutController extends Controller
{
    /** Where the details are kept between the two pages. */
    private const DETAILS = 'checkout.details';

    /** Where the key that makes placing the order repeatable is kept. */
    private const KEY = 'checkout.key';

    public function __construct(private readonly CartManager $carts) {}

    public function details(Request $request): View|RedirectResponse
    {
        if (($cart = $this->cart()) instanceof RedirectResponse) {
            return $cart;
        }

        $customer = Auth::guard('customer')->user();
        $addresses = $customer?->addresses()->with('county')->get() ?? collect();

        return view('shop.checkout.details', [
            'cart' => $cart,
            'details' => $this->startingDetails($request, $customer, $addresses),
            'customer' => $customer,
            'addresses' => $addresses,
            'counties' => County::query()->deliveredTo()->with('zone')->orderBy('name')->get(),
            'zones' => DeliveryZone::query()->active()->has('counties')->with('counties')->orderBy('position')->get(),
        ]);
    }

    public function saveDetails(CheckoutDetailsRequest $request): RedirectResponse
    {
        $request->session()->put(self::DETAILS, $request->details() + ['keep_address' => $request->boolean('keep_address')]);

        // One key for this checkout, kept through any number of visits to the review page.
        if (! $request->session()->has(self::KEY)) {
            $request->session()->put(self::KEY, (string) Str::uuid());
        }

        return redirect()->route('checkout.review');
    }

    public function review(Request $request): View|RedirectResponse
    {
        if (($cart = $this->cart()) instanceof RedirectResponse) {
            return $cart;
        }

        $details = $request->session()->get(self::DETAILS);

        if (! $details || ! $request->session()->has(self::KEY)) {
            return redirect()->route('checkout.details');
        }

        $method = DeliveryMethod::from($details['method']);
        $county = $method === DeliveryMethod::Delivery
            ? County::query()->deliveredTo()->with('zone')->find($details['county_id'])
            : null;

        if ($method === DeliveryMethod::Delivery && $county === null) {
            return redirect()->route('checkout.details')->with('shop.problem', 'We no longer deliver to the county you chose. Please choose another.');
        }

        return view('shop.checkout.review', [
            'cart' => $cart,
            'details' => $details,
            'method' => $method,
            'county' => $county,
            'totals' => Totals::for($cart, $method, $county?->zone),
            'key' => $request->session()->get(self::KEY),
        ]);
    }

    public function place(Request $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'uuid'],
            'total' => ['required', 'integer', 'min:0'],
        ]);

        // A second tap, a refresh or a retry after a dropped connection lands on the order already placed.
        if ($placed = Order::query()->where('checkout_key', $data['key'])->first()) {
            return redirect()->route('orders.pay', $placed);
        }

        $cart = $this->carts->current();
        $details = $request->session()->get(self::DETAILS);

        if ($cart === null || ! $details || $request->session()->get(self::KEY) !== $data['key']) {
            return redirect()->route('cart.show')->with('shop.problem', 'Your checkout had expired, so nothing was ordered. Please check out again.');
        }

        try {
            $customer = Auth::guard('customer')->user();
            $order = $placeOrder($cart, $details, $data['key'], (int) $data['total'], $customer);
        } catch (CartHasChanged $changed) {
            return redirect()->route('cart.show')->with('shop.problem', $changed->getMessage().' Nothing was ordered. Please check your cart and try again.');
        }

        if ($customer !== null && ($details['keep_address'] ?? false)) {
            $this->keepAddress($customer, $order);
        }

        // The cart became the order, so the browser can let go of it.
        $request->session()->forget([self::DETAILS, self::KEY]);
        $this->carts->forget();

        return redirect()->route('orders.pay', $order);
    }

    /**
     * What the details page starts with. What was typed earlier in this
     * checkout comes first. A signed-in customer otherwise starts from their
     * account and the address they picked, or the one they prefer.
     *
     * @param  Collection<int, CustomerAddress>  $addresses
     * @return array<string, mixed>
     */
    private function startingDetails(Request $request, ?Customer $customer, Collection $addresses): array
    {
        $details = $request->session()->get(self::DETAILS, []);

        if ($customer === null) {
            return $details;
        }

        $picked = $addresses->firstWhere('id', (int) $request->query('address'));
        $address = $picked ?? ($details ? null : $addresses->first());

        return array_merge(
            ['name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email, 'marketing' => $customer->marketing_opt_in],
            $details,
            $address ? [
                'method' => DeliveryMethod::Delivery->value,
                'county_id' => $address->county_id,
                'town' => $address->town,
                'estate' => $address->estate,
                'house' => $address->house,
                'directions' => $address->directions,
            ] : [],
        );
    }

    /** Keep the address an order went to in the customer's account, unless it is already there. */
    private function keepAddress(Customer $customer, Order $order): void
    {
        if ($order->delivery_method !== DeliveryMethod::Delivery || $customer->addresses()->count() >= AddressController::LIMIT) {
            return;
        }

        $county = County::query()->where('name', $order->county)->value('id');
        $same = ['county_id' => $county, 'town' => $order->town, 'estate' => $order->estate, 'house' => $order->house];

        if ($county === null || $customer->addresses()->where($same)->exists()) {
            return;
        }

        $customer->addresses()->create($same + ['directions' => $order->directions, 'is_default' => ! $customer->addresses()->exists()]);
    }

    /** The cart being checked out, or the way back to the cart page when it cannot be. */
    private function cart(): Cart|RedirectResponse
    {
        $cart = $this->carts->current();

        if ($cart === null || $cart->items->isEmpty()) {
            return redirect()->route('cart.show');
        }

        if ($cart->hasProblems()) {
            return redirect()->route('cart.show')->with('shop.problem', 'Please sort out the pieces marked in your cart before you check out.');
        }

        return $cart;
    }
}
