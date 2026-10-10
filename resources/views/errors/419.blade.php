<x-shop.plain title="Please try again">
    <p class="eyebrow">Please try again</p>
    <h1 class="mt-3 text-4xl lg:text-6xl">This page was open for a while.</h1>
    <p class="mt-5 text-lg text-ink-soft">For your safety it stopped accepting what you sent. Go back, refresh the page and send it again.</p>
    <p class="mt-8">
        <a href="{{ url()->previous(url('/')) }}" class="btn btn-primary">Go back</a>
    </p>
</x-shop.plain>
