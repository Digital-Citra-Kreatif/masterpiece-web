{{-- Halaman tipe --}}
@php
  $pj = $t['project'];
  $parts = $pj['turntable']['tabs'] ?? [];
  // Tab pertama yang berputar
  $spin = collect($parts)->first(fn (array $part): bool => count($part['frames']) > 1);

  $galleryItems = collect($t['gallery'])->map(fn ($img, $i) => ['src' => asset($img['src']), 'alt' => "{$t['name']}, gambar ".($i + 1)]);
  $galleryPayload = json_encode(['title' => "{$t['name']}: Galeri", 'items' => $galleryItems, 'index' => 0]);
  $denahItems = collect($t['denah'])->map(fn ($d) => ['src' => asset($d['image']), 'caption' => $d['label'], 'alt' => "Denah {$d['label']}"]);
  $denahPayload = fn ($index) => json_encode(['title' => "{$t['name']}: Denah", 'items' => $denahItems, 'index' => $index]);
  $vrUrl = filled($t['vr']['url']) ? asset($t['vr']['url']) : null;
@endphp

<x-layouts.base
  :title="$t['name']"
  :description="$t['name'].': tampilan 360°, spesifikasi, denah, galeri, dan tur VR.'"
  :favicon="$pj['style']['favicon'] ?? null"
  :body-class="$pj ? 'is-type is-project' : 'is-type'"
  entry="resources/js/type.js"
>
  @if ($pj && $pj['style']['css'] !== '')
    <x-slot:head>
      <style>{!! $pj['style']['css'] !!}</style>
    </x-slot:head>
  @endif

  @if ($pj)
    <x-header variant="type" :logo="$pj['style']['logo']" :back="['href' => $pj['url'], 'label' => $pj['title']]" />
  @else
    <x-header variant="type" />
  @endif

  <main>
    {{-- Judul tipe --}}
    <section class="tph tph--title" aria-labelledby="type-name">
      <div class="wrap">
        <h1 class="display tph__name" id="type-name">{{ $t['name'] }}</h1>
      </div>
    </section>

    {{-- Lembar tipe --}}
    <section
      class="tps"
      aria-label="Detail {{ $t['name'] }}"
      @if ($parts !== []) data-parts="{{ json_encode(collect($parts)->map(fn (array $part): array => Arr::only($part, ['name', 'text', 'frames', 'width', 'height', 'frame', 'config', 'root']))) }}" @endif
    >
      <div @class(['wrap', 'tps__grid', 'tps__grid--solo' => $parts === [] && ! $t['image']])>
        @if ($parts !== [])
          {{-- Viewer 360° --}}
          @php($first = $parts[0])
          <div class="tps__view" style="--native: {{ ($spin ?? $first)['width'] }}px">
            <h2 class="display tps__part" aria-live="polite" data-part-name>{{ $first['name'] }}</h2>

            <div class="tps__stage" role="region" aria-label="{{ $t['name'] }}, tampilan 360°">
              @if ($spin)
                @php($shown = count($first['frames']) > 1 ? $first : $spin)
                <div class="tps__spin" data-part-spin @if ($shown !== $first) hidden @endif>
                  <x-turntable
                    :id="'tt-'.$t['slug']"
                    :label="$t['name']"
                    :turntable="['config' => $shown['config'], 'root' => $shown['root'], 'width' => $shown['width'], 'height' => $shown['height'], 'frame' => $shown['frame'], 'poster' => $shown['frames'][$shown['frame']]]"
                    tone="black"
                  />
                </div>
              @endif
              <img
                class="tps__still"
                src="{{ $first['frames'][0] }}"
                alt="{{ $first['name'] }}"
                width="{{ $first['width'] }}"
                height="{{ $first['height'] }}"
                loading="lazy"
                data-part-still
                @if (count($first['frames']) > 1) hidden @endif
              />
            </div>

            @if (collect($parts)->contains(fn (array $part): bool => filled($part['text'])))
              <p class="tps__desc" data-part-text @if (blank($first['text'])) hidden @endif>{{ $first['text'] }}</p>
            @endif
          </div>
        @elseif ($t['image'])
          {{-- Gambar tipe --}}
          <div class="tps__view">
            <div class="tps__stage tps__stage--image">
              <img src="{{ asset($t['image']) }}" alt="{{ $t['name'] }}" loading="lazy" />
            </div>
          </div>
        @endif

        <div class="tps__info">
          @if ($t['label'])
            <p class="eyebrow tps__label">{{ $t['label'] }}</p>
          @endif
          @if ($t['dimensions'])
            <p class="display tps__size">{{ $t['dimensions'] }}</p>
          @endif

          <dl class="tps__facts">
            @foreach ($t['facts'] as [$k, $v])
              <div>
                <dt>{{ $k }}</dt>
                <dd>{{ $v }}</dd>
              </div>
            @endforeach
          </dl>

          @if ($t['intro'])
            <div class="tps__intro">{!! $t['intro'] !!}</div>
          @endif

          @if ($t['suitable']['items'] !== [])
            {{-- Cocok untuk usaha --}}
            <div class="tps__uses">
              <p class="tps__uses-title" id="uses-title">{{ $t['suitable']['title'] }}</p>
              <ul aria-labelledby="uses-title">
                @foreach ($t['suitable']['items'] as $use)
                  <li>{{ $use }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <dl class="tps__specs" aria-label="Spesifikasi">
            @foreach ($t['info'] as [$k, $v])
              <div>
                <dt>{{ $k }}</dt>
                <dd>{{ $v }}</dd>
              </div>
            @endforeach
          </dl>

          @if (count($parts) > 1)
            {{-- Tab bagian --}}
            <ul class="tps__tabs" aria-label="Bagian 360°">
              @foreach ($parts as $part)
                <li data-part>
                  <button class="tps__tab" type="button" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" data-part-tab>
                    {{ $part['name'] }}
                  </button>
                </li>
              @endforeach
            </ul>
          @endif

          @if ($t['gallery'] !== [] || $vrUrl)
            {{-- Galeri dan VR --}}
            <div class="tps__actions">
              @if ($t['gallery'] !== [])
                <button class="btn" type="button" data-open="gallery" data-payload="{{ $galleryPayload }}">Gallery</button>
              @endif
              @if ($vrUrl)
                <a class="btn btn--solid" href="{{ $vrUrl }}" target="_blank" rel="noopener" aria-label="Tur VR 360° (tab baru)">VR</a>
              @endif
            </div>
          @endif
        </div>
      </div>
    </section>

    @if ($t['denah'] !== [])
      {{-- Denah, slide bila lebih dari satu --}}
      @php($slidePlans = count($t['denah']) > 1)
      <section class="tpd" aria-label="Denah" data-view-cursor>
        <div @class(['tpd__frame', 'swiper' => $slidePlans]) @if ($slidePlans) data-plans-swiper @endif>
          <ul @class(['tpd__plans', 'swiper-wrapper' => $slidePlans])>
            @foreach ($t['denah'] as $d)
              <li @class(['swiper-slide' => $slidePlans])>
                <button class="tpd__plan" type="button" data-open="gallery" data-payload="{{ $denahPayload($loop->index) }}" aria-label="Perbesar denah {{ $d['label'] }}" data-view-target>
                  <img src="{{ asset($d['image']) }}" alt="Denah {{ $d['label'] }}" loading="lazy" />
                </button>
              </li>
            @endforeach
          </ul>
          @if ($slidePlans)
            <button class="icon-btn tpd__nav tpd__nav--prev" type="button" data-plans-prev aria-label="Denah sebelumnya">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
            </button>
            <button class="icon-btn tpd__nav tpd__nav--next" type="button" data-plans-next aria-label="Denah berikutnya">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
            </button>
          @endif
        </div>
        <x-view-cursor />
      </section>
    @endif

    @if ($t['buildingSpecs'] !== [])
      {{-- Spesifikasi bangunan --}}
      <section class="tpb" aria-labelledby="build-title">
        <div class="wrap tpb__grid">
          <h2 class="display tpb__title" id="build-title">Spesifikasi bangunan</h2>
          <div class="tpb__body">
            <dl class="tpb__list">
              @foreach ($t['buildingSpecs'] as [$k, $v])
                <div>
                  <dt>{{ $k }}</dt>
                  <dd>{{ $v }}</dd>
                </div>
              @endforeach
            </dl>
            @if ($t['specsNote'])
              <p class="tpb__note">{{ $t['specsNote'] }}</p>
            @endif
          </div>
        </div>
      </section>
    @endif

    @if ($pj && $pj['types'] !== [])
      <x-home.type-grid variant="project" id="tipe-lain" :types="$pj['types']" :heading="['title' => 'Tipe lain', 'intro' => null]" />
    @endif
  </main>

  @if ($pj && $pj['whatsapp'])
    <x-project.whatsapp :whatsapp="$pj['whatsapp']" :logo="$pj['style']['logo']" />
  @endif

  <x-footer :logo="$pj['style']['logo'] ?? null" />
</x-layouts.base>
