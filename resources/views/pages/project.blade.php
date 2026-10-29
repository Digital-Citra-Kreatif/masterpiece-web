{{-- Halaman proyek --}}
@php
  $s = $p['sections'];
  $marker = $p['style']['marker'];
  $gallery = fn (string $title, array $images, int $index = 0): string => json_encode([
      'title' => $title,
      'items' => array_map(fn (array $image): array => ['src' => $image['full'], 'alt' => $image['alt'] ?: ($image['label'] ?? $image['name'] ?? $title), 'caption' => $image['label'] ?? $image['name'] ?? ''], $images),
      'index' => $index,
  ]);
  $sectionId = fn (string $handle): string => \App\Support\ProjectPage::SECTIONS[$handle]['id'];
@endphp

<x-layouts.base :favicon="$p['style']['favicon']" :title="$p['title']" :description="$p['description']" body-class="is-project" entry="resources/js/project.js">
  @if ($p['style']['css'] !== '')
    <x-slot:head>
      <style>{!! $p['style']['css'] !!}</style>
    </x-slot:head>
  @endif

  <x-header variant="project" :nav="$p['nav']" :logo="$p['style']['logo']" :mark="['href' => request()->url(), 'label' => $p['title'].', muat ulang halaman proyek']" />

  <main>
    {{-- Hero proyek --}}
    {{-- Lindungi gambar hero --}}
    <div data-protect data-hero-parallax>
      {{-- Tanpa deskripsi --}}
      <x-home.hero :hero="[...$p['hero'], 'eyebrow' => $p['title'], 'cue' => 'Gulir ke bawah', 'subtitle' => '']" />
    </div>

    {{-- Scene Masterplan Cluster --}}
    @isset($s['masterplan'])
      @php($mp = $s['masterplan'])
      @if ($mp['scene'])
        <x-scroll-scene
          :id="$sectionId('masterplan')"
          :title="$mp['title']"
          :intro="null"
          :scene="$mp['scene']['scene']"
          :hotspots="$mp['scene']['hotspots']"
          :tracks="$mp['scene']['tracks'] ?? null"
          :masterplan="['title' => $mp['title'], 'text' => $mp['intro'], 'video' => $mp['scene']['video'] ?? null, 'panel' => 'zoom']"
          :marker="$marker"
        />
      @else
        <x-project.points :section="$mp" :id="$sectionId('masterplan')" />
      @endif
    @endisset

    @isset($s['location'])
      <x-project.location :loc="$s['location']" :id="$sectionId('location')" />
    @endisset

    @isset($s['cluster'])
      @php($cl = $s['cluster'])
      @if ($cl['scene'])
        <x-scroll-scene
          :id="$sectionId('cluster')"
          :title="$cl['title']"
          :intro="null"
          :scene="$cl['scene']['scene']"
          :hotspots="$cl['scene']['hotspots']"
          :tracks="$cl['scene']['tracks'] ?? null"
          :marker="$marker"
          panel="zoom"
        />
      @else
        <x-project.points :section="$cl" :id="$sectionId('cluster')" />
      @endif
    @endisset

    @isset($s['facilities'])
      <x-home.facilities
        variant="split"
        :id="$sectionId('facilities')"
        :facilities="$s['facilities']['items']"
        :heading="['title' => $s['facilities']['title'], 'intro' => $s['facilities']['intro']]"
      />
    @endisset

    @isset($s['turntable'])
      <x-project.turntable :section="$s['turntable']" :id="$sectionId('turntable')" />
    @endisset

    @isset($s['types'])
      @php($ty = $s['types'])
      <x-home.type-grid variant="project" :id="$sectionId('types')" :types="$ty['cards']" :heading="['title' => $ty['title'], 'intro' => null]">
        {{-- Gambar potongan --}}
        @if ($ty['heroImage'])
          <x-slot:top>
            <x-project.band :image="$ty['heroImage']" />
          </x-slot:top>
        @endif
      </x-home.type-grid>
    @endisset

    @isset($s['availability'])
      {{-- Ketersediaan unit --}}
      <x-home.unit-update :id="$sectionId('availability')" :units="$s['availability']['units']" :counts="$s['availability']['counts']" :heading="['title' => $s['availability']['title'], 'intro' => $s['availability']['intro']]"
        :siteplan="$s['availability']['siteplan'] ? ['image' => $s['availability']['siteplan']['full'], 'width' => $s['availability']['siteplan']['width'] ?: 1600, 'height' => $s['availability']['siteplan']['height'] ?: 1000, 'placeholder' => false] : null"
      />
    @endisset
  </main>

  @isset($s['types'])
    @foreach ($s['types']['units'] as $unit)
      @if ($unit['url'] === null)
        <x-project.unit-sheet :unit="$unit" :gallery="$gallery" />
      @endif
    @endforeach
  @endisset

  @if ($p['whatsapp'])
    <x-project.whatsapp :whatsapp="$p['whatsapp']" :logo="$p['style']['logo']" />
  @endif

  <x-footer :logo="$p['style']['logo']" />
</x-layouts.base>
