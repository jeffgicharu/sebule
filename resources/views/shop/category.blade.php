@php
    use App\Catalogue\ProductListing;
    use App\Seo\StructuredData;

    $trail = $category->parent
        ? [[$category->parent->name, route('shop.category', $category->parent)], [$category->name, null]]
        : [[$category->name, null]];

    $active = collect($filters)->except('sort')->filter();
    $description = $category->seo_description ?: ($category->description ?: 'Shop '.strtolower($category->name).' from Sebule, made in Kenya and delivered across the country.');

    // Filters and sorting are views of the same page, so they share its address; a later page has its own.
    $page = $products->currentPage();
    $address = route('shop.category', array_filter(['category' => $category, 'page' => $page > 1 ? $page : null]));
    $title = ($category->seo_title ?: $category->name)
        .($page > 1 ? ', page '.$page : '')
        .($category->parent && ! $category->seo_title ? ' | '.$category->parent->name : '');
@endphp

<x-shop.layout :title="$title" :description="$description" :canonical="$address"
    :schema="[StructuredData::breadcrumbs($trail, $address), StructuredData::listing($category->name, $address, $description, $products->items())]">
    <div class="wrap pt-6 lg:pt-8">
        <x-shop.breadcrumbs :trail="$trail" />

        <header class="mt-6 max-w-3xl lg:mt-8">
            <h1 class="text-4xl lg:text-6xl">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="mt-4 text-lg text-ink-soft">{{ $category->description }}</p>
            @endif
        </header>

        {{-- Sub-categories of the room, or the other sub-categories beside this one. --}}
        @if ($siblings->isNotEmpty())
            <nav aria-label="{{ $category->parent?->name ?? $category->name }}" class="-mx-4 mt-7 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                <ul class="flex gap-2 whitespace-nowrap text-[0.95rem] sm:flex-wrap">
                    @if ($category->parent)
                        <li><a href="{{ route('shop.category', $category->parent) }}" class="block rounded-full border border-rule px-4 py-2 hover:border-ink">All {{ strtolower($category->parent->name) }}</a></li>
                    @endif
                    @foreach ($siblings as $sibling)
                        <li>
                            <a href="{{ route('shop.category', $sibling) }}" @if ($sibling->is($category)) aria-current="page" @endif
                                class="block rounded-full border px-4 py-2 {{ $sibling->is($category) ? 'border-ink bg-ink text-paper' : 'border-rule hover:border-ink' }}">{{ $sibling->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        {{-- Filters are a plain form, so every choice is in the address and works without script. --}}
        <form method="get" action="{{ route('shop.category', $category) }}" data-tidy-address class="mt-8 border-y border-rule py-4">
            <div class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:items-end sm:gap-4">
                @foreach ([
                    'price' => ['Price', collect(ProductListing::PRICE_BANDS)->map(fn ($band) => $band[2])->all()],
                    'colour' => ['Colour or finish', $colours->mapWithKeys(fn ($colour) => [$colour->label => $colour->label])->all()],
                    'availability' => ['Availability', ProductListing::AVAILABILITY],
                ] as $name => [$label, $options])
                    @if ($options)
                        <label class="block text-sm">
                            <span class="eyebrow block">{{ $label }}</span>
                            <select name="{{ $name }}" onchange="this.form.requestSubmit()" class="mt-1.5 min-h-11 w-full border border-rule bg-paper px-3 pr-8 text-[0.95rem] sm:w-auto sm:min-w-44">
                                <option value="">Any</option>
                                @foreach ($options as $value => $text)
                                    <option value="{{ $value }}" @selected(($filters[$name] ?? null) === (string) $value)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                @endforeach

                <label class="block text-sm sm:ml-auto">
                    <span class="eyebrow block">Sort by</span>
                    <select name="sort" data-default="featured" onchange="this.form.requestSubmit()" class="mt-1.5 min-h-11 w-full border border-rule bg-paper px-3 pr-8 text-[0.95rem] sm:w-auto sm:min-w-44">
                        @foreach (ProductListing::SORTS as $value => $text)
                            <option value="{{ $value }}" @selected(($filters['sort'] ?? 'featured') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </label>

                <noscript><button class="btn btn-primary col-span-2">Apply</button></noscript>
            </div>
        </form>

        <p class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink-soft" role="status">
            <span>{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</span>
            @if ($active->isNotEmpty())
                <a href="{{ route('shop.category', ['category' => $category] + array_filter(['sort' => $filters['sort'] ?? null])) }}" class="link text-ink">Clear filters</a>
            @endif
        </p>

        @if ($products->isEmpty())
            <div class="mt-8 border border-rule px-6 py-16 text-center">
                <h2 class="text-2xl">Nothing matches those filters.</h2>
                <p class="mt-3 text-ink-soft">Try a wider price range or another colour.</p>
                <p class="mt-6"><a href="{{ route('shop.category', $category) }}" class="btn btn-quiet">Show all {{ strtolower($category->name) }}</a></p>
            </div>
        @else
            <ul class="mt-6 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-6 lg:gap-y-14">
                @foreach ($products as $product)
                    <li><x-shop.product-card :product="$product" :eager="$loop->index < 4" /></li>
                @endforeach
            </ul>

            {{ $products->links('shop.pagination') }}
        @endif
    </div>
</x-shop.layout>
