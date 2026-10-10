{{-- A page that stands on its own: no menu and nothing read from the database or the cache,
     so it can still be shown when one of those is the thing that failed. --}}
@props(['title'])

<!DOCTYPE html>
<html lang="en-KE">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title }} | {{ config('app.name') }}</title>
        <meta name="robots" content="noindex">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <meta name="theme-color" content="#2f4a3a">

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen flex-col">
        <header class="border-b border-rule bg-paper">
            <div class="wrap py-4 lg:py-5">
                <a href="{{ url('/') }}" class="font-display text-[1.75rem] leading-none lg:text-[2rem]">{{ config('app.name') }}</a>
            </div>
        </header>

        <main class="wrap flex flex-1 flex-col justify-center py-16 lg:py-24">
            <div class="max-w-xl">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
