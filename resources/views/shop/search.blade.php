@php
    $found = $products?->total() ?? 0;
    $title = $searching ? 'Search: '.$term : 'Search';
@endphp

<x-shop.layout :title="$title" description="Search Sebule's furniture and homeware.">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <div class="wrap pt-8 lg:pt-12">
        <p class="eyebrow">Search</p>

        <form method="get" action="{{ route('shop.search') }}" role="search" class="mt-3 flex max-w-2xl gap-3">
            <label for="q" class="sr-only">Search for furniture and homeware</label>
            <input id="q" name="q" type="search" value="{{ $term }}" maxlength="{{ \App\Catalogue\ProductSearch::MAX_LENGTH }}" required
                placeholder="Sofa, dining table, lamp" autocomplete="off" enterkeyhint="search" @unless ($searching) autofocus @endunless
                class="min-h-12 min-w-0 flex-1 border border-ink/45 bg-paper px-3.5 text-lg">
            <button class="btn btn-primary min-h-12 shrink-0 px-6">Search</button>
        </form>

        @if (! $searching)
            <h1 class="mt-10 text-4xl lg:text-5xl">What are you looking for?</h1>
            <p class="mt-3 max-w-xl text-ink-soft">Search by what it is, the room it is for, its colour or its collection. Or start from a room.</p>
        @elseif ($found === 0)
            <h1 class="mt-10 text-4xl lg:text-5xl">Nothing matched &ldquo;{{ $term }}&rdquo;.</h1>
            <p class="mt-3 max-w-xl text-ink-soft">Try fewer words, or a plainer one: &ldquo;sofa&rdquo; finds more than &ldquo;three-seater settee&rdquo;. Or start from a room.</p>
        @else
            <h1 class="mt-10 text-4xl lg:text-5xl">{{ $found }} {{ Str::plural('piece', $found) }} for &ldquo;{{ $term }}&rdquo;</h1>
        @endif

        @if ($categories->isNotEmpty())
            <p class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-2 text-[0.95rem]">
                <span class="text-ink-soft">Go straight to</span>
                @foreach ($categories as $category)
                    <a href="{{ route('shop.category', $category) }}" class="border border-ink/45 px-3.5 py-1.5 font-medium hover:border-ink hover:bg-tile">{{ $category->name }}@if ($category->parent)<span class="font-normal text-ink-soft">, {{ $category->parent->name }}</span>@endif</a>
                @endforeach
            </p>
        @endif

        @if ($found > 0)
            <form method="get" action="{{ route('shop.search') }}" data-tidy-address class="mt-8 flex flex-wrap items-end justify-between gap-4 border-y border-rule py-4">
                <input type="hidden" name="q" value="{{ $term }}">
                <p class="text-sm text-ink-soft">
                    @if ($products->hasPages())
                        Showing {{ $products->firstItem() }} to {{ $products->lastItem() }} of {{ $found }}
                    @else
                        Showing all {{ $found }}
                    @endif
                </p>
                <div class="flex items-end gap-3">
                    <label class="block text-sm font-semibold">
                        Sort by
                        <select name="sort" data-default="relevance" onchange="this.form.requestSubmit()" class="mt-1.5 block min-h-11 w-full border border-rule bg-paper px-3 pr-8 text-[0.95rem] font-normal sm:w-auto sm:min-w-44">
                            @foreach (\App\Http\Controllers\Shop\SearchController::SORTS as $value => $text)
                                <option value="{{ $value }}" @selected($sort === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </label>
                    <noscript><button class="btn btn-quiet min-h-11">Apply</button></noscript>
                </div>
            </form>

            <ul class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-6 lg:gap-y-14">
                @foreach ($products as $product)
                    <li><x-shop.product-card :product="$product" :eager="$loop->index < 4" /></li>
                @endforeach
            </ul>

            <div class="mt-12">{{ $products->links('shop.pagination') }}</div>
        @else
            <ul class="mt-8 flex flex-wrap gap-3">
                @foreach ($rooms as $room)
                    <li><a href="{{ route('shop.category', $room) }}" class="btn btn-quiet">{{ $room->name }}</a></li>
                @endforeach
            </ul>
        @endif
    </div>
</x-shop.layout>
