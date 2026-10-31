{{-- Section 360° --}}
@props(['section', 'id'])

@php
  $tabs = $section['tabs'];
  $first = $tabs[0];
  $label = $section['title'] ?: 'Tampilan 360°';
  // Ada tab yang berputar
  $spins = collect($tabs)->contains(fn (array $tab): bool => count($tab['frames']) > 1);
@endphp

<section
  id="{{ $id }}"
  class="tph tph--section"
  @if ($section['title']) aria-labelledby="{{ $id }}-title" @else aria-label="{{ $label }}" @endif
  data-spin="drag"
  data-tabs="{{ json_encode(collect($tabs)->map(fn (array $tab): array => Arr::only($tab, ['name', 'text', 'frames', 'width', 'height', 'frame']))) }}"
>
  <div class="wrap tph__grid">
    <div class="tph__copy">
      @if ($section['title'])
        <h2 class="pj-title display" id="{{ $id }}-title">{{ $section['title'] }}</h2>
      @endif

      <ul class="tph__tabs" aria-label="Bagian 360°">
        @foreach ($tabs as $tab)
          <li class="tph__part">
            <button class="tph__tab" type="button" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $id }}-desc-{{ $loop->index }}" data-spin-tab="{{ $loop->index }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
              {{ $tab['name'] }}
            </button>
            <p class="tph__desc" id="{{ $id }}-desc-{{ $loop->index }}"><strong>{{ $tab['name'] }}.</strong> {{ $tab['text'] }}</p>
          </li>
        @endforeach
      </ul>
    </div>

    <div class="tph__stage" role="region" aria-label="{{ $label }}" style="aspect-ratio: {{ $first['width'] }} / {{ $first['height'] }}">
      {{-- Poster sebelum kanvas --}}
      <img class="tph__poster" src="{{ $first['frames'][$first['frame']] ?? $first['frames'][0] }}" alt="" width="{{ $first['width'] }}" height="{{ $first['height'] }}" data-spin-poster />
      <canvas class="tph__canvas" tabindex="0" role="img" aria-label="{{ $label }}, {{ $first['name'] }}: geser untuk memutar" data-spin-canvas></canvas>
      @if ($spins)
        <button class="icon-btn tph__nav tph__nav--prev" type="button" data-spin-step="-1" aria-label="Putar ke kiri">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
        </button>
        <button class="icon-btn tph__nav tph__nav--next" type="button" data-spin-step="1" aria-label="Putar ke kanan">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
        </button>
      @endif
    </div>
  </div>
</section>
