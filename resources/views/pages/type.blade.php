{{-- Halaman tipe --}}
@php
  $pj = $t['project'];
  $parts = $t['parts'];

  $galleryItems = collect($t['gallery'])->map(fn ($img, $i) => ['src' => asset($img['src']), 'thumb' => asset($img['thumb']), 'alt' => "{$t['name']}, gambar ".($i + 1)]);
  $galleryPayload = fn ($index = 0) => json_encode(['title' => "{$t['name']}: Galeri", 'items' => $galleryItems->map(fn ($img) => Arr::only($img, ['src', 'alt'])), 'index' => $index]);
  $denahItems = collect($t['denah'])->map(fn ($d) => ['src' => asset($d['image']), 'caption' => $d['label'], 'alt' => "Denah {$d['label']}"]);
  $denahPayload = fn ($index) => json_encode(['title' => "{$t['name']}: Denah", 'items' => $denahItems, 'index' => $index]);
  $vrUrl = filled($t['vr']['url']) ? asset($t['vr']['url']) : null;
@endphp

<x-layouts.base
  :title="$t['name']"
  :description="$t['name'].': fasad dan setiap lantai dalam 360°, denah, galeri, dan tur VR.'"
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
    <x-header variant="type" :logo="$pj['style']['logo']" :back="['href' => $pj['url'], 'label' => 'Lihat Proyek '.$pj['title']]" />
  @else
    <x-header variant="type" />
  @endif

  <main>
    {{-- Tab bagian --}}
    <section class="tph" aria-labelledby="type-name" data-parts="{{ json_encode(collect($parts)->map(fn ($p) => Arr::only($p, ['name', 'text', 'frame']))) }}">
      <div class="wrap tph__grid">
        <div class="tph__copy">
          <h1 class="display tph__name" id="type-name">{{ $t['name'] }}</h1>

          {{-- Tab bagian --}}
          <ul class="tph__tabs" aria-label="Bagian">
            @foreach ($parts as $p)
              <li @class(['tph__part', 'is-open' => $loop->first]) data-part>
                <div class="tph__head">
                  <button class="tph__tab" type="button" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="part-desc-{{ $loop->index }}" data-part-tab>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" /></svg>
                    {{ $p['name'] }}
                  </button>
                </div>
                <div class="tph__body">
                  <p class="tph__desc" id="part-desc-{{ $loop->index }}"><strong>{{ $p['name'] }}.</strong> {{ $p['text'] }}</p>
                </div>
              </li>
            @endforeach
          </ul>
        </div>

        <div class="tph__stage" aria-label="{{ $t['name'] }}, tampilan 360°" role="region">
          <x-turntable :id="'tt-'.$t['slug']" :label="$t['name'].', fasad dan lantai'" :turntable="$t['sequence']" tone="black" />
          @if (count($parts) > 1)
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

    {{-- Spesifikasi --}}
    <section class="tpx tpx--spec" id="spesifikasi" aria-labelledby="spec-title">
      <div class="wrap tpx__grid">
        <h2 class="display tpx__title" id="spec-title">Spesifikasi</h2>
        <div class="tpx__body">
          @if ($t['intro'])
            <p class="tpx__intro">{{ $t['intro'] }}</p>
          @endif
          <dl class="tpx__specs">
            @foreach ($t['info'] as [$k, $v])
              <div>
                <dt>{{ $k }}</dt>
                <dd>{{ $v }}</dd>
              </div>
            @endforeach
          </dl>
        </div>
      </div>
    </section>

    @if ($t['denah'] !== [])
      {{-- Denah --}}
      {{-- Slider denah --}}
      @php($slidePlans = count($t['denah']) > 1)
      <section class="tpx" aria-labelledby="denah-title" data-view-cursor>
        <div class="wrap tpx__grid">
          <div class="tpx__side">
            <h2 class="display tpx__title" id="denah-title">Denah</h2>
            @if ($slidePlans)
              <div class="tpx__arrows">
                <button class="fac__arrow" type="button" data-plans-prev aria-label="Denah sebelumnya">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M15 5l-7 7 7 7"></path></svg>
                </button>
                <button class="fac__arrow" type="button" data-plans-next aria-label="Denah berikutnya">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 5l7 7-7 7"></path></svg>
                </button>
              </div>
            @endif
          </div>
          <div @class(['tpx__body', 'swiper' => $slidePlans]) @if ($slidePlans) data-plans-swiper @endif>
            <ul @class(['tpx__plans', 'swiper-wrapper' => $slidePlans])>
              @foreach ($t['denah'] as $d)
                <li @class(['swiper-slide' => $slidePlans])>
                  <button class="tpx__plan" type="button" data-open="gallery" data-payload="{{ $denahPayload($loop->index) }}" aria-label="Perbesar denah {{ $d['label'] }}" data-view-target>
                    <img src="{{ asset($d['image']) }}" alt="Denah {{ $d['label'] }}" loading="lazy" />
                  </button>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
        <x-view-cursor />
      </section>
    @endif

    @if ($t['gallery'] !== [] || $vrUrl)
      {{-- Galeri dan VR --}}
      <section class="tpx tpg" aria-labelledby="gallery-title">
        <div class="wrap tpg__head">
          <h2 class="display tpx__title" id="gallery-title">Galeri</h2>
          <div class="tpg__actions">
            @if ($t['gallery'] !== [])
              <button class="btn" type="button" aria-haspopup="dialog" aria-controls="gallery-grid" data-grid-open>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" /></svg>
                Lihat Galeri
              </button>
            @endif
            @if ($vrUrl)
              <a class="btn btn--solid tpx__vr" href="{{ $vrUrl }}" target="_blank" rel="noopener" aria-label="Tur VR 360° (tab baru)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-4l-3-3-3 3H5a2 2 0 0 1-2-2Z" /><circle cx="8.5" cy="11.5" r="1.5" /><circle cx="15.5" cy="11.5" r="1.5" /></svg>
                Tur VR 360°
              </a>
            @endif
          </div>
        </div>

        @if ($t['gallery'] !== [])
          {{-- Strip berjalan --}}
          @php($sets = (int) ceil(6 / count($galleryItems)))
          <div class="tpg__run" style="--n: {{ count($galleryItems) * $sets }}">
            <ul class="tpg__track">
              @foreach (range(1, $sets * 2) as $copy)
                @foreach ($galleryItems as $image)
                  <li @if ($copy > 1) aria-hidden="true" @endif>
                    <img src="{{ $image['thumb'] }}" decoding="async" alt="{{ $copy > 1 ? '' : $image['alt'] }}" loading="lazy" />
                  </li>
                @endforeach
              @endforeach
            </ul>
          </div>

          {{-- Popup grid --}}
          <dialog class="tpg__dialog" id="gallery-grid" aria-labelledby="gallery-grid-title" data-gallery-grid>
            <div class="tpg__bar">
              <div>
                <h3 class="display tpg__dialog-title" id="gallery-grid-title">Galeri {{ $t['name'] }}</h3>
                <p class="tpg__count">{{ count($galleryItems) }} gambar</p>
              </div>
              <button class="tpg__close" type="button" aria-label="Tutup galeri" data-grid-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19" /></svg>
              </button>
            </div>
            <ul class="tpg__grid">
              @foreach ($galleryItems as $image)
                <li>
                  <button class="tpx__shot" type="button" data-open="gallery" data-payload="{{ $galleryPayload($loop->index) }}" aria-label="Perbesar {{ $image['alt'] }}">
                    <img src="{{ $image['thumb'] }}" decoding="async" alt="" loading="lazy" />
                  </button>
                </li>
              @endforeach
            </ul>
          </dialog>
        @endif
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
