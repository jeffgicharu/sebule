{{-- Where the customer is on the way from cart to paid. --}}
@props(['current'])

<ol class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm" aria-label="Checkout steps">
    @foreach (['Your details', 'Review', 'Pay'] as $index => $step)
        @php $number = $index + 1; @endphp
        <li class="flex items-center gap-2 {{ $number === $current ? 'font-semibold text-ink' : 'text-ink-soft' }}" @if ($number === $current) aria-current="step" @endif>
            <span class="grid size-6 place-items-center rounded-full text-xs font-semibold {{ $number <= $current ? 'bg-forest text-paper' : 'border border-ink/40' }}">{{ $number }}</span>
            {{ $step }}
        </li>
        @unless ($loop->last)
            <li aria-hidden="true" class="h-px w-6 bg-ink/30 sm:w-10"></li>
        @endunless
    @endforeach
</ol>
