@props(['trail' => []])

{{-- $trail is a list of [label, url]; the last item is the page itself and is not a link. --}}
<nav aria-label="Breadcrumb" class="text-sm text-ink-soft">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <li><a href="{{ route('home') }}" class="link">Home</a></li>
        @foreach ($trail as [$label, $url])
            <li aria-hidden="true">/</li>
            <li>
                @if ($loop->last)
                    <span aria-current="page" class="text-ink">{{ $label }}</span>
                @else
                    <a href="{{ $url }}" class="link">{{ $label }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
