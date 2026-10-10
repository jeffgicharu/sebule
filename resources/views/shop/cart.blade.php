@php
    use App\Enums\Availability;
    use App\Http\Controllers\Shop\ProductController;
    use App\Models\CartItem;
    use App\Support\Money;

    $items = $cart?->items ?? collect();
    $blocked = $cart?->hasProblems() ?? false;
    $subtotal = $cart?->subtotal() ?? 0;
    $vat = (int) round($subtotal * config('store.vat_rate') / (10000 + config('store.vat_rate')));
@endphp

<x-shop.layout title="Your cart" description="The pieces you have chosen from Sebule.">
    <div class="wrap pt-6 lg:pt-8">
        <x-shop.breadcrumbs :trail="[['Your cart', null]]" />

        <h1 class="mt-6 text-4xl lg:mt-8 lg:text-6xl">Your cart</h1>

        @if ($items->isEmpty())
            <div class="mt-8 border border-rule px-6 py-16 text-center">
                <h2 class="text-2xl">There is nothing in your cart yet.</h2>
                <p class="mt-3 text-ink-soft">Find a piece you like and it will be kept here for you.</p>
                <p class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('shop.category', 'living-room') }}" class="btn btn-primary">Shop living room</a>
                    <a href="{{ route('home') }}#collections" class="btn btn-quiet">See the collections</a>
                </p>
            </div>
        @else
            <div class="mt-8 grid gap-10 lg:grid-cols-12 lg:gap-12">
                <ul class="divide-y divide-rule self-start border-y border-rule lg:col-span-8">
                    @foreach ($items as $item)
                        @php
                            $variant = $item->variant;
                            $product = $variant->product;
                            $photo = $variant->photo();
                            $label = $variant->label() === 'Standard' ? null : $variant->label();
                            $address = route('shop.product', array_filter(['product' => $product, 'v' => $label ? ProductController::key($variant) : null]));
                            $problem = $item->problem();
                            $limit = $item->limit();
                            $availability = $variant->availability();
                        @endphp
                        <li class="grid grid-cols-[5.5rem_1fr] gap-x-4 gap-y-3 py-6 sm:grid-cols-[8rem_1fr_auto] sm:gap-x-6">
                            <a href="{{ $address }}" class="tile block aspect-square self-start" tabindex="-1" aria-hidden="true">
                                @if ($photo)
                                    <x-shop.photo :media="$photo" alt="" sizes="8rem" class="h-full w-full object-contain {{ $problem ? 'opacity-60' : '' }}" />
                                @endif
                            </a>

                            <div class="min-w-0">
                                <h2 class="text-lg leading-snug sm:text-xl"><a href="{{ $address }}">{{ \App\Support\Text::unbroken($product->name) }}</a></h2>
                                <p class="mt-1 text-[0.95rem] text-ink-soft">
                                    @if ($label){{ $label }} <span aria-hidden="true">&middot;</span> @endif{{ Money::format($item->price()) }} each
                                </p>

                                @if ($problem === CartItem::UNAVAILABLE)
                                    <p class="mt-2 font-medium text-clay-deep">This piece is no longer for sale. Take it out to carry on.</p>
                                @elseif ($problem === CartItem::OUT_OF_STOCK)
                                    <p class="mt-2 font-medium text-clay-deep">This has sold out since you chose it. Take it out to carry on.</p>
                                @elseif ($problem === CartItem::SHORT)
                                    <p class="mt-2 font-medium text-clay-deep">Only {{ $limit }} {{ $limit === 1 ? 'is' : 'are' }} left in stock.</p>
                                @elseif ($availability === Availability::MadeToOrder)
                                    <p class="mt-2 text-sm text-ink-soft">Made to order in about {{ $variant->lead_time_weeks }} weeks</p>
                                @elseif ($availability === Availability::LowStock)
                                    <p class="mt-2 text-sm text-ink-soft">Only {{ $variant->stock_on_hand }} left in stock</p>
                                @else
                                    <p class="mt-2 text-sm text-ink-soft">In stock</p>
                                @endif

                                @if (! $problem && $item->priceChanged())
                                    <p class="mt-2 text-sm font-medium text-clay-deep">
                                        The price has gone {{ $item->price() > $item->unit_price ? 'up' : 'down' }} from {{ Money::format($item->unit_price) }} since you added this.
                                    </p>
                                @endif
                            </div>

                            <div class="col-span-2 flex items-center justify-between gap-4 sm:col-span-1 sm:flex-col sm:items-end sm:justify-start">
                                <p class="order-last font-display text-xl sm:order-none">{{ Money::format($item->total()) }}</p>

                                <div class="flex items-center gap-4">
                                    @if ($problem === CartItem::SHORT)
                                        <form method="post" action="{{ route('cart.items.update', $item) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="quantity" value="{{ $limit }}">
                                            <button class="btn btn-quiet min-h-11 px-4">Change to {{ $limit }}</button>
                                        </form>
                                    @elseif (! $problem)
                                        {{-- One step at a time, each a form of its own, so it works without script. --}}
                                        <div class="flex items-center border border-ink/60" role="group" aria-label="Quantity of {{ $product->name }}">
                                            <form method="post" action="{{ route('cart.items.update', $item) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="quantity" value="{{ max(1, $item->quantity - 1) }}">
                                                <button class="grid size-11 place-items-center text-xl disabled:text-ink/30" aria-label="One fewer" @disabled($item->quantity <= 1)>&minus;</button>
                                            </form>
                                            <span class="min-w-8 text-center font-medium" aria-live="polite">{{ $item->quantity }}</span>
                                            <form method="post" action="{{ route('cart.items.update', $item) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="quantity" value="{{ $item->quantity + 1 }}">
                                                <button class="grid size-11 place-items-center text-xl disabled:text-ink/30" aria-label="One more" @disabled($item->quantity >= $limit)>+</button>
                                            </form>
                                        </div>
                                    @endif

                                    <form method="post" action="{{ route('cart.items.destroy', $item) }}">
                                        @csrf @method('DELETE')
                                        <button class="link min-h-11 text-sm" aria-label="Remove {{ $product->name }} from the cart">Remove</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <aside class="lg:col-span-4" aria-labelledby="cart-summary">
                    <div class="bg-tile p-6 lg:sticky lg:top-8 lg:p-8">
                        <h2 id="cart-summary" class="text-2xl">Summary</h2>

                        <dl class="mt-5 space-y-3 text-[0.95rem]">
                            <div class="flex justify-between gap-6">
                                <dt>{{ $cart->pieces() }} {{ Str::plural('piece', $cart->pieces()) }}</dt>
                                <dd>{{ Money::format($subtotal) }}</dd>
                            </div>
                            <div class="flex justify-between gap-6 text-ink-soft">
                                <dt>Delivery</dt>
                                <dd>Worked out at checkout</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-6 border-t border-rule pt-4">
                                <dt class="font-semibold">Total before delivery</dt>
                                <dd class="shrink-0 whitespace-nowrap font-display text-2xl">{{ Money::format($subtotal) }}</dd>
                            </div>
                        </dl>

                        <p class="mt-2 text-sm text-ink-soft">Includes {{ Money::format($vat) }} VAT</p>

                        @if ($blocked)
                            <p class="mt-6 font-medium text-clay-deep">Sort out the {{ Str::plural('piece', $items->filter->problem()->count()) }} marked in your cart before you check out.</p>
                            <p class="mt-4"><span class="btn w-full cursor-not-allowed bg-ink/10 text-ink-soft">Check out</span></p>
                        @else
                            <p class="mt-6"><a href="{{ route('checkout.details') }}" class="btn btn-primary w-full">Check out</a></p>
                        @endif

                        <p class="mt-4 text-center"><a href="{{ route('home') }}" class="link text-sm">Carry on shopping</a></p>
                    </div>
                </aside>
            </div>
        @endif
    </div>
</x-shop.layout>
