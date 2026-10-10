<x-shop.layout title="Page not found">
    <div class="wrap py-16 lg:py-24">
        <div class="max-w-xl">
            <p class="eyebrow">Page not found</p>
            <h1 class="mt-3 text-4xl lg:text-6xl">We cannot find that page.</h1>
            <p class="mt-5 text-lg text-ink-soft">It may have moved, or the address may have been typed a little differently. Everything we make is a short walk from the home page.</p>
            <p class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('home') }}" class="btn btn-primary">Go to the home page</a>
                <a href="{{ route('home') }}#collections" class="btn btn-quiet">See the collections</a>
            </p>
        </div>
    </div>
</x-shop.layout>
