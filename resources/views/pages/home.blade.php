<x-layouts.base entry="resources/js/home.js">
  <x-header variant="home" />

  <main>
    <!-- Hero -->
    <x-home.hero :hero="$hero" />

    <!-- Masterplan dan area -->
    <x-scroll-scene
      id="kawasan"
      :title="$area['title']"
      :intro="$area['intro']"
      :scene="$area['scene']"
      :hotspots="$area['hotspots']"
      :masterplan="$masterplan"
    />

    <!-- Peta area -->
    <section id="peta" class="map" aria-labelledby="peta-title">
      <div class="wrap">
        <x-sheet-header id="peta-title" :title="$areaMap['title']" :intro="$areaMap['intro']" />
        <figure class="map__figure" data-reveal>
          <img
            src="{{ asset($areaMap['image']) }}"
            alt="{{ $areaMap['alt'] }}"
            width="{{ $areaMap['width'] }}"
            height="{{ $areaMap['height'] }}"
            loading="lazy"
          />
          @if ($site['showPlaceholderBadges'] && $areaMap['placeholder'])
            <span class="badge map__badge">Peta contoh</span>
          @endif
        </figure>
      </div>
    </section>

    <!-- Cluster -->
    <x-scroll-scene
      id="cluster"
      :title="$cluster['title']"
      :intro="$cluster['intro']"
      :scene="$cluster['scene']"
      :hotspots="$cluster['hotspots']"
    />

    <!-- Fasilitas -->
    <x-home.facilities :facilities="$facilities" :heading="$headings['facilities']" />

    <!-- Tipe unit -->
    <x-home.type-grid :types="$types" :heading="$headings['types']" />

    <!-- Update unit -->
    @if ($units !== [])
      <x-home.unit-update :units="$units" :counts="$counts" :heading="$headings['units']" />
    @endif
  </main>

  <x-footer />
</x-layouts.base>
