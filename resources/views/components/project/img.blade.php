{{-- Gambar CMS --}}
@props(['image', 'sizes' => '100vw', 'alt' => null, 'eager' => false])

<img
  {{ $attributes }}
  src="{{ $image['src'] }}"
  @if ($image['srcset'] !== '') srcset="{{ $image['srcset'] }}" sizes="{{ $sizes }}" @endif
  @if ($image['width']) width="{{ $image['width'] }}" height="{{ $image['height'] }}" @endif
  alt="{{ $alt ?? $image['alt'] }}"
  @unless ($eager) loading="lazy" @endunless
  decoding="async"
/>
