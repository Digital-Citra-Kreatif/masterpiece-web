{{-- Spesifikasi dan tombol --}}
@props(['facts' => [], 'gallery' => null, 'vr' => null])

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

  @if ($gallery || $vr)
    {{-- Galeri dan VR --}}
    <div class="tps__actions">
      @if ($gallery)
        <button class="btn" type="button" data-open="gallery" data-payload="{{ $gallery }}">Galeri</button>
      @endif
      @if ($vr)
        <a class="btn btn--solid" href="{{ $vr }}" target="_blank" rel="noopener" aria-label="Tur VR 360° (tab baru)">VR</a>
      @endif
    </div>
  @endif
</div>
