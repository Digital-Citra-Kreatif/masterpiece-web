{{-- Section 360° --}}
@props(['section', 'id'])

@php
  $tabs = $section['tabs'];
  $first = $tabs[0];
  // Tab pertama yang berputar
  $spin = collect($tabs)->first(fn (array $tab): bool => count($tab['frames']) > 1);
  $label = $section['title'] ?: 'Tampilan 360°';
@endphp

<section
  id="{{ $id }}"
  class="tph tph--section"
  @if ($section['title']) aria-labelledby="{{ $id }}-title" @else aria-label="{{ $label }}" @endif
  data-parts="{{ json_encode(collect($tabs)->map(fn (array $tab): array => Arr::only($tab, ['name', 'frames', 'width', 'height', 'frame', 'config', 'root']))) }}"
>
  <div class="wrap tph__grid">
    <div class="tph__copy">
      @if ($section['title'])
        <h2 class="pj-title display" id="{{ $id }}-title">{{ $section['title'] }}</h2>
      @endif

      <ul class="tph__tabs" aria-label="Bagian">
        @foreach ($tabs as $tab)
          <li @class(['tph__part', 'is-open' => $loop->first]) data-part>
            <div class="tph__head">
              <button class="tph__tab" type="button" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $id }}-desc-{{ $loop->index }}" data-part-tab>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
                {{ $tab['name'] }}
              </button>
            </div>
            <div class="tph__body">
              <p class="tph__desc" id="{{ $id }}-desc-{{ $loop->index }}"><strong>{{ $tab['name'] }}.</strong> {{ $tab['text'] }}</p>
            </div>
          </li>
        @endforeach
      </ul>
    </div>

    <div class="tph__stage" aria-label="{{ $label }}" role="region">
      @if ($spin)
        @php($shown = count($first['frames']) > 1 ? $first : $spin)
        <div class="tph__spin" data-part-spin @if ($shown !== $first) hidden @endif>
          <x-turntable
            :id="$id.'-tt'"
            :label="$label"
            :turntable="['config' => $shown['config'], 'root' => $shown['root'], 'width' => $shown['width'], 'height' => $shown['height'], 'frame' => $shown['frame'], 'poster' => $shown['frames'][$shown['frame']]]"
            tone="black"
          />
        </div>
      @endif
      <img
        class="tph__still"
        src="{{ $first['frames'][0] }}"
        alt="{{ $first['name'] }}"
        width="{{ $first['width'] }}"
        height="{{ $first['height'] }}"
        loading="lazy"
        data-part-still
        @if (count($first['frames']) > 1) hidden @endif
      />
      @if (count($tabs) > 1)
        <button class="icon-btn tph__nav tph__nav--prev" type="button" data-part-prev aria-label="Bagian sebelumnya">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
        </button>
        <button class="icon-btn tph__nav tph__nav--next" type="button" data-part-next aria-label="Bagian berikutnya">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
        </button>
      @endif
    </div>
  </div>
</section>
