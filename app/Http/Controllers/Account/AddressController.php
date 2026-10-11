<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The places a customer has deliveries sent, kept for next time.
 */
class AddressController extends Controller
{
    /** Enough for home, work and the family; more is somebody filling the table. */
    public const LIMIT = 8;

    public function index(): View
    {
        return view('shop.account.addresses', [
            'customer' => $this->customer(),
            'addresses' => $this->customer()->addresses()->with('county.zone')->get(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($this->customer()->addresses()->count() >= self::LIMIT) {
            return redirect()->route('account.addresses.index')->with('shop.problem', 'You have '.self::LIMIT.' addresses saved, which is the most an account keeps. Remove one to add another.');
        }

        return view('shop.account.address', ['customer' => $this->customer(), 'address' => new CustomerAddress, 'counties' => $this->counties()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        if ($customer->addresses()->count() >= self::LIMIT) {
            return redirect()->route('account.addresses.index');
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($customer, $data): void {
            // The first address saved is the usual one; a later one is only if the customer says so.
            $first = ! $customer->addresses()->exists();
            $address = $customer->addresses()->create(['is_default' => $first] + $data);

            if (! $first && $data['is_default']) {
                $this->prefer($customer, $address);
            }
        });

        return redirect()->route('account.addresses.index')->with('shop.notice', 'The address is saved.');
    }

    public function edit(CustomerAddress $address): View
    {
        $this->mine($address);

        return view('shop.account.address', ['customer' => $this->customer(), 'address' => $address, 'counties' => $this->counties($address->county_id)]);
    }

    public function update(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->mine($address);

        $data = $this->validated($request, $address->county_id);

        DB::transaction(function () use ($address, $data): void {
            $address->update(collect($data)->except('is_default')->all());

            if ($data['is_default']) {
                $this->prefer($this->customer(), $address);
            }
        });

        return redirect()->route('account.addresses.index')->with('shop.notice', 'The address is saved.');
    }

    public function destroy(CustomerAddress $address): RedirectResponse
    {
        $this->mine($address);

        DB::transaction(function () use ($address): void {
            $address->delete();

            // Deliveries need somewhere to default to while any address is left.
            if ($address->is_default && ($next = $this->customer()->addresses()->first())) {
                $next->update(['is_default' => true]);
            }
        });

        return redirect()->route('account.addresses.index')->with('shop.notice', 'The address is removed.');
    }

    public function prefer(Customer $customer, CustomerAddress $address): void
    {
        $customer->addresses()->whereKeyNot($address->getKey())->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }

    public function makeDefault(CustomerAddress $address): RedirectResponse
    {
        $this->mine($address);

        DB::transaction(fn () => $this->prefer($this->customer(), $address));

        return redirect()->route('account.addresses.index')->with('shop.notice', 'Deliveries will go to that address unless you choose another at checkout.');
    }

    /**
     * @return array{county_id: int, town: string, estate: string, house: string|null, directions: string|null, is_default: bool}
     */
    private function validated(Request $request, ?int $keep = null): array
    {
        $data = $request->validate([
            // A county the store delivers to, or the one the address already has.
            'county_id' => ['required', 'integer', Rule::in($this->counties($keep)->pluck('id'))],
            'town' => ['required', 'string', 'max:120'],
            'estate' => ['required', 'string', 'max:160'],
            'house' => ['nullable', 'string', 'max:60'],
            'directions' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'county_id.required' => 'Choose the county.',
            'county_id.in' => 'We do not deliver to that county yet.',
            'town.required' => 'Tell us the town or area.',
            'estate.required' => 'Tell us the estate, building or street.',
        ]);

        return [
            'county_id' => (int) $data['county_id'],
            'town' => trim($data['town']),
            'estate' => trim($data['estate']),
            'house' => filled($data['house'] ?? null) ? trim($data['house']) : null,
            'directions' => filled($data['directions'] ?? null) ? trim($data['directions']) : null,
            'is_default' => (bool) ($data['is_default'] ?? false),
        ];
    }

    /** @return Collection<int, County> */
    private function counties(?int $keep = null)
    {
        return County::query()
            ->where(fn ($query) => $query->deliveredTo()->when($keep, fn ($query) => $query->orWhere('id', $keep)))
            ->orderBy('name')
            ->get();
    }

    /** One customer's address is nobody else's to see or change. */
    private function mine(CustomerAddress $address): void
    {
        abort_unless($address->customer_id === $this->customer()->getKey(), 404);
    }

    private function customer(): Customer
    {
        return Auth::guard('customer')->user();
    }
}
