{{--
  Type page, laid out as a drawing sheet (single unit.pdf): the parts first, in
  two columns (name, the part's text and a tab per part on the left; the 360°
  viewer on the right, clear of the side line and the rule: a tab turns it to the
  part's keyframe), then rows with the title in a narrow column and
  the content right of a rule: specifications, floor plans, gallery with the VR
  tour, and the project's other types.

  A project's type wears the project's style, logo (a header with a button back
  to the project, no menu), footer logo and WhatsApp button; a type without one
  keeps the site's own.

  Tabs in resources/js/type-parts.js.
--}}
@php
  $pj = $t['project'];
  $parts = $t['parts'];

  $galleryItems = collect($t['gallery'])->map(fn ($src, $i) => ['src' => asset($src), 'alt' => "{$t['name']}, gambar ".($i + 1)]);
  $galleryPayload = fn ($index = 0) => json_encode(['title' => "{$t['name']}: Galeri", 'items' => $galleryItems, 'index' => $index]);
  $denahItems = collect($t['denah'])->map(fn ($d) => ['src' => asset($d['image']), 'caption' => $d['label'], 'alt' => "Denah {$d['label']}"]);
  $denahPayload = fn ($index) => json_encode(['title' => "{$t['name']}: Denah", 'items' => $denahItems, 'index' => $index]);
  $vrPayload = filled($t['vr']['url']) ? json_encode(['src' => asset($t['vr']['url']), 'title' => "{$t['name']}: Tur VR"]) : null;
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
    {{-- Bagian sebagai tab: nama, teks dan tab di kiri, viewer 360° di kanan --}}
    <section class="tph" aria-labelledby="type-name" data-parts="{{ json_encode(collect($parts)->map(fn ($p) => Arr::only($p, ['name', 'text', 'frame']))) }}">
      <div class="wrap tph__grid">
        <div class="tph__copy">
          <h1 class="display tph__name" id="type-name">{{ $t['name'] }}</h1>
          <p class="tph__text" data-part-text aria-live="polite">{{ $parts[0]['text'] }}</p>

          <div class="tph__tabs" role="tablist" aria-label="Bagian" aria-orientation="vertical">
            @foreach ($parts as $p)
              <button
                class="tph__tab"
                type="button"
                role="tab"
                id="part-tab-{{ $loop->index }}"
                aria-controls="part-panel"
                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                tabindex="{{ $loop->first ? 0 : -1 }}"
                data-part-tab
              >
                <span class="tph__tab-no" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>
                <span class="tph__tab-name">{{ $p['name'] }}</span>
              </button>
            @endforeach
          </div>

        </div>

        <div class="tph__stage" id="part-panel" role="tabpanel" aria-labelledby="part-tab-0" data-part-panel>
          <x-turntable :id="'tt-'.$t['slug']" :label="$t['name'].', fasad dan lantai'" :turntable="$t['sequence']" tone="black" />
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
      {{-- Denah: gambar memenuhi kolom kanan --}}
      {{-- Lebih dari satu denah: slider dengan panah (resources/js/plans-swiper.js) --}}
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

    @if ($t['gallery'] !== [] || $vrPayload)
      {{-- Galeri dan tur VR --}}
      <section class="tpx" aria-labelledby="gallery-title">
        <div class="wrap tpx__grid">
          <div class="tpx__side">
            <h2 class="display tpx__title" id="gallery-title">Galeri</h2>
            @if ($vrPayload)
              <button class="btn btn--solid tpx__vr" type="button" data-open="iframe" data-payload="{{ $vrPayload }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-4l-3-3-3 3H5a2 2 0 0 1-2-2Z" /><circle cx="8.5" cy="11.5" r="1.5" /><circle cx="15.5" cy="11.5" r="1.5" /></svg>
                Tur VR 360°
              </button>
            @endif
          </div>
          <div class="tpx__body">
            @if ($t['gallery'] !== [])
              <ul class="tpx__gallery">
                @foreach ($galleryItems as $image)
                  <li>
                    <button class="tpx__shot" type="button" data-open="gallery" data-payload="{{ $galleryPayload($loop->index) }}" aria-label="Perbesar {{ $image['alt'] }}">
                      <img src="{{ $image['src'] }}" alt="" loading="lazy" />
                    </button>
                  </li>
                @endforeach
              </ul>
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
