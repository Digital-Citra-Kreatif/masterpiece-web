{{-- Halaman maintenance, isi dari global CMS --}}
<x-layouts.base :title="$mt['title']" :description="$mt['description']" :preload="false">
  <main class="nf nf--mt">
    <h1 class="nf__title">{{ $mt['title'] }}</h1>
    @if ($mt['description'])
      <p class="nf__text">{!! nl2br(e($mt['description']), false) !!}</p>
    @endif
    @if ($mt['countdown'])
      {{-- Hitung mundur ke waktu di CMS; angka awal dari server --}}
      <div class="nf__count" role="timer" aria-label="Dibuka kembali {{ $mt['countdown']['label'] }}" data-countdown="{{ $mt['countdown']['left'] }}">
        @foreach (['days' => 'Hari', 'hours' => 'Jam', 'minutes' => 'Menit', 'seconds' => 'Detik'] as $part => $unit)
          @if (! $loop->first)
            <span class="nf__sep" aria-hidden="true">:</span>
          @endif
          <span class="nf__unit" aria-hidden="true">
            <span class="nf__num" data-countdown-part="{{ $part }}">{{ sprintf('%02d', $mt['countdown']['parts'][$part]) }}</span>
            <span class="nf__unit-label">{{ $unit }}</span>
          </span>
        @endforeach
      </div>
    @endif
  </main>
</x-layouts.base>
