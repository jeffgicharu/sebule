@props(['product', 'eager' => false, 'sizes' => '(min-width: 64rem) 25vw, (min-width: 40rem) 33vw, 50vw'])

@php
    use App\Support\Money;

    $photo = $product->mainPhoto();
    $swatches = $product->options->flatMap->values->filter(fn ($value) => filled($value->swatch));
    $onSale = $product->was_price !== null && $product->was_price > $product->price_from;
    $madeToOrder = $product->made_to_order_any !== null;
    $soldOut = ! $madeToOrder && (int) $product->stock_total === 0;
@endphp

{{-- The name takes whatever height the tallest card in its row needs, so prices and
     swatches line up across the row even when one name runs to a second line. --}}
<article class="group relative flex h-full flex-col">
    <div class="tile relative aspect-square">
        @if ($photo)
            <x-shop.photo :media="$photo" :alt="$product->name" :sizes="$sizes" :eager="$eager"
                class="h-full w-full object-contain transition duration-500 ease-out group-hover:scale-[1.04] {{ $soldOut ? 'opacity-60' : '' }}" />
        @endif

        <p class="absolute left-3 top-3 flex flex-col items-start gap-1.5 text-[0.6875rem] font-semibold uppercase tracking-[0.12em]">
            @if ($onSale)
                <span class="bg-clay px-2 py-1 text-paper">Offer</span>
            @endif
            @if ($soldOut)
                <span class="bg-ink px-2 py-1 text-paper">Out of stock</span>
            @elseif ($madeToOrder)
                <span class="border border-ink/60 bg-paper/80 px-2 py-1">Made to order</span>
            @endif
        </p>
    </div>

    <h3 class="mt-4 flex-1 text-[1.0625rem] leading-snug sm:text-lg">
        <a href="{{ route('shop.product', $product) }}" class="after:absolute after:inset-0">{{ \App\Support\Text::unbroken($product->name) }}</a>
    </h3>

    <p class="mt-1.5 flex flex-wrap items-baseline gap-x-2 text-[0.95rem]">
        <span class="{{ $onSale ? 'font-semibold text-clay' : 'text-ink' }}">
            @if ($product->price_to > $product->price_from)<span class="text-ink-soft">From</span> @endif{{ Money::format((int) $product->price_from) }}
        </span>
        @if ($onSale)
            <s class="text-sm text-ink-soft"><span class="sr-only">Was </span>{{ Money::format((int) $product->was_price) }}</s>
        @endif
    </p>

    @if ($swatches->count() > 1)
        <ul class="mt-2.5 flex h-3.5 gap-1.5" aria-label="Available in {{ $swatches->pluck('label')->join(', ', ' and ') }}">
            @foreach ($swatches->take(5) as $swatch)
                <li class="size-3.5 rounded-full ring-1 ring-ink/25" style="background-color: {{ $swatch->swatch }}" title="{{ $swatch->label }}"></li>
            @endforeach
        </ul>
    @else
        <div class="mt-2.5 h-3.5" aria-hidden="true"></div>
    @endif
</article>
