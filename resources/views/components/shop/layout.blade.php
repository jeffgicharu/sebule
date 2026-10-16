{{--
    The frame of every shop page.

    title        the page's own name; the store's name is added after it
    description  one or two sentences for search results and link previews
    image        ['url' => ..., 'width' => ..., 'height' => ..., 'alt' => ...] shown when the page is shared
    type         the Open Graph type: "website" or "product"
    canonical    the address search engines should file the page under, when it is not the current one
    schema       schema.org nodes describing the page, printed as one JSON-LD graph
    head         a slot for tags only this page needs
--}}
@props(['title' => null, 'description' => null, 'image' => null, 'type' => 'website', 'canonical' => null, 'schema' => [], 'head' => null])

@php
    $store = config('app.name');
    $pageTitle = $title ? $title.' | '.$store : $store.' | Furniture and homeware made in Kenya';
    $pageDescription = Str::limit(Str::squish(strip_tags($description ?: 'Furniture and homeware designed in Nairobi and built in solid mvule, mahogany and Meru oak. Delivered across Kenya, and paid for with M-Pesa.')), 158);
    $pageUrl = $canonical ?: url()->current();
    $share = $image ?: [
        'url' => asset('images/site/share-1200.jpg'),
        'width' => 1200,
        'height' => 630,
        'alt' => 'The Amani sofa in a bright Nairobi living room',
    ];

    // Encoded here and not in the markup, where Blade would read "@context" as one of its own directives.
    $structured = $schema ? json_encode(
        ['@context' => 'https://schema.org', '@graph' => array_values($schema)],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
    ) : null;
@endphp

<!DOCTYPE html>
<html lang="en-KE">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $pageDescription }}">
        <link rel="canonical" href="{{ $pageUrl }}">
        @if (config('store.allow_indexing'))
            <meta name="robots" content="index, follow, max-image-preview:large">
        @endif

        <meta property="og:site_name" content="{{ $store }}">
        <meta property="og:locale" content="en_KE">
        <meta property="og:type" content="{{ $type }}">
        <meta property="og:title" content="{{ $title ?: $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ $pageUrl }}">
        <meta property="og:image" content="{{ $share['url'] }}">
        @isset($share['width'])
            <meta property="og:image:width" content="{{ $share['width'] }}">
            <meta property="og:image:height" content="{{ $share['height'] }}">
        @endisset
        <meta property="og:image:alt" content="{{ $share['alt'] ?? $pageTitle }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title ?: $pageTitle }}">
        <meta name="twitter:description" content="{{ $pageDescription }}">
        <meta name="twitter:image" content="{{ $share['url'] }}">
        <meta name="twitter:image:alt" content="{{ $share['alt'] ?? $pageTitle }}">

        {{ $head }}

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <meta name="theme-color" content="#2f4a3a">
        <meta name="application-name" content="{{ $store }}">
        <meta name="apple-mobile-web-app-title" content="{{ $store }}">
        <meta name="format-detection" content="telephone=no">

        @if ($structured)
            <script type="application/ld+json">{!! $structured !!}</script>
        @endif

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Moving between pages, the photo of the piece being opened glides from where it was to where it lands,
             and back again. It has to be in the page itself: a new page names what it shows before its first frame. --}}
        <script>
            (() => {
                const pieceAt = (address) => {
                    const path = address ? new URL(address, location.href).pathname : '';

                    return path.startsWith('/p/') ? path : null;
                };
                const inView = (element) => {
                    const box = element?.getBoundingClientRect();

                    return Boolean(box && box.width > 0 && box.bottom > 0 && box.top < innerHeight);
                };
                const photoOf = (path) => path && [...document.querySelectorAll('[data-piece]')].find((element) => element.dataset.piece === path && inView(element));
                const mainPhoto = () => [document.querySelector('[data-piece-main]')].find(inView);

                // The banner and the header stay still while the page under them changes. Only the photo
                // itself travels, not the labels laid over it.
                const hold = (transition, piece) => {
                    const named = [[document.querySelector('[data-ribbon]'), 'ribbon'], [document.querySelector('[data-masthead]'), 'masthead'], [piece?.querySelector('img') ?? piece, 'piece']]
                        .filter(([element]) => element);

                    named.forEach(([element, name]) => element.style.viewTransitionName = name);
                    transition.finished.finally(() => named.forEach(([element]) => element.style.viewTransitionName = ''));
                };

                let chosen = null;
                addEventListener('click', (event) => chosen = event.target.closest?.('a[href]')?.href ?? null, true);

                // Leaving: the photo of the piece about to open, or the photo of the piece being left. A piece
                // just added to the cart has already flown there, so it does not travel a second time.
                addEventListener('pageswap', (event) => {
                    if (event.viewTransition) {
                        const sent = document.querySelector('form[data-to-cart][data-sent]');
                        hold(event.viewTransition, sent ? null : photoOf(pieceAt(event.activation?.entry?.url ?? chosen)) || mainPhoto());
                    }
                });

                // Arriving: the piece's own photo, or, back from a piece, its photo in the list.
                addEventListener('pagereveal', (event) => {
                    if (event.viewTransition) {
                        hold(event.viewTransition, mainPhoto() || photoOf(pieceAt(window.navigation?.activation?.from?.url ?? document.referrer)));
                    }
                });
            })();
        </script>
    </head>
    <body class="flex min-h-screen flex-col">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50 focus:bg-ink focus:px-4 focus:py-2 focus:text-paper">Skip to the content</a>

        <p class="bg-forest px-4 py-2 text-center text-[0.8125rem] font-medium tracking-wide text-paper" data-ribbon>
            Delivery across Kenya <span aria-hidden="true" class="mx-2 text-sand">/</span> Pay with M-Pesa
        </p>

        <header class="border-b border-rule bg-paper" data-masthead>
            <div class="wrap flex items-center justify-between gap-6 py-4 lg:py-5">
                <a href="{{ route('home') }}" class="font-display text-[1.75rem] leading-none lg:text-[2rem]">{{ config('app.name') }}</a>

                {{-- On a small laptop the rooms take the width, so the icons on the right go without their words until there is room. --}}
                <div class="flex items-center gap-3 lg:gap-4 xl:gap-7">
                {{-- Desktop: rooms across the bar, each opening its sub-categories. --}}
                <nav aria-label="Shop" class="hidden lg:block">
                    <ul class="flex items-center gap-x-5 whitespace-nowrap text-[0.95rem] font-medium xl:gap-x-7">
                        @foreach ($menuRooms as $room)
                            <li class="group relative">
                                <a href="{{ route('shop.category', $room['slug']) }}" class="block border-b-2 border-transparent py-2 transition-colors hover:border-ink group-focus-within:border-ink">{{ $room['name'] }}</a>

                                @if ($room['children'])
                                    {{-- The last two open leftwards from their right edge, so no list reaches past the page. --}}
                                    <ul class="invisible absolute top-full z-30 w-56 {{ $loop->remaining < 2 ? 'right-0' : 'left-1/2 -translate-x-1/2' }} border border-rule bg-paper p-2 opacity-0 shadow-[0_18px_40px_-18px_rgb(31_29_26/0.35)] transition duration-150 group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                                        @foreach ($room['children'] as $child)
                                            <li><a href="{{ route('shop.category', $child['slug']) }}" class="block px-3 py-2 font-normal hover:bg-tile">{{ $child['name'] }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <a href="{{ route('shop.search') }}" class="flex min-h-11 items-center gap-2 px-1 text-[0.95rem] font-medium" aria-label="Search">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[1.35rem]" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="10.8" cy="10.8" r="6.3" />
                        <path d="m15.6 15.6 4.4 4.4" />
                    </svg>
                    <span class="hidden xl:inline">Search</span>
                </a>

                {{-- The account: a customer's own name once they are signed in. --}}
                <a href="{{ $shopper ? route('account.show') : route('account.login') }}" class="flex min-h-11 items-center gap-2 px-1 text-[0.95rem] font-medium"
                    aria-label="{{ $shopper ? 'Your account, signed in as '.$shopper->name : 'Sign in to your account' }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[1.35rem]" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="3.6" />
                        <path d="M4.8 20c.9-3.6 3.7-5.6 7.2-5.6s6.3 2 7.2 5.6" />
                    </svg>
                    <span class="hidden max-w-[7rem] truncate sm:inline lg:hidden xl:inline">{{ $shopper ? Str::of($shopper->name)->before(' ') : 'Sign in' }}</span>
                </a>

                <a href="{{ route('cart.show') }}" class="flex min-h-11 items-center gap-2 px-1 text-[0.95rem] font-medium" data-cart
                    aria-label="Cart, {{ $cartPieces }} {{ Str::plural('piece', $cartPieces) }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[1.35rem]" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 8h14l-1 12H6L5 8Z" />
                        <path d="M9 8V6.5a3 3 0 0 1 6 0V8" />
                    </svg>
                    <span class="hidden sm:inline lg:hidden xl:inline">Cart</span>
                    @if ($cartPieces > 0)
                        {{-- Just after something is added, the count springs up to say so. --}}
                        <span @class(['grid h-5 min-w-5 place-items-center rounded-full bg-forest px-1.5 text-xs font-semibold leading-none text-paper', 'count-pop' => session()->has('shop.added')])>{{ $cartPieces }}</span>
                    @endif
                </a>

                {{-- Phone: one menu button, no script needed. --}}
                <details class="group lg:hidden">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 border border-ink px-4 text-sm font-semibold [&::-webkit-details-marker]:hidden">
                        <span class="group-open:hidden">Menu</span>
                        <span class="hidden group-open:inline">Close</span>
                    </summary>

                    <nav aria-label="Shop" class="menu-drop absolute inset-x-0 z-40 mt-4 max-h-[75vh] overflow-y-auto border-y border-rule bg-paper shadow-[0_30px_40px_-24px_rgb(31_29_26/0.4)]">
                        <ul class="wrap divide-y divide-rule py-2">
                            @foreach ($menuRooms as $room)
                                <li class="py-3">
                                    <a href="{{ route('shop.category', $room['slug']) }}" class="font-display text-xl">{{ $room['name'] }}</a>
                                    <ul class="mt-2 flex flex-wrap gap-x-5 gap-y-2 text-[0.95rem] text-ink-soft">
                                        @foreach ($room['children'] as $child)
                                            <li><a href="{{ route('shop.category', $child['slug']) }}" class="link py-1">{{ $child['name'] }}</a></li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                </details>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1">
            {{-- What the last thing the visitor did came to, said once. --}}
            @if (session('shop.notice'))
                <p role="status" class="border-b border-forest/25 bg-forest/10">
                    <span class="wrap block py-3 font-medium text-forest-deep">{{ session('shop.notice') }}</span>
                </p>
            @endif
            @if (session('shop.problem'))
                <p role="alert" class="border-b border-clay/30 bg-clay/10">
                    <span class="wrap block py-3 font-medium text-clay-deep">{{ session('shop.problem') }}</span>
                </p>
            @endif

            {{ $slot }}
        </main>

        <footer class="mt-24 bg-ink text-paper">
            <div class="wrap grid grid-cols-2 gap-x-8 gap-y-12 py-14 md:grid-cols-12 lg:py-20">
                <div class="col-span-2 md:col-span-12 lg:col-span-3">
                    <p class="font-display text-4xl">{{ config('app.name') }}</p>
                    <p class="mt-4 max-w-sm text-paper/70">Furniture and homeware designed in Nairobi and built to be used every day, in solid mvule, mahogany and Meru oak.</p>
                </div>

                <nav aria-label="Rooms" class="md:col-span-3 lg:col-span-2">
                    <h2 class="eyebrow !text-sand">Rooms</h2>
                    <ul class="mt-4 space-y-2 text-paper/85">
                        @foreach ($menuRooms as $room)
                            <li><a href="{{ route('shop.category', $room['slug']) }}" class="hover:text-paper hover:underline">{{ $room['name'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>

                <nav aria-label="Collections" class="md:col-span-3 lg:col-span-2">
                    <h2 class="eyebrow !text-sand">Collections</h2>
                    <ul class="mt-4 space-y-2 text-paper/85">
                        @foreach ($menuCollections as $collection)
                            <li><a href="{{ route('shop.collection', $collection['slug']) }}" class="hover:text-paper hover:underline">{{ $collection['name'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>

                @if ($footerPages['help'] ?? [])
                    <nav aria-label="Help" class="md:col-span-3 lg:col-span-2">
                        <h2 class="eyebrow !text-sand">Help</h2>
                        <ul class="mt-4 space-y-2 text-paper/85">
                            @foreach ($footerPages['help'] as $page)
                                <li><a href="{{ route('shop.page', $page['slug']) }}" class="hover:text-paper hover:underline">{{ $page['title'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                <div class="md:col-span-3">
                    <h2 class="eyebrow !text-sand">Showroom</h2>
                    <p class="mt-4 text-paper/85">{{ config('store.showroom.street') }}, {{ config('store.showroom.city') }}</p>
                    <p class="mt-2 text-paper/85">{!! str_replace(', ', '<br>', e(\App\Messages\OrderWording::hours())) !!}</p>
                    @if ($footerPages['about'] ?? [])
                        <ul class="mt-4 space-y-2 text-paper/85">
                            @foreach ($footerPages['about'] as $page)
                                <li><a href="{{ route('shop.page', $page['slug']) }}" class="hover:text-paper hover:underline">{{ $page['title'] }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="border-t border-paper/15">
                <div class="wrap flex flex-col gap-x-8 gap-y-3 py-6 text-sm text-paper/60 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <p>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved. Every price includes VAT.</p>
                    @if ($footerPages['legal'] ?? [])
                        <ul class="flex flex-wrap gap-x-6 gap-y-2">
                            @foreach ($footerPages['legal'] as $page)
                                <li><a href="{{ route('shop.page', $page['slug']) }}" class="hover:text-paper hover:underline">{{ $page['title'] }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </footer>
    </body>
</html>
