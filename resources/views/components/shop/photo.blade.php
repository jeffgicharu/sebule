@props(['media', 'alt' => '', 'sizes' => '100vw', 'eager' => false])

@php
    // Every product photo is square and has the same set of widths generated for it.
    $src = $media->hasGeneratedConversion('medium') ? $media->getUrl('medium') : $media->getUrl();
    $srcset = \App\Catalogue\ProductImage::srcset($media);
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
