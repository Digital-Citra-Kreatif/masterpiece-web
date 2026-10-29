<x-layouts.base entry="resources/js/home.js" body-class="is-home">
  <x-header variant="home" />

  <main>
    <!-- Hero -->
    <div data-hero-parallax>
      <x-home.hero :hero="$hero" />
    </div>

    @if ($projects['cards'] !== [])
      <!-- Grid proyek -->
      <x-home.project-grid :id="$projects['id']" :projects="$projects['cards']" :heading="$projects['heading']" />
    @endif
  </main>

  <x-footer />
</x-layouts.base>
