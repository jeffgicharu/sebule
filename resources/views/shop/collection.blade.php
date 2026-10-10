<x-shop.layout :title="$collection->name.' collection'" :description="$collection->description">
    <div class="wrap pt-6 lg:pt-8">
        <x-shop.breadcrumbs :trail="[[$collection->name.' collection', null]]" />

        <header class="mt-6 grid gap-6 border-b border-rule pb-10 lg:mt-10 lg:grid-cols-12 lg:items-end">
            <div class="lg:col-span-7">
                <p class="eyebrow">Collection</p>
                <h1 class="mt-3 text-5xl lg:text-7xl">{{ $collection->name }}</h1>
            </div>
            <p class="max-w-md text-lg text-ink-soft lg:col-span-5">{{ $collection->description }}</p>
        </header>

        <p class="mt-5 text-sm text-ink-soft">{{ $products->count() }} {{ Str::plural('piece', $products->count()) }}</p>

        <ul class="mt-6 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-6 lg:gap-y-14">
            @foreach ($products as $product)
                <li><x-shop.product-card :product="$product" :eager="$loop->index < 4" /></li>
            @endforeach
        </ul>
    </div>
</x-shop.layout>
