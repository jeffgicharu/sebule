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
    $pageDescription = Str::limit(Str::squish(strip_tags($description ?: 'Furniture and homeware designed in Nairobi and built in solid mvule, mahogany and Meru oak. Delivered across Kenya, with M-Pesa or card at checkout.')), 158);
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
    </head>
    <body class="flex min-h-screen flex-col">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50 focus:bg-ink focus:px-4 focus:py-2 focus:text-paper">Skip to the content</a>

        <p class="bg-forest px-4 py-2 text-center text-[0.8125rem] font-medium tracking-wide text-paper">
            Delivery across Kenya <span aria-hidden="true" class="mx-2 text-sand">/</span> Pay with M-Pesa or card
        </p>

        <header class="border-b border-rule bg-paper">
            <div class="wrap flex items-center justify-between gap-6 py-4 lg:py-5">
                <a href="{{ route('home') }}" class="font-display text-[1.75rem] leading-none lg:text-[2rem]">{{ config('app.name') }}</a>

                {{-- Desktop: rooms across the bar, each opening its sub-categories. --}}
                <nav aria-label="Shop" class="hidden lg:block">
                    <ul class="flex items-center gap-x-7 text-[0.95rem] font-medium">
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

                {{-- Phone: one menu button, no script needed. --}}
                <details class="group lg:hidden">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 border border-ink px-4 text-sm font-semibold [&::-webkit-details-marker]:hidden">
                        <span class="group-open:hidden">Menu</span>
                        <span class="hidden group-open:inline">Close</span>
                    </summary>

                    <nav aria-label="Shop" class="absolute inset-x-0 z-40 mt-4 max-h-[75vh] overflow-y-auto border-y border-rule bg-paper shadow-[0_30px_40px_-24px_rgb(31_29_26/0.4)]">
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
        </header>

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <footer class="mt-24 bg-ink text-paper">
            <div class="wrap grid gap-12 py-14 md:grid-cols-12 lg:py-20">
                <div class="md:col-span-5">
                    <p class="font-display text-4xl">{{ config('app.name') }}</p>
                    <p class="mt-4 max-w-sm text-paper/70">Furniture and homeware designed in Nairobi and built to be used every day, in solid mvule, mahogany and Meru oak.</p>
                </div>

                <nav aria-label="Rooms" class="md:col-span-2">
                    <h2 class="eyebrow !text-sand">Rooms</h2>
                    <ul class="mt-4 space-y-2 text-paper/85">
                        @foreach ($menuRooms as $room)
                            <li><a href="{{ route('shop.category', $room['slug']) }}" class="hover:text-paper hover:underline">{{ $room['name'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>

                <nav aria-label="Collections" class="md:col-span-2">
                    <h2 class="eyebrow !text-sand">Collections</h2>
                    <ul class="mt-4 space-y-2 text-paper/85">
                        @foreach ($menuCollections as $collection)
                            <li><a href="{{ route('shop.collection', $collection['slug']) }}" class="hover:text-paper hover:underline">{{ $collection['name'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>

                <div class="md:col-span-3">
                    <h2 class="eyebrow !text-sand">Showroom</h2>
                    <p class="mt-4 text-paper/85">{{ config('store.showroom.street') }}, {{ config('store.showroom.city') }}<br>Monday to Saturday, 9 am to 6 pm</p>
                </div>
            </div>

            <div class="border-t border-paper/15">
                <p class="wrap flex flex-col gap-2 py-6 text-sm text-paper/60 sm:flex-row sm:justify-between">
                    <span>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</span>
                    <span>Every price includes VAT.</span>
                </p>
            </div>
        </footer>
    </body>
</html>
