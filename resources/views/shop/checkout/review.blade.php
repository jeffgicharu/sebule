@php
    use App\Enums\DeliveryMethod;
    use App\Support\Money;
    use App\Support\Phone;

    $delivering = $method === DeliveryMethod::Delivery;
    $showroom = config('store.showroom');
@endphp

<x-shop.layout title="Review your order" description="Check your order before you pay.">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <div class="wrap pt-6 lg:pt-8">
        <x-shop.steps :current="2" />

        <h1 class="mt-6 text-4xl lg:text-5xl">Review your order</h1>
        <p class="mt-3 max-w-xl text-ink-soft">Nothing is ordered until you press &ldquo;Place order and pay&rdquo;.</p>

        <div class="mt-8 grid gap-10 lg:grid-cols-12 lg:gap-12">
            <div class="space-y-8 lg:col-span-7">
                <section aria-labelledby="review-who" class="border-t border-rule pt-5">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 id="review-who" class="text-2xl">Who it is for</h2>
                        <a href="{{ route('checkout.details') }}" class="link text-sm" aria-label="Change who the order is for">Change</a>
                    </div>
                    <p class="mt-3 font-medium">{{ $details['name'] }}</p>
                    <p class="text-ink-soft">{{ Phone::display($details['phone']) }}@if ($details['email']) <span aria-hidden="true">&middot;</span> {{ $details['email'] }}@endif</p>
                </section>

                <section aria-labelledby="review-where" class="border-t border-rule pt-5">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 id="review-where" class="text-2xl">{{ $delivering ? 'Where it is going' : 'Collection' }}</h2>
                        <a href="{{ route('checkout.details') }}" class="link text-sm" aria-label="Change the delivery">Change</a>
                    </div>

                    @if ($delivering)
                        <p class="mt-3 font-medium">{{ implode(', ', array_filter([$details['house'], $details['estate'], $details['town']])) }}</p>
                        <p class="text-ink-soft">{{ $county->name }} County</p>
                        @if ($details['directions'])
                            <p class="mt-2 text-ink-soft">{{ $details['directions'] }}</p>
                        @endif
                        <p class="mt-3 text-[0.95rem]">Delivered in {{ $county->zone->estimate() }} once paid. Pieces that are made to order follow when they are ready.</p>
                    @else
                        <p class="mt-3 font-medium">Sebule showroom, {{ $showroom['street'] }}, {{ $showroom['city'] }}</p>
                        <p class="text-ink-soft">{{ \App\Messages\OrderWording::hours() }}. We will text you when your order is ready to collect.</p>
                    @endif
                </section>

                <section aria-labelledby="review-what" class="border-t border-rule pt-5">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 id="review-what" class="text-2xl">What you are ordering</h2>
                        <a href="{{ route('cart.show') }}" class="link text-sm" aria-label="Change what is in the cart">Change</a>
                    </div>
                    <div class="mt-1"><x-shop.order-lines :lines="$cart->items->map->summary()" /></div>
                </section>
            </div>

            <aside class="lg:col-span-5" aria-labelledby="review-total">
                <div class="bg-tile p-6 lg:sticky lg:top-8 lg:p-8">
                    <h2 id="review-total" class="text-2xl">What it comes to</h2>

                    <dl class="mt-5 space-y-3 text-[0.95rem]">
                        <div class="flex justify-between gap-6">
                            <dt>{{ $cart->pieces() }} {{ Str::plural('piece', $cart->pieces()) }}</dt>
                            <dd>{{ Money::format($totals->subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between gap-6">
                            <dt>{{ $delivering ? 'Delivery to '.$county->name : 'Collection from the showroom' }}</dt>
                            <dd>{{ $delivering ? Money::format($totals->deliveryFee) : 'Free' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6 border-t border-rule pt-4">
                            <dt class="font-semibold">Total to pay</dt>
                            <dd class="shrink-0 whitespace-nowrap font-display text-3xl">{{ Money::format($totals->total) }}</dd>
                        </div>
                    </dl>

                    <p class="mt-2 text-sm text-ink-soft">Includes {{ Money::format($totals->vatAmount) }} VAT at {{ rtrim(rtrim(number_format($totals->vatRate / 100, 2), '0'), '.') }}%</p>

                    {{-- The key makes this safe to send twice: a second press finds the order the first one placed. --}}
                    <form method="post" action="{{ route('checkout.place') }}" class="mt-6" data-once>
                        @csrf
                        <input type="hidden" name="key" value="{{ $key }}">
                        <input type="hidden" name="total" value="{{ $totals->total }}">
                        <button class="btn btn-primary min-h-12 w-full">Place order and pay</button>
                    </form>

                    <p class="mt-4 text-sm text-ink-soft">We hold your pieces for {{ config('store.hold_minutes') }} minutes while you pay with M-Pesa on the next page.</p>
                </div>
            </aside>
        </div>
    </div>
</x-shop.layout>
