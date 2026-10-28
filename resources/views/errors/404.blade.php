@php($nf = \App\Support\Content::notFound())

<x-layouts.base :title="$nf['title']">
  <main class="nf">
    <div class="nf__head">
      <span class="nf__code" aria-hidden="true">404</span>
      <h1 class="nf__title">{{ $nf['title'] }}</h1>
    </div>
    @if ($nf['description'])
      <p class="nf__text">{{ $nf['description'] }}</p>
    @endif
    <a class="btn btn--solid nf__btn" href="{{ $nf['link']['href'] }}" @if ($nf['link']['newTab']) target="_blank" rel="noopener" @endif>{{ $nf['link']['label'] }}</a>
  </main>
  <x-footer />
</x-layouts.base>
