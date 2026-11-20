{{-- Spesifikasi dan tombol --}}
@props(['facts' => [], 'gallery' => null, 'vr' => null, 'back' => false])

<div {{ $attributes->class('tps__side') }}>
  @if ($facts !== [])
    <dl class="tps__facts">
      @foreach ($facts as [$k, $v])
        <div>
          <dt>{{ $k }}</dt>
          <dd>{{ $v }}</dd>
        </div>
      @endforeach
    </dl>
  @endif

  @if ($gallery || $vr || $back)
    {{-- Galeri dan VR --}}
    <div class="tps__actions">
      @if ($gallery)
        <button class="btn" type="button" data-open="gallery" data-payload="{{ $gallery }}">Galeri</button>
      @endif
      @if ($vr)
        <a class="btn btn--solid" href="{{ $vr }}" target="_blank" rel="noopener" aria-label="Tur VR 360° (tab baru)">VR</a>
      @endif
      @if ($back)
        {{-- Keluar zoom 360° --}}
        <button class="btn tps__back" type="button" data-spin-back hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6" /></svg>
          Kembali
        </button>
      @endif
    </div>
  @endif
</div>
