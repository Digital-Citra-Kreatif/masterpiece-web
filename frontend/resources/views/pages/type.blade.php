{{--
  Type page. Left column: one 360° image sequence that stays on screen while the
  page scrolls through its parts (facade, then each floor). Each part is a
  keyframe: reaching it, by scrolling or from the part list in the panel,
  animates the sequence there and shows the part's text and denah. Dragging
  still rotates freely. Right column stays fixed: property info, denah, Gallery
  and VR. On narrow screens the info sits above the viewer and a bottom bar
  keeps key facts, Gallery and VR in reach.

  Part switching in resources/js/type-parts.js.
--}}
@php
  $parts = $t['parts'];
  $denahIndex = fn ($label) => collect($t['denah'])->search(fn ($d) => $d['label'] === $label);

  $galleryPayload = json_encode([
      'title' => "{$t['name']}: Galeri",
      'items' => collect($t['gallery'])->map(fn ($src, $i) => ['src' => asset($src), 'alt' => "{$t['name']}, gambar ".($i + 1)]),
  ]);
  $denahItems = collect($t['denah'])->map(fn ($d) => ['src' => asset($d['image']), 'caption' => $d['label'], 'alt' => "Denah {$d['label']}"]);
  $denahPayload = fn ($index) => json_encode(['title' => "{$t['name']}: Denah", 'items' => $denahItems, 'index' => $index]);
  $vrPayload = json_encode(['src' => asset($t['vr']['url']), 'title' => "{$t['name']}: Tur VR"]);
  $facts = [
      ['LT', "{$t['lt']} m²"],
      ['LB', "{$t['lb']} m²"],
      ['KT', $t['bedrooms']],
      ['KM', $t['bathrooms']],
  ];
@endphp

<x-layouts.base
  :title="$t['name']"
  :description="$t['name'].': fasad dan setiap lantai dalam 360°, denah, galeri, dan tur VR.'"
  body-class="is-type"
  entry="resources/js/type.js"
>
  <x-header variant="type" />

  <main class="tp wrap">
    <div
      class="tp__track"
      data-parts="{{ json_encode(collect($parts)->map(fn ($p) => Arr::only($p, ['name', 'kind', 'text', 'frame']))) }}"
      style="--parts: {{ count($parts) }}"
    >
      @foreach ($parts as $p)
        <span class="tp__step" id="{{ $p['id'] }}" data-part-step="{{ $loop->index }}" style="--i: {{ $loop->index }}" aria-hidden="true"></span>
      @endforeach

      <section class="viewer" aria-labelledby="part-title" style="--ratio: {{ $t['sequence']['width'] / $t['sequence']['height'] }}">
        <div class="viewer__head">
          <div>
            <h2 class="display viewer__title" id="part-title" data-part-name>{{ $parts[0]['name'] }}</h2>
          </div>
          <nav class="viewer__dots" aria-label="Bagian">
            @foreach ($parts as $p)
              <a href="#{{ $p['id'] }}" @class(['is-active' => $loop->first]) data-part-dot aria-label="{{ $p['name'] }}"></a>
            @endforeach
          </nav>
        </div>

        <div class="viewer__stage">
          <x-turntable :id="'tt-'.$t['slug']" :label="$t['name'].', fasad dan lantai'" :turntable="$t['sequence']" tone="black" />
        </div>

        <div class="viewer__info">
          <p class="viewer__text" data-part-text aria-live="polite">{{ $parts[0]['text'] }}</p>
          @foreach ($parts as $p)
            @php($d = isset($p['denah']) ? $denahIndex($p['denah']) : false)
            @if ($d !== false)
              <button class="viewer__denah" type="button" data-part-denah="{{ $loop->index }}" data-open="gallery" data-payload="{{ $denahPayload($d) }}" @if (! $loop->first) hidden @endif>
                <img src="{{ asset($t['denah'][$d]['image']) }}" alt="" loading="lazy" />
                <span>Denah {{ $t['denah'][$d]['label'] }}</span>
              </button>
            @endif
          @endforeach
        </div>
      </section>
    </div>

    <aside class="tp__panel" aria-label="Informasi {{ $t['name'] }}">
      <div class="panel">
        <h1 class="display panel__name">{{ $t['name'] }}</h1>
        @if ($t['label'])
          <p class="panel__label eyebrow">{{ $t['label'] }}</p>
        @endif

        <dl class="panel__facts">
          @foreach ($facts as [$k, $v])
            <div>
              <dt>{{ $k }}</dt>
              <dd>{{ $v }}</dd>
            </div>
          @endforeach
        </dl>

        <p class="panel__intro">{{ $t['intro'] }}</p>

        <dl class="panel__info">
          @foreach ($t['info'] as [$k, $v])
            <div>
              <dt>{{ $k }}</dt>
              <dd>{{ $v }}</dd>
            </div>
          @endforeach
        </dl>

        <nav class="panel__floors" aria-label="Bagian">
          @foreach ($parts as $p)
            <a href="#{{ $p['id'] }}" data-part-link="{{ $loop->index }}" @class(['is-active' => $loop->first])>
              <span>{{ sprintf('%02d', $loop->iteration) }}</span>
              {{ $p['name'] }}
            </a>
          @endforeach
        </nav>

        <div class="panel__denah">
          <ul aria-label="Denah">
            @foreach ($t['denah'] as $d)
              <li>
                <button type="button" data-open="gallery" data-payload="{{ $denahPayload($loop->index) }}" aria-label="Buka denah {{ $d['label'] }}">
                  <img src="{{ asset($d['image']) }}" alt="" loading="lazy" />
                  <span>{{ $d['label'] }}</span>
                </button>
              </li>
            @endforeach
          </ul>
        </div>

        <div class="panel__actions">
          <button class="btn" type="button" data-open="gallery" data-payload="{{ $galleryPayload }}">Gallery</button>
          <button class="btn btn--solid" type="button" data-open="iframe" data-payload="{{ $vrPayload }}">VR</button>
        </div>
      </div>
    </aside>
  </main>

  <!-- Narrow screens: facts, Gallery and VR stay reachable while scrolling. -->
  <div class="dock" aria-label="{{ $t['name'] }} ringkas">
    <div class="dock__info">
      <strong class="display">{{ $t['name'] }}</strong>
      <span>{{ collect($facts)->map(fn ($f) => "{$f[0]} {$f[1]}")->join(' · ') }}</span>
    </div>
    <button class="btn" type="button" data-open="gallery" data-payload="{{ $galleryPayload }}">Gallery</button>
    <button class="btn btn--solid" type="button" data-open="iframe" data-payload="{{ $vrPayload }}">VR</button>
  </div>

  <x-footer />
</x-layouts.base>
