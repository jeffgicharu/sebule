@php
    use App\Seo\StructuredData;
    use App\Support\Money;

    $address = route('shop.page', $page);
    $trail = [[$page->title, null]];
    $withContact = in_array($page->slug, ['contact', 'showroom'], true);
    $schema = array_values(array_filter([
        StructuredData::breadcrumbs($trail, $address),
        ($questions = $page->questions()) ? StructuredData::questions($questions) : null,
    ]));
@endphp

<x-shop.layout :title="$page->title" :description="$page->summary" :canonical="$address" :schema="$schema">
    @unless ($page->is_published)
        <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

        <p role="status" class="border-b border-clay/30 bg-clay/10">
            <span class="wrap block py-3 font-medium text-clay-deep">This page is not published. Only staff who are signed in can see it.</span>
        </p>
    @endunless

    <div class="wrap pt-6 lg:pt-8">
        <x-shop.breadcrumbs :trail="$trail" />

        <div class="mt-6 grid gap-12 lg:mt-10 lg:grid-cols-12">
            <article class="min-w-0 lg:col-span-7">
                <h1 class="text-4xl lg:text-6xl">{{ $page->title }}</h1>

                <div class="prose-page mt-8">{!! $page->html() !!}</div>

                @if ($zones->isNotEmpty())
                    <h2 class="mt-12 text-2xl lg:text-3xl">What delivery costs</h2>
                    <dl class="mt-5 divide-y divide-rule border-y border-rule">
                        @foreach ($zones as $zone)
                            <div class="py-4">
                                <dt class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                                    <span class="font-semibold">{{ $zone->name }}</span>
                                    <span class="whitespace-nowrap"><span class="font-semibold">{{ Money::format($zone->fee) }}</span> <span class="text-ink-soft">in {{ $zone->estimate() }}</span></span>
                                </dt>
                                <dd class="mt-1 text-[0.95rem] text-ink-soft">{{ $zone->counties->pluck('name')->join(', ') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </article>

            <aside class="lg:col-span-4 lg:col-start-9">
                @if ($withContact)
                    <x-shop.contact-card />
                @endif

                @if ($others->isNotEmpty())
                    <nav aria-label="Related pages" class="{{ $withContact ? 'mt-8' : '' }} border-t border-rule pt-6">
                        <h2 class="eyebrow">{{ $page->footer_group?->value === 'legal' ? 'Also' : 'More help' }}</h2>
                        <ul class="mt-3 space-y-2">
                            @foreach ($others as $other)
                                <li><a href="{{ route('shop.page', $other) }}" class="link">{{ $other->title }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </aside>
        </div>
    </div>
</x-shop.layout>
