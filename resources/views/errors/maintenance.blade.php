{{-- Halaman maintenance, isi dari global CMS --}}
<x-layouts.base :title="$mt['title']" :description="$mt['description']">
  <main class="nf nf--mt">
    <h1 class="nf__title">{{ $mt['title'] }}</h1>
    @if ($mt['description'])
      <p class="nf__text">{!! nl2br(e($mt['description']), false) !!}</p>
    @endif
    @if ($mt['date'])
      <p class="nf__date">Perkiraan kembali <time datetime="{{ $mt['date']['iso'] }}">{{ $mt['date']['label'] }}</time></p>
    @endif
  </main>
</x-layouts.base>
