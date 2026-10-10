<x-shop.layout>
    {{-- Hero. On a phone the words sit on paper above a photo cut for that shape;
         from tablet up they sit on the quiet left side of the wide photo. --}}
    <section class="relative md:min-h-[min(44vw,40rem)]">
        <div class="wrap relative z-10 py-10 md:absolute md:inset-0 md:flex md:items-center md:py-0">
            <div class="max-w-xl md:max-w-[34%]">
                <p class="eyebrow">Made in Kenya</p>
                <h1 class="mt-3 text-[2.5rem] sm:text-5xl lg:text-[3.6rem]">Furniture for the way Kenya lives.</h1>
                <p class="mt-5 max-w-md text-lg text-ink-soft md:text-ink">Designed in Nairobi and built in solid mvule, mahogany and Meru oak. Delivered to your door anywhere in the country.</p>
                <p class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('shop.category', 'living-room') }}" class="btn btn-primary">Shop living room</a>
                    <a href="#collections" class="btn btn-quiet md:bg-paper/70">See the collections</a>
                </p>
            </div>
        </div>

        <div aria-hidden="true" class="pointer-events-none absolute inset-y-0 left-0 z-[5] hidden w-[58%] bg-gradient-to-r from-paper/90 via-paper/60 to-transparent md:block"></div>

        <picture>
            <source media="(min-width: 48rem)" type="image/webp"
                srcset="{{ asset('images/site/hero-living-1600.webp') }} 1600w, {{ asset('images/site/hero-living-2400.webp') }} 2400w" sizes="100vw">
            <source type="image/webp"
                srcset="{{ asset('images/site/hero-living-phone-480.webp') }} 480w, {{ asset('images/site/hero-living-phone-800.webp') }} 800w" sizes="100vw">
            <img src="{{ asset('images/site/hero-living-1600.jpg') }}" alt="The Amani sofa in Sand in a bright Nairobi living room, with a sisal rug and a monstera by the window"
                width="1600" height="800" fetchpriority="high" class="aspect-[4/5] w-full object-cover md:absolute md:inset-0 md:aspect-auto md:h-full">
        </picture>
    </section>

    {{-- Rooms --}}
    <section class="wrap mt-16 lg:mt-24">
        <div class="flex items-end justify-between gap-6 border-b border-rule pb-5">
            <h2 class="text-3xl lg:text-4xl">Shop by room</h2>
            <p class="eyebrow hidden sm:block">{{ $rooms->count() }} rooms</p>
        </div>

        {{-- Seven rooms: one wide tile then pairs on a phone; three large then four smaller from tablet up. --}}
        <ul class="mt-8 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-12 lg:gap-x-6">
            @foreach ($rooms as $room)
                <li class="{{ $loop->first ? 'col-span-2' : '' }} {{ $loop->index < 3 ? 'md:col-span-4' : 'md:col-span-3' }}">
                    <a href="{{ route('shop.category', $room) }}" class="group block h-full">
                        <div class="tile {{ $loop->first ? 'aspect-[4/3] md:aspect-square' : 'aspect-square' }}">
                            @if ($room->lead?->mainPhoto())
                                <x-shop.photo :media="$room->lead->mainPhoto()" alt="" :eager="$loop->first"
                                    :sizes="$loop->index < 3 ? '(min-width: 48rem) 33vw, '.($loop->first ? '100vw' : '50vw') : '(min-width: 48rem) 25vw, 50vw'"
                                    class="h-full w-full object-contain transition duration-500 ease-out group-hover:scale-[1.04]" />
                            @endif
                        </div>
                        <h3 class="mt-4 text-xl lg:text-2xl">{{ $room->name }}</h3>
                        <p class="mt-1 text-sm text-ink-soft">{{ $room->children->pluck('name')->take(3)->join(', ') }}</p>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Collections --}}
    <section id="collections" class="mt-20 scroll-mt-8 bg-tile py-16 lg:mt-28 lg:py-24">
        <div class="wrap">
            <p class="eyebrow">Collections</p>
            <h2 class="mt-3 max-w-2xl text-3xl lg:text-5xl">Pieces designed to live together.</h2>

            <div class="mt-12 space-y-16 lg:space-y-20">
                @foreach ($collections as $collection)
                    <div class="grid gap-8 lg:grid-cols-12 lg:gap-10">
                        <div class="lg:col-span-4 {{ $loop->even ? 'lg:order-last' : '' }}">
                            <p class="font-display text-6xl text-sand lg:text-7xl" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                            <h3 class="mt-2 text-3xl">{{ $collection->name }}</h3>
                            <p class="mt-3 max-w-sm text-ink-soft">{{ $collection->description }}</p>
                            <p class="mt-5"><a href="{{ route('shop.collection', $collection) }}" class="link font-semibold">See the {{ $collection->name }} collection</a></p>
                        </div>

                        <ul class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-4 lg:col-span-8 lg:gap-x-6">
                            @foreach ($collection->showcase as $product)
                                <li class="[&_.tile]:bg-paper"><x-shop.product-card :product="$product" sizes="(min-width: 64rem) 16vw, (min-width: 40rem) 25vw, 50vw" /></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Dining --}}
    <section class="wrap mt-16 lg:mt-24">
        <a href="{{ route('shop.category', 'dining') }}" class="group relative block overflow-hidden">
            <picture>
                <source media="(min-width: 48rem)" type="image/webp"
                    srcset="{{ asset('images/site/dining-1600.webp') }} 1600w, {{ asset('images/site/dining-2400.webp') }} 2400w" sizes="(min-width: 84rem) 80rem, 100vw">
                <source type="image/webp" srcset="{{ asset('images/site/dining-phone-800.webp') }} 800w" sizes="100vw">
                <img src="{{ asset('images/site/dining-1600.jpg') }}" alt="The Tana dining table laid for six in a sunlit dining room"
                    width="1600" height="800" loading="lazy" decoding="async"
                    class="aspect-[4/3] w-full object-cover transition duration-700 ease-out group-hover:scale-[1.02] md:aspect-[5/2]">
            </picture>

            <div class="bg-ink px-5 py-6 text-paper md:absolute md:bottom-0 md:left-0 md:max-w-md md:px-8 md:py-7">
                <p class="eyebrow !text-sand">Dining</p>
                <h2 class="mt-2 text-2xl lg:text-3xl">A table worth sitting at for hours.</h2>
                <p class="mt-3 font-semibold underline decoration-sand underline-offset-4">Shop dining tables and chairs</p>
            </div>
        </a>
    </section>

    {{-- Offers --}}
    @if ($offers->isNotEmpty())
        <section class="wrap mt-16 lg:mt-24">
            <div class="border-b border-rule pb-5">
                <h2 class="text-3xl lg:text-4xl">On offer this month</h2>
            </div>

            <ul class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-4 lg:gap-x-6">
                @foreach ($offers as $product)
                    <li><x-shop.product-card :product="$product" /></li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- What a customer can count on --}}
    <section class="wrap mt-20 lg:mt-28">
        <dl class="grid gap-x-8 gap-y-8 border-y border-rule py-10 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Delivered across Kenya', 'Our own crew in Nairobi, and trusted carriers to every county.'],
                ['Pay your way', 'M-Pesa or card at checkout. Every price includes VAT.'],
                ['Made to order', 'Larger pieces are built for you in three to six weeks.'],
                ['Guaranteed', 'Up to three years on solid wood furniture.'],
            ] as [$heading, $text])
                <div>
                    <dt class="font-display text-xl">{{ $heading }}</dt>
                    <dd class="mt-2 text-[0.95rem] text-ink-soft">{{ $text }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</x-shop.layout>
