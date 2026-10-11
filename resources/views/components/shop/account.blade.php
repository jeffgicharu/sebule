{{-- The frame of a customer's account pages: who is signed in, and the way between the pages. --}}
@props(['title', 'customer', 'intro' => null])

@php
    $pages = [
        'account.show' => 'Orders',
        'account.addresses.index' => 'Addresses',
        'account.details' => 'Your details',
    ];
    $here = fn (string $route): bool => request()->routeIs($route) || ($route === 'account.addresses.index' && request()->routeIs('account.addresses.*'));
@endphp

<x-shop.layout :title="$title" description="Your Sebule account.">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <div class="wrap pt-8 lg:pt-12">
        <p class="eyebrow">Your account</p>
        <h1 class="mt-3 text-4xl lg:text-5xl">{{ $title }}</h1>
        @if ($intro)
            <p class="mt-3 max-w-xl text-ink-soft">{{ $intro }}</p>
        @endif

        <div class="mt-8 grid gap-10 lg:grid-cols-12 lg:gap-12">
            <nav aria-label="Your account" class="lg:col-span-3">
                <ul class="flex flex-wrap gap-x-6 gap-y-1 border-b border-rule pb-3 text-[0.95rem] font-medium lg:block lg:space-y-1 lg:border-b-0 lg:border-l lg:pb-0">
                    @foreach ($pages as $route => $label)
                        <li>
                            <a href="{{ route($route) }}" @if ($here($route)) aria-current="page" @endif
                                class="block py-2 lg:-ml-px lg:border-l-2 lg:px-4 {{ $here($route) ? 'font-semibold text-ink underline decoration-forest decoration-2 underline-offset-8 lg:border-forest lg:no-underline' : 'text-ink-soft hover:text-ink lg:border-transparent' }}">{{ $label }}</a>
                        </li>
                    @endforeach
                    <li class="lg:pt-3">
                        <form method="post" action="{{ route('account.logout') }}">
                            @csrf
                            <button class="py-2 text-ink-soft underline decoration-sand underline-offset-4 hover:text-ink lg:px-4">Sign out</button>
                        </form>
                    </li>
                </ul>
                <p class="mt-3 hidden break-words text-sm text-ink-soft lg:block lg:px-4">Signed in as {{ $customer->email }}</p>
            </nav>

            <div class="min-w-0 lg:col-span-9">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-shop.layout>
