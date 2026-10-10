<x-shop.plain title="Something went wrong">
    <p class="eyebrow">Something went wrong</p>
    <h1 class="mt-3 text-4xl lg:text-6xl">That one is on us.</h1>
    <p class="mt-5 text-lg text-ink-soft">The page could not be shown, and we have been told about it. Please try again in a moment.</p>
    <p class="mt-8 flex flex-wrap gap-3">
        <a href="{{ url()->current() }}" class="btn btn-primary">Try again</a>
        <a href="{{ url('/') }}" class="btn btn-quiet">Go to the home page</a>
    </p>
</x-shop.plain>
