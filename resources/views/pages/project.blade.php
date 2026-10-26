{{--
  A project's page (Projects collection, /projects/{slug}), in the homepage's
  layout system: hero, then Masterplan, Location, Cluster, Facility, Type and
  Unit availability, each only when it has content. The project's Global
  Style (fonts, colours, corners, background, logo, marker) arrives as
  $p['style'] and overrides the homepage tokens. Data: App\Support\ProjectPage.

  Laid out after design-project.pdf: light hero, the Masterplan and Cluster
  aerials with their green panels, Location on the green, the facilities
  carousel, the type cards, and the siteplan with its block plans.

  Pictures open in the shared overlay (resources/js/overlay.js); unit types
  open their own sheet and the WhatsApp panel toggles (resources/js/project-page.js).
--}}
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

<x-layouts.base :favicon="$p['style']['favicon']" :title="$p['title']" :description="$p['hero']['subtitle'] ?: null" body-class="is-project" entry="resources/js/project.js">
  @if ($p['style']['css'] !== '')
    <x-slot:head>
      <style>{!! $p['style']['css'] !!}</style>
    </x-slot:head>
  @endif

  <x-header variant="project" :nav="$p['nav']" :logo="$p['style']['logo']" />

  <main>
    {{-- The homepage's hero banner, filled from the project's Hero (heading, description, media as the slider). --}}
    {{-- data-protect: the hero's pictures can't be saved or copied from the browser's menu or by dragging (resources/js/protect-media.js). --}}
    <div data-protect data-hero-parallax>
      {{-- For now without the description (and so without the bottom row's scroll cue); Hero → Deskripsi stays in the CMS. --}}
      <x-home.hero :hero="[...$p['hero'], 'eyebrow' => $p['title'], 'cue' => 'Scroll Down', 'subtitle' => '']" />
    </div>

    {{-- Masterplan and Cluster: the homepage's scenes as they are (Masterplan with its opening video), with the CMS's points as hotspots and the Penanda as pins. --}}
    @isset($s['masterplan'])
      @php($mp = $s['masterplan'])
      @if ($mp['scene'])
        <x-scroll-scene
          :id="$sectionId('masterplan')"
          :title="$mp['title']"
          :intro="null"
          :scene="$mp['scene']['scene']"
          :hotspots="$mp['scene']['hotspots']"
          :masterplan="['title' => $mp['title'], 'text' => $mp['intro'], 'video' => $mp['scene']['video'] ?? null, 'panel' => 'zoom']"
          :marker="$marker"
        />
      @else
        <x-project.points :section="$mp" :id="$sectionId('masterplan')" />
      @endif
    @endisset

    @isset($s['location'])
      <x-project.location :loc="$s['location']" :id="$sectionId('location')" :gallery="$gallery" />
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
          :marker="$marker"
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

    @isset($s['types'])
      @php($ty = $s['types'])
      @if ($ty['heroImage'])
        <x-project.band :image="$ty['heroImage']" />
      @endif
      <x-home.type-grid variant="project" :id="$sectionId('types')" :types="$ty['cards']" :heading="['title' => $ty['title'], 'intro' => null]" />
    @endisset

    @isset($s['availability'])
      <x-project.availability :av="$s['availability']" :id="$sectionId('availability')" :gallery="$gallery" />
    @endisset
  </main>

  @isset($s['types'])
    @foreach ($s['types']['groups'] as $group)
      @foreach ($group['units'] as $unit)
        @if ($unit['url'] === null)
          <x-project.unit-sheet :unit="$unit" :gallery="$gallery" />
        @endif
      @endforeach
    @endforeach
  @endisset

  @if ($p['whatsapp'])
    <x-project.whatsapp :whatsapp="$p['whatsapp']" />
  @endif

  <x-footer />
</x-layouts.base>
