{{--
  A WebRotate 360 turntable. Rotates on horizontal drag, horizontal wheel /
  trackpad swipe, the arrow buttons, or ←/→ when focused. Vertical scroll over
  the viewer keeps scrolling the page.

  turntable: { package, width, height, firstImage?, poster?, placeholder? }
  `hotspots` maps WebRotate hotspot ids to the content of the info panel. The
  list under the viewer opens the same panels, so hotspots are reachable by
  keyboard and screen readers too.

  Logic in resources/js/turntable.js.
--}}
@props(['id', 'label', 'turntable', 'hotspots' => [], 'kicker' => null, 'tone' => 'dark'])

@php
  $resolved = collect($hotspots)->map(fn ($h) => [
      ...$h,
      'kicker' => $kicker ?? $label,
      'image' => isset($h['image']) ? asset($h['image']) : null,
  ]);
  $pkg = rtrim(asset($turntable['package']), '/').'/';
@endphp

<div
  class="tt tt--{{ $tone }}"
  style="--native: {{ $turntable['width'] }}px"
  data-turntable
  data-wr360="{{ asset('wr360') }}"
  data-hotspots="{{ json_encode((object) $resolved->all()) }}"
>
  <div
    class="tt__stage"
    style="aspect-ratio: {{ $turntable['width'] }} / {{ $turntable['height'] }}"
    data-config="{{ $pkg }}config.xml"
    data-root="{{ $pkg }}"
    data-width="{{ $turntable['width'] }}"
    data-height="{{ $turntable['height'] }}"
    data-first="{{ $turntable['firstImage'] ?? 0 }}"
  >
    @isset($turntable['poster'])
      <img class="tt__poster" src="{{ asset($turntable['poster']) }}" alt="" loading="lazy" />
    @endisset
    <div
      class="tt__viewer"
      id="{{ $id }}"
      tabindex="0"
      role="application"
      aria-roledescription="360° viewer"
      aria-label="{{ $label }}, tampilan 360°. Gunakan panah kiri dan kanan untuk memutar."
    ></div>
    <div class="tt__loading" aria-hidden="true"><span></span></div>
    <p class="tt__locked">
      Tampilan 360° ini aktif setelah lisensi WebRotate 360 PRO dipasang. Versi gratis hanya memuat satu viewer per halaman.
    </p>
    @if ($site['showPlaceholderBadges'] && ($turntable['placeholder'] ?? false))
      <span class="badge tt__badge">Sekuens contoh</span>
    @endif
  </div>

  <div class="tt__hud">
    <button class="icon-btn tt__step" type="button" data-step="-1" aria-label="Putar ke kiri" aria-controls="{{ $id }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M15 5l-7 7 7 7"></path></svg>
    </button>
    <div class="tt__dial" aria-hidden="true">
      <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="17" /><path class="tt__needle" d="M20 20 L20 5" /></svg>
      <span class="tt__deg">0°</span>
    </div>
    <span class="tt__spacer" aria-hidden="true"></span>
    <button class="icon-btn tt__step" type="button" data-step="1" aria-label="Putar ke kanan" aria-controls="{{ $id }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"></path></svg>
    </button>
  </div>

  @if ($resolved->isNotEmpty())
    <ol class="tt__spots">
      @foreach ($resolved as $key => $h)
        <li>
          <button type="button" data-spot="{{ $key }}">
            <span class="tt__spot-no">{{ sprintf('%02d', $loop->iteration) }}</span>
            {{ $h['title'] }}
          </button>
        </li>
      @endforeach
    </ol>
  @endif
</div>
