{{--
  A pinned, full-screen scene whose image sequence scrubs with page scroll
  (after malinowskiego.com). Used for Kawasan and Cluster on the homepage.

  With `hero`, the scene opens as the hero banner over a looping video of the
  same orbit; on the first scroll the canvas takes over at the frame the video is
  showing, and the hero text gives way to the section panel without a break.
  Without `hero`, the panel and markers show from the start.

  Opening a hotspot (marker or list) fast-forwards the orbit to the hotspot's
  default angle while zooming in on it, with its info in place of the section
  panel. Back, Esc, or scrolling on returns to the orbit.

  scene:    { base, startFrame?, placeholder? }
  hotspots: { key: { track, frame?, title, short?, text } }
  hero:     { title, subtitle, teaser, full, cover, fullThumb }

  Logic in resources/js/scroll-scene.js.
--}}
@props(['id', 'title', 'intro', 'scene', 'hotspots', 'hero' => null])

@php
  $spots = collect($hotspots)->map(fn ($h, $key) => [
      'key' => $key,
      'short' => ($h['short'] ?? null) ?: $h['title'],
      'content' => ['track' => $h['track'], 'frame' => $h['frame'] ?? null, 'title' => $h['title'], 'text' => $h['text']],
  ])->values();
@endphp

<section
  @class(['scene', 'scene--hero' => $hero])
  data-scene
  data-base="{{ rtrim(asset($scene['base']), '/') }}/"
  data-start="{{ $scene['startFrame'] ?? 0 }}"
  data-spots="{{ json_encode($spots->pluck('content', 'key')) }}"
  aria-labelledby="{{ $id }}-title"
>
  <span id="{{ $id }}" class="scene__anchor" aria-hidden="true"></span>

  <div class="scene__stage">
    @if ($hero)
      <video
        class="scene__media scene__video"
        muted
        loop
        playsinline
        preload="auto"
        poster="{{ asset($hero['cover']) }}"
        data-src="{{ asset($hero['teaser']) }}"
        aria-hidden="true"
      ></video>
    @endif
    <canvas class="scene__media scene__canvas" aria-hidden="true"></canvas>
    <div class="scene__shade" aria-hidden="true"></div>
    @if ($hero)
      <div class="scene__hero-shade" aria-hidden="true"></div>
    @endif

    <div class="scene__markers">
      @foreach ($spots as $s)
        <button class="spot" type="button" data-spot="{{ $s['key'] }}" tabindex="-1" hidden>
          <span class="spot__dot">{{ $loop->iteration }}</span>
          <span class="spot__label">{{ $s['short'] }}</span>
        </button>
      @endforeach
    </div>

    @if ($site['showPlaceholderBadges'] && ($scene['placeholder'] ?? false))
      <span class="badge scene__badge" data-phase="area">Sekuens contoh</span>
    @endif

    @if ($hero)
      <div class="scene__hero wrap" data-phase="hero">
        <h1 class="scene__title">{!! nl2br(e($hero['title']), false) !!}</h1>
        <div class="scene__hero-foot">
          <span class="scene__rule" aria-hidden="true"></span>
          <p class="scene__lead">{!! nl2br(e($hero['subtitle']), false) !!}</p>
          <button
            class="scene__play"
            type="button"
            data-open="video"
            data-payload="{{ json_encode(['src' => asset($hero['full']), 'title' => $site['fullName']]) }}"
          >
            <img class="scene__play-thumb" src="{{ asset($hero['fullThumb']) }}" alt="" width="98" height="98" />
            <span class="scene__play-label">Play Full Video</span>
            <span class="scene__play-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M9 6.5v11l9-5.5z" fill="currentColor" /></svg>
            </span>
          </button>
        </div>
      </div>
    @endif

    <div class="scene__area wrap" data-phase="area">
      <h2 class="display scene__area-title" id="{{ $id }}-title">{{ $title }}</h2>
      <p class="scene__intro">{{ $intro }}</p>
      <ol class="scene__list">
        @foreach ($spots as $s)
          <li>
            <button type="button" data-spot-open="{{ $s['key'] }}">
              <span>{{ sprintf('%02d', $loop->iteration) }}</span>
              {{ $s['content']['title'] }}
            </button>
          </li>
        @endforeach
      </ol>
    </div>

    <!-- Location view: shown in place of the panel while a hotspot is open -->
    <div class="scene__loc wrap" role="group" aria-labelledby="{{ $id }}-loc-title" hidden>
      <button class="scene__back" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M19 12H5M11 6l-6 6 6 6"></path>
        </svg>
        Kembali ke {{ Str::lower($title) }}
      </button>
      <h3 class="display scene__loc-title" id="{{ $id }}-loc-title" tabindex="-1"></h3>
      <p class="scene__loc-text"></p>
      <div class="scene__loc-nav">
        <button class="icon-btn" type="button" data-loc-step="-1" aria-label="Lokasi sebelumnya">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M15 5l-7 7 7 7"></path></svg>
        </button>
        <button class="icon-btn" type="button" data-loc-step="1" aria-label="Lokasi berikutnya">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"></path></svg>
        </button>
      </div>
    </div>

    <div class="scene__meter" data-phase="area" aria-hidden="true">
      <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="17"></circle><path class="scene__needle" d="M20 20 L20 5"></path></svg>
      <span class="scene__deg">0°</span>
      <span class="scene__bar"><span></span></span>
    </div>
  </div>
</section>
