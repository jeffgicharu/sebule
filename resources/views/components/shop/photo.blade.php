@props(['media', 'alt' => '', 'sizes' => '100vw', 'eager' => false])

@php
    // Every product photo is square and has the same set of widths generated for it.
    $widths = collect(\App\Catalogue\ProductImage::WIDTHS)->except('thumb')
        ->filter(fn (int $width, string $name) => $media->hasGeneratedConversion($name));

    $src = $widths->has('medium') ? $media->getUrl('medium') : $media->getUrl();
    $srcset = $widths->map(fn (int $width, string $name) => $media->getUrl($name).' '.$width.'w')->implode(', ');
@endphp

<img
    src="{{ $src }}"
    @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
    alt="{{ $alt }}"
    width="960"
    height="960"
    loading="{{ $eager ? 'eager' : 'lazy' }}"
    @if ($eager) fetchpriority="high" @endif
    decoding="async"
    {{ $attributes }}
>
