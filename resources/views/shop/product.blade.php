@php
    use App\Enums\Availability;
    use App\Catalogue\ProductImage;
    use App\Http\Controllers\Shop\ProductController;
    use App\Seo\StructuredData;
    use App\Support\Money;

    $category = $product->category;
    $trail = array_values(array_filter([
        $category->parent ? [$category->parent->name, route('shop.category', $category->parent)] : null,
        [$category->name, route('shop.category', $category)],
        [$product->name, null],
    ]));

    $availability = $selected->availability();
    $option = $product->options->first();
    $collection = $product->collections->first();
    $size = collect([$selected->width_cm, $selected->depth_cm, $selected->height_cm])->filter();
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 1), '0'), '.');

    // Every colour is the same page to a search engine, filed under the address without a colour.
    $address = route('shop.product', $product);
    $inColour = $option && $selected->label() !== $defaultLabel ? ' in '.$selected->label() : '';
    $title = $product->seo_title ?: $product->name.$inColour;
    $description = $product->seo_description
        ?: trim(($product->summary ? Str::finish($product->summary, '.').' ' : '').Money::format($selected->price).' including VAT, delivered across Kenya.');

    $lead = $photos->first();
    $image = $lead ? [
        'url' => StructuredData::photoUrl($lead),
        'alt' => $product->name.$inColour,
    ] + ($lead->hasGeneratedConversion(ProductImage::SHARE) ? ['width' => 1200, 'height' => 1200] : []) : null;
@endphp

<x-shop.layout :title="$title" :description="$description" :image="$image" type="product" :canonical="$address"
    :schema="[StructuredData::breadcrumbs($trail, $address), StructuredData::product($product, $photos)]">
    <x-slot:head>
        <meta property="product:brand" content="{{ config('app.name') }}">
        <meta property="product:price:amount" content="{{ StructuredData::amount($selected->price) }}">
        <meta property="product:price:currency" content="{{ StructuredData::CURRENCY }}">
        <meta property="product:availability" content="{{ $availability === Availability::OutOfStock ? 'out of stock' : ($availability === Availability::MadeToOrder ? 'available for order' : 'in stock') }}">
        <meta property="product:condition" content="new">
        <meta property="product:retailer_item_id" content="{{ $selected->sku }}">
        {{-- Arriving from another page, the first frame waits for the main photo, which the photo being opened glides into. --}}
        <link rel="expect" href="#piece-photo" blocking="render">
    </x-slot:head>

    <div class="wrap pt-6 lg:pt-8">
        <x-shop.breadcrumbs :trail="$trail" />

        <div class="mt-6 grid gap-8 lg:mt-8 lg:grid-cols-12 lg:gap-12">
            {{-- Photos: the first one large, the rest in pairs, with nothing to swipe past. Each is a link to its
                 largest size: with script it opens over the page to be looked at closely, without it the photo opens by itself. --}}
            <div class="lg:col-span-7">
                <ul class="grid grid-cols-2 gap-3 lg:gap-4">
                    @forelse ($photos as $photo)
                        @php $alt = $loop->first ? $product->name.($option ? ' in '.$selected->label() : '') : $product->name.', photo '.$loop->iteration; @endphp
                        {{-- An odd one out at the end takes the full width, so no half of the grid is left empty. --}}
                        <li class="tile {{ $loop->first || ($loop->last && $loop->count % 2 === 0) ? 'col-span-2' : '' }} aspect-square"
                            @if ($loop->first) id="piece-photo" data-piece-main @endif>
                            <a href="{{ $photo->hasGeneratedConversion('large') ? $photo->getUrl('large') : $photo->getUrl() }}" data-zoom
                                class="block h-full w-full cursor-zoom-in" aria-label="Look closer: {{ $alt }}">
                                <x-shop.photo :media="$photo" :eager="$loop->first" :alt="$alt"
                                    :sizes="$loop->first ? '(min-width: 64rem) 58vw, 100vw' : '(min-width: 64rem) 29vw, 50vw'"
                                    class="h-full w-full object-contain" />
                            </a>
                        </li>
                    @empty
                        <li class="tile col-span-2 flex aspect-square items-center justify-center text-ink-soft">Photo coming soon</li>
                    @endforelse
                </ul>

                {{-- Where a photo opens to be looked at closely. One click or tap on it there shows it at its full size. --}}
                <dialog data-zoom-dialog class="zoom" aria-label="Photo, enlarged">
                    <form method="dialog">
                        <button class="btn btn-primary zoom-close">Close</button>
                    </form>
                    <div class="zoom-stage"><img src="data:," alt=""></div>
                </dialog>

                @if ($photosAreOfDefault)
                    <p class="mt-3 text-sm text-ink-soft">Shown in {{ $defaultLabel }}. The piece is the same in {{ $selected->label() }}.</p>
                @elseif ($inColour && $photos->contains(fn ($photo) => str_contains($photo->file_name, '_room.')))
                    <p class="mt-3 text-sm text-ink-soft">The room photo shows the piece in {{ $defaultLabel }}.</p>
                @endif
            </div>

            <div class="lg:col-span-5">
                <div class="lg:sticky lg:top-8">
                    @if ($collection)
                        <p class="eyebrow"><a href="{{ route('shop.collection', $collection) }}" class="hover:text-ink">{{ $collection->name }} collection</a></p>
                    @endif

                    <h1 class="mt-2 text-4xl lg:text-5xl">{{ \App\Support\Text::unbroken($product->name) }}</h1>

                    @if ($product->summary)
                        <p class="mt-4 text-lg text-ink-soft">{{ $product->summary }}</p>
                    @endif

                    <p class="mt-6 flex flex-wrap items-baseline gap-x-3">
                        <span class="font-display text-3xl {{ $selected->isOnSale() ? 'text-clay' : '' }}">{{ Money::format($selected->price) }}</span>
                        @if ($selected->isOnSale())
                            <s class="text-lg text-ink-soft"><span class="sr-only">Was </span>{{ Money::format($selected->compare_at_price) }}</s>
                        @endif
                        <span class="text-sm text-ink-soft">including VAT</span>
                    </p>

                    <p class="mt-4 flex items-center gap-2.5 font-medium">
                        <span aria-hidden="true" class="size-2.5 rounded-full {{ match ($availability) {
                            Availability::InStock => 'bg-forest',
                            Availability::LowStock => 'bg-clay',
                            Availability::MadeToOrder => 'bg-sand ring-1 ring-ink/40',
                            Availability::OutOfStock => 'bg-ink/40',
                        } }}"></span>
                        <span>
                            @switch($availability)
                                @case(Availability::InStock) In stock and ready to deliver @break
                                @case(Availability::LowStock) Only {{ $selected->stock_on_hand }} left in stock @break
                                @case(Availability::MadeToOrder) Made to order in about {{ $selected->lead_time_weeks }} weeks @break
                                @case(Availability::OutOfStock) Out of stock in {{ $option ? $selected->label() : 'this piece' }} @break
                            @endswitch
                        </span>
                    </p>

                    {{-- Each choice is a link, so the colour is part of the address and the page works without script. --}}
                    @if ($option && $product->variants->count() > 1)
                        <div class="mt-7 border-t border-rule pt-6">
                            <p class="text-sm"><span class="eyebrow">{{ $option->name }}</span> <span class="ml-2 font-medium">{{ $selected->label() }}</span></p>

                            <ul class="mt-3 flex flex-wrap gap-3">
                                @foreach ($product->variants as $variant)
                                    @php
                                        $value = $variant->optionValues->first();
                                        $isSelected = $variant->is($selected);
                                    @endphp
                                    <li>
                                        <a href="{{ route('shop.product', ['product' => $product, 'v' => ProductController::key($variant)]) }}"
                                            @if ($isSelected) aria-current="true" @endif
                                            class="flex min-h-11 items-center gap-2.5 rounded-full border py-1.5 pl-1.5 pr-4 text-[0.95rem] transition-colors {{ $isSelected ? 'border-ink bg-tile font-semibold' : 'border-rule hover:border-ink' }}">
                                            @if ($value?->swatch)
                                                <span aria-hidden="true" class="size-8 rounded-full ring-1 ring-ink/20" style="background-color: {{ $value->swatch }}"></span>
                                            @else
                                                <span class="pl-2.5"></span>
                                            @endif
                                            <span>{{ $variant->label() }}</span>
                                            @if ($variant->availability() === Availability::OutOfStock)
                                                <span class="text-sm font-normal text-ink-soft">(out of stock)</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- A plain form: the piece goes into the cart with or without script. With it, the photo flies to the cart first. --}}
                    @if ($availability->canBeBought())
                        <form method="post" action="{{ route('cart.items.store') }}" class="mt-7 flex gap-3" data-to-cart>
                            @csrf
                            <input type="hidden" name="variant" value="{{ $selected->getKey() }}">

                            <label for="quantity" class="sr-only">Quantity</label>
                            <select id="quantity" name="quantity" class="min-h-12 border border-ink bg-paper px-3 pr-8 text-[0.95rem] font-medium">
                                @foreach (range(1, min($selected->purchasableLimit(), 10)) as $count)
                                    <option value="{{ $count }}">{{ $count }}</option>
                                @endforeach
                            </select>

                            <button class="btn btn-primary min-h-12 flex-1">Add to cart</button>
                        </form>
                    @else
                        <p class="mt-7">
                            <span class="btn min-h-12 w-full cursor-not-allowed bg-ink/10 text-ink-soft">Out of stock</span>
                        </p>
                    @endif

                    {{-- A chat with the store that opens already saying which piece it is about. --}}
                    @if ($chat = \App\Support\WhatsApp::link('Hello Sebule, I have a question about the '.$product->name.($selected->optionValues->isNotEmpty() ? ' in '.$selected->label() : '').': '.url()->full()))
                        <p class="mt-4 text-[0.95rem]">
                            <a href="{{ $chat }}" rel="noopener" class="link">Ask about this piece on WhatsApp</a>
                        </p>
                    @endif

                    <dl class="mt-7 divide-y divide-rule border-y border-rule text-[0.95rem]">
                        @if ($size->count() === 3)
                            <div class="flex justify-between gap-6 py-3">
                                <dt class="text-ink-soft">Size</dt>
                                <dd class="text-right">{{ $number($selected->width_cm) }} wide &times; {{ $number($selected->depth_cm) }} deep &times; {{ $number($selected->height_cm) }} high, in cm</dd>
                            </div>
                        @endif
                        @if ($selected->weight_kg)
                            <div class="flex justify-between gap-6 py-3">
                                <dt class="text-ink-soft">Weight</dt>
                                <dd>{{ $number($selected->weight_kg) }} kg</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-ink-soft">Assembly</dt>
                            <dd>{{ $product->assembly_required ? 'Some assembly needed' : 'Arrives ready to use' }}</dd>
                        </div>
                        @if ($product->warranty_months)
                            <div class="flex justify-between gap-6 py-3">
                                <dt class="text-ink-soft">Warranty</dt>
                                <dd>{{ $product->warranty_months % 12 === 0 ? ($product->warranty_months / 12).' '.Str::plural('year', $product->warranty_months / 12) : $product->warranty_months.' months' }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-ink-soft">Code</dt>
                            <dd class="text-sm">{{ $selected->sku }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- The longer read, set like a catalogue entry. --}}
        <section class="mt-16 grid gap-10 border-t border-rule pt-12 lg:mt-24 lg:grid-cols-12">
            <h2 class="text-3xl lg:col-span-4" data-reveal>About this piece</h2>

            <div class="space-y-8 lg:col-span-8" data-reveal>
                @if ($product->description)
                    <div class="prose-shop max-w-2xl text-lg">{!! str($product->description)->sanitizeHtml() !!}</div>
                @endif

                <dl class="grid max-w-2xl gap-8 sm:grid-cols-2">
                    @if ($product->materials)
                        <div>
                            <dt class="eyebrow">Materials</dt>
                            <dd class="mt-2 text-ink-soft">{{ $product->materials }}</dd>
                        </div>
                    @endif
                    @if ($product->care)
                        <div>
                            <dt class="eyebrow">Care</dt>
                            <dd class="mt-2 text-ink-soft">{{ $product->care }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </section>

        @if ($related->isNotEmpty())
            <section class="mt-16 lg:mt-24">
                <div class="flex items-end justify-between gap-6 border-b border-rule pb-5" data-reveal>
                    <h2 class="text-3xl">More {{ strtolower($category->name) }}</h2>
                    <a href="{{ route('shop.category', $category) }}" class="link shrink-0 font-semibold">See all</a>
                </div>

                <ul class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-4 lg:gap-x-6">
                    @foreach ($related as $item)
                        <li><x-shop.product-card :product="$item" /></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-shop.layout>
