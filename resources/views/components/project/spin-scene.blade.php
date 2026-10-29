{{-- Section 360° lewat scroll --}}
@props(['section', 'id'])

@php
  $tabs = $section['tabs'];
  $first = $tabs[0];
  $title = $section['title'] ?: 'Tampilan 360°';
@endphp

<section
  class="scene scene--project scene--left is-scrub spin"
  style="--p-area: 1; --v-area: visible"
  aria-labelledby="{{ $id }}-title"
  data-spin="scroll"
  data-tabs="{{ json_encode(collect($tabs)->map(fn (array $tab): array => Arr::only($tab, ['name', 'text', 'frames', 'width', 'height', 'frame']))) }}"
>
  <span id="{{ $id }}" class="scene__anchor" aria-hidden="true"></span>

  <div class="scene__stage">
    {{-- Gambar awal sebelum kanvas siap --}}
    <img class="scene__media spin__poster" src="{{ $first['frames'][$first['frame']] ?? $first['frames'][0] }}" alt="" width="{{ $first['width'] }}" height="{{ $first['height'] }}" data-spin-poster />
    <canvas class="scene__media spin__canvas" role="img" aria-label="{{ $title }}, {{ $first['name'] }}: gulir untuk memutar" data-spin-canvas></canvas>
    <div class="scene__shade" aria-hidden="true"></div>

    <div class="scene__area wrap" data-phase="area">
      <div class="scene__box">
        <h2 class="display scene__area-title" id="{{ $id }}-title">{{ $title }}</h2>
        @if ($section['intro'])
          <p class="scene__intro">{{ $section['intro'] }}</p>
        @endif
        @if (count($tabs) > 1)
          <ul class="scene__list spin__tabs" aria-label="Bagian 360°">
            @foreach ($tabs as $tab)
              <li>
                <button type="button" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" data-spin-tab="{{ $loop->index }}">
                  <span>{{ sprintf('%02d', $loop->iteration) }}</span>
                  {{ $tab['name'] }}
                </button>
              </li>
            @endforeach
          </ul>
        @endif
        <p class="spin__text" aria-live="polite" data-spin-text @if (blank($first['text'])) hidden @endif>{{ $first['text'] }}</p>
      </div>
    </div>
  </div>
</section>
