<x-layouts.base entry="resources/js/home.js" body-class="is-home">
  <x-header variant="home" />

  <main>
    <!-- Hero -->
    <x-home.hero :hero="$hero" />

    @if ($projects['cards'] !== [])
      <!-- Grid proyek -->
      <x-home.project-grid :projects="$projects['cards']" :heading="$projects['heading']" />
    @endif
  </main>

  <x-footer />
</x-layouts.base>
