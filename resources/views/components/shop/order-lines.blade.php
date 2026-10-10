{{-- The pieces being bought, with what each comes to. Takes cart lines or order lines. --}}
@props(['lines'])

@php use App\Support\Money; @endphp

<ul class="divide-y divide-rule">
    @foreach ($lines as $line)
        <li class="flex gap-4 py-4">
            <div class="tile size-16 shrink-0">
                @if ($line['photo'])
                    <x-shop.photo :media="$line['photo']" alt="" sizes="4rem" class="h-full w-full object-contain" />
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-medium leading-snug">{{ $line['name'] }}</p>
                <p class="mt-0.5 text-sm text-ink-soft">
                    @if ($line['label']){{ $line['label'] }} <span aria-hidden="true">&middot;</span> @endif{{ $line['quantity'] }} &times; {{ Money::format($line['price']) }}
                </p>
                @if ($line['note'])
                    <p class="mt-0.5 text-sm text-ink-soft">{{ $line['note'] }}</p>
                @endif
            </div>
            <p class="shrink-0 whitespace-nowrap font-medium">{{ Money::format($line['total']) }}</p>
        </li>
    @endforeach
</ul>
