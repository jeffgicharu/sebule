<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>
        <meta name="description" content="Furniture and homeware made for Kenyan homes.">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-stone-50 px-4 text-stone-900 antialiased">
        <main class="max-w-md text-center">
            <h1 class="text-4xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
            <p class="mt-4 text-lg text-stone-600">Furniture and homeware made for Kenyan homes.</p>
            <p class="mt-2 text-stone-500">Our online shop opens soon.</p>
        </main>
    </body>
</html>
