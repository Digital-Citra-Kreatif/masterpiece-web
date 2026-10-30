{{-- Halaman tipe --}}
@php
  $pj = $t['project'];
  $parts = $t['turntable'];

  $galleryItems = collect($t['gallery'])->map(fn ($img, $i) => ['src' => asset($img['src']), 'alt' => "{$t['name']}, gambar ".($i + 1), 'w' => $img['w'] ?? null, 'h' => $img['h'] ?? null]);
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
    <x-header variant="type" :logo="$pj['style']['logo']" :back="['href' => $pj['url'], 'label' => $pj['title']]" :mark="['href' => $pj['url'], 'label' => $pj['title'].', halaman proyek']" />
  @else
    <x-header variant="type" />
  @endif

  <main>
    {{-- Judul tipe, parallax ke section bawahnya --}}
    <div data-hero-parallax>
      <section class="tph tph--title" aria-labelledby="type-name" data-hero>
        <div class="wrap">
          <h1 class="display tph__name" id="type-name">{{ $t['name'] }}</h1>
        </div>
      </section>
    </div>

    @if ($parts !== [] || $t['image'])
    {{-- 360° tipe --}}
    {{-- 360° ikut gulir: tertahan di layar, tab berganti sesuai putaran --}}
    <section
      @class(['tps', 'tps--scroll' => $parts !== []])
      aria-label="Detail {{ $t['name'] }}"
      @if ($parts !== []) data-spin="scroll" data-tabs="{{ json_encode(collect($parts)->map(fn (array $part): array => Arr::only($part, ['name', 'text', 'frames', 'width', 'height', 'frame']))) }}" style="--spin-tabs: {{ count($parts) }}" @endif
    >
      <div @class(['tps__pin' => $parts !== []]) @if ($parts !== []) data-spin-pin @endif>
      <div @class(['wrap', 'tps__grid', 'tps__grid--solo' => $parts === []])>
        @if ($parts !== [])
          {{-- Viewer 360° --}}
          @php($first = $parts[0])
          <div class="tps__view" style="--native: {{ $first['width'] }}px">
            <div class="tps__stage" role="region" aria-label="{{ $t['name'] }}, tampilan 360°" style="aspect-ratio: {{ $first['width'] }} / {{ $first['height'] }}">
              {{-- Gambar awal sebelum kanvas siap --}}
              <img class="tps__poster" src="{{ $first['frames'][$first['frame']] ?? $first['frames'][0] }}" alt="" width="{{ $first['width'] }}" height="{{ $first['height'] }}" data-spin-poster />
              <canvas class="tps__canvas" tabindex="0" role="img" aria-label="{{ $t['name'] }}, {{ $first['name'] }}: gulir atau geser untuk memutar" data-spin-canvas></canvas>
              <button class="icon-btn tps__nav tps__nav--prev" type="button" data-spin-step="-1" aria-label="Putar ke kiri">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
              </button>
              <button class="icon-btn tps__nav tps__nav--next" type="button" data-spin-step="1" aria-label="Putar ke kanan">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
              </button>
            </div>
          </div>

          {{-- Label tab, tab seperti halaman proyek, lalu label ruko dan ukuran --}}
          <div class="tps__info">
            <h2 class="display tps__part" aria-live="polite" data-spin-name>{{ $first['name'] }}</h2>

            <ul class="tph__tabs" aria-label="Bagian 360°">
              @foreach ($parts as $part)
                <li class="tph__part">
                  <button class="tph__tab" type="button" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-controls="type-360-desc-{{ $loop->index }}" data-spin-tab="{{ $loop->index }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
                    {{ $part['name'] }}
                  </button>
                  <p class="tph__desc" id="type-360-desc-{{ $loop->index }}"><strong>{{ $part['name'] }}.</strong> {{ $part['text'] }}</p>
                </li>
              @endforeach
            </ul>

            @if ($t['label'] || $t['dimensions'])
              <div class="tps__meta">
                @if ($t['label'])
                  <p class="eyebrow tps__label">{{ $t['label'] }}</p>
                @endif
                @if ($t['dimensions'])
                  <p class="display tps__size">{{ $t['dimensions'] }}</p>
                @endif
              </div>
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
      </div>
      </div>
    </section>
    @endif

    {{-- Label dan ukuran di section 360° bila ada --}}
    @php($aboutHead = $parts === [])
    @if (($aboutHead && ($t['label'] || $t['dimensions'])) || $t['intro'] || $t['facts'] !== [] || $t['suitable']['items'] !== [] || $t['gallery'] !== [] || $vrUrl)
      {{-- Keterangan tipe --}}
      <section class="tps tps--about" aria-label="Keterangan {{ $t['name'] }}">
        <div class="wrap">
          <div class="tps__about">
            @if ($aboutHead && $t['label'])
              <p class="eyebrow tps__label">{{ $t['label'] }}</p>
            @endif
            @if ($aboutHead && $t['dimensions'])
              <p class="display tps__size">{{ $t['dimensions'] }}</p>
            @endif

            @if ($t['intro'])
              <div class="tps__intro">{!! $t['intro'] !!}</div>
            @endif

            {{-- LT LB lantai, di bawah deskripsi --}}
            @if ($t['facts'] !== [])
              <dl class="tps__facts">
                @foreach ($t['facts'] as [$k, $v])
                  <div>
                    <dt>{{ $k }}</dt>
                    <dd>{{ $v }}</dd>
                  </div>
                @endforeach
              </dl>
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

            @if ($t['gallery'] !== [] || $vrUrl)
              {{-- Galeri dan VR --}}
              <div class="tps__actions">
                @if ($t['gallery'] !== [])
                  <button class="btn" type="button" data-open="gallery" data-payload="{{ $galleryPayload }}">Galeri</button>
                @endif
                @if ($vrUrl)
                  <a class="btn btn--solid" href="{{ $vrUrl }}" target="_blank" rel="noopener" aria-label="Tur VR 360° (tab baru)">VR</a>
                @endif
              </div>
            @endif
          </div>
        </div>
      </section>
    @endif

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

    @if ($t['info'] !== [])
      {{-- Ringkasan unit --}}
      <section class="tpi" aria-label="Ringkasan unit">
        <div class="wrap">
          <dl class="tps__specs" aria-label="Spesifikasi">
            @foreach ($t['info'] as [$k, $v])
              <div>
                <dt>{{ $k }}</dt>
                <dd>{{ $v }}</dd>
              </div>
            @endforeach
          </dl>
        </div>
      </section>
    @endif

    @if ($t['buildingSpecs'] !== [])
      {{-- Spesifikasi bangunan --}}
      <section class="tpb" aria-labelledby="build-title">
        <div class="wrap tpb__grid">
          <h2 class="display tpb__title" id="build-title">{{ $t['specsTitle'] }}</h2>
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
      <x-home.type-grid variant="project" id="tipe-lain" :types="$pj['types']" :heading="['title' => $pj['othersTitle'], 'intro' => null]" />
    @endif
  </main>

  @if ($pj && $pj['whatsapp'])
    <x-project.whatsapp :whatsapp="$pj['whatsapp']" :logo="$pj['style']['logo']" />
  @endif

  <x-footer :logo="$pj['style']['logo'] ?? null" />
</x-layouts.base>
