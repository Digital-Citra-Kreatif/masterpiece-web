<x-layouts.base entry="resources/js/home.js">
  <x-header variant="home" />

  <main>
    <!-- 1. Hero -->
    <x-home.hero :hero="$hero" />

    <!-- 2. Masterplan + Area: one pinned scene; the Masterplan frame zooms into the scroll scrub -->
    <x-scroll-scene
      id="kawasan"
      :title="$area['title']"
      :intro="$area['intro']"
      :scene="$area['scene']"
      :hotspots="$area['hotspots']"
      :masterplan="$masterplan"
    />

    <!-- 3. Area map (static, no interaction) -->
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

    <!-- 4. Cluster: scroll scene like Kawasan -->
    <x-scroll-scene
      id="cluster"
      :title="$cluster['title']"
      :intro="$cluster['intro']"
      :scene="$cluster['scene']"
      :hotspots="$cluster['hotspots']"
    />

    <!-- 5. Facilities -->
    <x-home.facilities :facilities="$facilities" :heading="$headings['facilities']" />

    <!-- 6. Unit types -->
    <x-home.type-grid :types="$types" :heading="$headings['types']" />

    <!-- 7. Unit update -->
    <x-home.unit-update :units="$units" :counts="$counts" :heading="$headings['units']" />
  </main>

  <x-footer />
</x-layouts.base>
