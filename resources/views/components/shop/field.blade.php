{{-- One labelled text box of a form, with its hint and its error beside it. --}}
@props(['name', 'label', 'value' => null, 'type' => 'text', 'hint' => null, 'required' => false, 'multiline' => false])

@php
    $describedBy = trim(($hint ? $name.'-hint ' : '').($errors->has($name) ? $name.'-error' : ''));
    $box = 'mt-2 w-full border bg-paper px-3.5 text-base '.($errors->has($name) ? 'border-clay-deep ring-1 ring-clay-deep' : 'border-ink/45');
@endphp

<div>
    <label for="{{ $name }}" class="block text-[0.95rem] font-semibold">
        {{ $label }}
        @unless ($required)
            <span class="font-normal text-ink-soft">(optional)</span>
        @endunless
    </label>

    @if ($hint)
        <p id="{{ $name }}-hint" class="mt-1 text-sm text-ink-soft">{{ $hint }}</p>
    @endif

    @if ($multiline)
        <textarea id="{{ $name }}" name="{{ $name }}" rows="3" class="{{ $box }} py-3"
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @error($name) aria-invalid="true" @enderror
            {{ $attributes }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" class="{{ $box }} min-h-12"
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @error($name) aria-invalid="true" @enderror
            {{ $attributes }}>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-sm font-medium text-clay-deep">{{ $message }}</p>
    @enderror
</div>
