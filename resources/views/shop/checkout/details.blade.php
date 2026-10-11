@php
    use App\Enums\DeliveryMethod;
    use App\Models\DeliveryZone;
    use App\Support\Money;
    use App\Support\Phone;

    $method = old('method', $details['method'] ?? DeliveryMethod::Delivery->value);
    $chosenCounty = (int) old('county_id', $details['county_id'] ?? 0);
    $note = fn ($county) => 'Delivery to '.$county->name.' is '.Money::format($county->zone->fee).', in '.$county->zone->estimate().'.';
    $showroom = config('store.showroom');
@endphp

<x-shop.layout title="Checkout" description="Tell us who the order is for and where it is going.">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <div class="wrap pt-6 lg:pt-8">
        <x-shop.steps :current="1" />

        <h1 class="mt-6 text-4xl lg:text-5xl">Your details</h1>
        @if ($customer)
            <p class="mt-3 max-w-xl text-ink-soft">Signed in as {{ $customer->name }}. We have filled in what your account holds; change anything that is different for this order.</p>
        @else
            <p class="mt-3 max-w-xl text-ink-soft">No account needed. We use these only to deliver your order and keep you posted on it.
                <a href="{{ route('account.login', ['next' => 'checkout']) }}" class="link whitespace-nowrap text-ink">Have an account? Sign in</a></p>
        @endif

        <div class="mt-8 grid gap-10 lg:grid-cols-12 lg:gap-12">
            {{-- The address is asked for only when the order is being delivered: the form hides it, without script, when collection is chosen. --}}
            <form method="post" action="{{ route('checkout.details.save') }}" novalidate
                class="space-y-10 lg:col-span-7 [&:has(input[value=collection]:checked)_.for-delivery]:hidden [&:has(input[value=delivery]:checked)_.for-collection]:hidden">
                @csrf

                @if ($errors->any())
                    <div role="alert" class="border border-clay-deep bg-clay/10 p-4">
                        <p class="font-semibold text-clay-deep">Please check {{ $errors->count() === 1 ? 'one thing' : $errors->count().' things' }} below.</p>
                    </div>
                @endif

                <fieldset class="space-y-5">
                    <legend class="font-display text-2xl">Who is the order for?</legend>

                    <x-shop.field name="name" label="Full name" :value="$details['name'] ?? null" required autocomplete="name" />
                    <x-shop.field name="phone" label="Mobile number" type="tel" :value="isset($details['phone']) ? Phone::display($details['phone']) : null" required
                        hint="We call or text this number about delivery." autocomplete="tel" inputmode="tel" placeholder="0712 345 678" />
                    <x-shop.field name="email" label="Email" type="email" :value="$details['email'] ?? null"
                        hint="For your receipt and order updates." autocomplete="email" />
                </fieldset>

                <fieldset>
                    <legend class="font-display text-2xl">How would you like to get it?</legend>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer gap-3 border border-ink/45 p-4 has-[:checked]:border-ink has-[:checked]:bg-tile has-[:checked]:ring-1 has-[:checked]:ring-ink">
                            <input type="radio" name="method" value="delivery" class="mt-1 size-4 accent-forest" @checked($method === 'delivery')>
                            <span>
                                <span class="block font-semibold">Deliver it to me</span>
                                <span class="mt-0.5 block text-sm text-ink-soft">To your door, anywhere in Kenya</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer gap-3 border border-ink/45 p-4 has-[:checked]:border-ink has-[:checked]:bg-tile has-[:checked]:ring-1 has-[:checked]:ring-ink">
                            <input type="radio" name="method" value="collection" class="mt-1 size-4 accent-forest" @checked($method === 'collection')>
                            <span>
                                <span class="block font-semibold">I will collect it</span>
                                <span class="mt-0.5 block text-sm text-ink-soft">Free, from our showroom in {{ $showroom['city'] }}</span>
                            </span>
                        </label>
                    </div>
                    @error('method')
                        <p class="mt-2 text-sm font-medium text-clay-deep">{{ $message }}</p>
                    @enderror

                    <div class="for-collection mt-5 border-l-2 border-sand pl-4 text-[0.95rem]">
                        <p class="font-semibold">Sebule showroom, {{ $showroom['street'] }}, {{ $showroom['city'] }}</p>
                        <p class="mt-1 text-ink-soft">{{ \App\Messages\OrderWording::hours() }}. We will write to you when your order is ready to collect.</p>
                    </div>

                    <div class="for-delivery mt-6 space-y-5">
                        @if ($addresses->isNotEmpty())
                            {{-- Each saved address is a link that fills the boxes below, so choosing one needs no script. --}}
                            <div class="border-l-2 border-sand pl-4">
                                <p class="text-[0.95rem] font-semibold">Your saved addresses</p>
                                <ul class="mt-2 space-y-1.5 text-[0.95rem]">
                                    @foreach ($addresses as $saved)
                                        <li><a href="{{ route('checkout.details', ['address' => $saved->id]) }}" class="link">Use {{ $saved->line() }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div>
                            <label for="county_id" class="block text-[0.95rem] font-semibold">County</label>
                            <select id="county_id" name="county_id" data-county
                                class="mt-2 min-h-12 w-full border bg-paper px-3 {{ $errors->has('county_id') ? 'border-clay-deep ring-1 ring-clay-deep' : 'border-ink/45' }}"
                                @error('county_id') aria-invalid="true" aria-describedby="county_id-error" @enderror>
                                <option value="">Choose a county</option>
                                @foreach ($counties as $county)
                                    <option value="{{ $county->id }}" data-note="{{ $note($county) }}" @selected($chosenCounty === $county->id)>{{ $county->name }}</option>
                                @endforeach
                            </select>
                            @error('county_id')
                                <p id="county_id-error" class="mt-1.5 text-sm font-medium text-clay-deep">{{ $message }}</p>
                            @enderror

                            {{-- Filled in by script as the county is chosen. Without script, the fee is on the next page and in the list below. --}}
                            <p data-county-note aria-live="polite" class="mt-2 text-[0.95rem] font-medium text-forest-deep">{{ ($chosen = $counties->firstWhere('id', $chosenCounty)) ? $note($chosen) : '' }}</p>

                            <details class="mt-2 text-sm">
                                <summary class="link inline cursor-pointer">Delivery fees for every county</summary>
                                <dl class="mt-3 divide-y divide-rule border-y border-rule">
                                    @foreach ($zones as $zone)
                                        <div class="py-3">
                                            <dt class="flex justify-between gap-4 font-semibold"><span>{{ $zone->name }}</span><span>{{ Money::format($zone->fee) }}</span></dt>
                                            <dd class="mt-1 text-ink-soft">{{ $zone->counties->pluck('name')->join(', ') }}. {{ ucfirst($zone->estimate()) }}.</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </details>
                        </div>

                        <x-shop.field name="town" label="Town or area" :value="$details['town'] ?? null" required autocomplete="address-level2" placeholder="Kilimani" />
                        <x-shop.field name="estate" label="Estate, building or street" :value="$details['estate'] ?? null" required autocomplete="address-line1" placeholder="Rose Avenue, Maple Court" />
                        <x-shop.field name="house" label="House or flat number" :value="$details['house'] ?? null" autocomplete="address-line2" placeholder="B4" />
                        <x-shop.field name="directions" label="Directions for the driver" :value="$details['directions'] ?? null" multiline
                            hint="A landmark, the gate colour, or anything that helps us find you." />

                        @if ($customer)
                            <label class="flex cursor-pointer items-start gap-3 text-[0.95rem]">
                                <input type="hidden" name="keep_address" value="0">
                                <input type="checkbox" name="keep_address" value="1" class="mt-1 size-4 accent-forest" @checked(old('keep_address', $details['keep_address'] ?? $addresses->isEmpty()))>
                                <span>Keep this address in my account for next time.</span>
                            </label>
                        @endif
                    </div>
                </fieldset>

                <label class="flex cursor-pointer items-start gap-3 text-[0.95rem]">
                    <input type="hidden" name="marketing" value="0">
                    <input type="checkbox" name="marketing" value="1" class="mt-1 size-4 accent-forest" @checked(old('marketing', $details['marketing'] ?? false))>
                    <span>Send me news of new pieces and offers now and then. <span class="text-ink-soft">You can stop these at any time.</span></span>
                </label>

                <p><button class="btn btn-primary min-h-12 w-full sm:w-auto sm:px-10">Continue to review</button></p>
            </form>

            <aside class="lg:col-span-5" aria-labelledby="checkout-summary">
                <div class="bg-tile p-6 lg:sticky lg:top-8 lg:p-8">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 id="checkout-summary" class="text-2xl">Your order</h2>
                        <a href="{{ route('cart.show') }}" class="link text-sm">Change</a>
                    </div>

                    <div class="mt-3"><x-shop.order-lines :lines="$cart->items->map->summary()" /></div>

                    <dl class="space-y-2 border-t border-rule pt-4 text-[0.95rem]">
                        <div class="flex justify-between gap-6"><dt>Pieces</dt><dd>{{ Money::format($cart->subtotal()) }}</dd></div>
                        <div class="flex justify-between gap-6 text-ink-soft"><dt>Delivery</dt><dd>Shown on the next page</dd></div>
                    </dl>
                </div>
            </aside>
        </div>
    </div>
</x-shop.layout>
