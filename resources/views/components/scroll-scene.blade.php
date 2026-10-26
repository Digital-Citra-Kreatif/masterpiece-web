{{--
  A pinned, full-screen scene whose image sequence scrubs with page scroll
  (after malinowskiego.com). Used for Kawasan and Cluster on the homepage.

  With `masterplan`, the scene opens on the Masterplan composition: the orbit
  video (the same orbit as the frames; without one, the scene's first frame) in a
  portrait frame, the title over its left edge and the text by its right.
  Scrolling moves the title out to the left and the text to the right while the
  frame grows to fill the screen ([data-zoom-end]); the video keeps looping full
  screen for a stretch of scroll. Scrolling on ([data-intro-end]), the canvas
  picks up at the frame the video is showing, the section panel and markers fade
  in and the orbit scrubs on. Without `masterplan`, the panel and markers show
  from the start.

  Opening a hotspot (marker or list) fast-forwards the orbit to the hotspot's
  default angle while zooming in on it, with its info in place of the section
  panel. Back, Esc, or scrolling on returns to the orbit.

  scene:      { base, startFrame?, placeholder? } (a frame sequence), or
              { still: { src, width, height, sweep? } } (one picture: scroll-scene.js
              turns it back and forth by up to `sweep`°, the pins turning with it,
              until the sequence exists; the rest works the same)
  hotspots:   { key: { track, frame?, title, short?, text } }, or with a still
              { key: { x, y, title, short?, text } } (% of the picture)
  masterplan: { title, text, video?, panel? }: panel 'zoom' brings the section
  panel and shade in as soon as the frame fills the screen, over the video, and
  an open hotspot then shows its picture (`image`) on the right, with no back
  button (Esc, scrolling on or the arrows go on).

  marker: { shape, image } (a project's Global Style → Penanda): the markers are
  that shape (its colour --marker) or that image instead of numbered squares.

  variant 'project' (project pages, after design-project.pdf; the same
  interaction): the picture unshaded, the pins the project's logo in a cream
  drop (`pin`), the panel a green box on the `side` ('left' or 'right') with its
  points in columns, the location panel the same box. `card` adds the open (or
  first) hotspot's picture on the other side; without it the open hotspot's
  picture shows in its panel. Hotspots may carry an `image` for these.

  Logic in resources/js/scroll-scene.js (orbit) and resources/js/masterplan.js (opening).
--}}
@props(['id', 'title', 'intro', 'scene', 'hotspots', 'masterplan' => null, 'variant' => 'default', 'side' => 'left', 'pin' => null, 'card' => false, 'marker' => null])

@php
  $still = $scene['still'] ?? null;
  $spots = collect($hotspots)->map(fn ($h, $key) => [
      'key' => $key,
      'short' => ($h['short'] ?? null) ?: $h['title'],
      'content' => [
          'track' => $h['track'] ?? $key,
          'point' => isset($h['x'], $h['y']) ? [$h['x'], $h['y']] : null,
          'frame' => $h['frame'] ?? null,
          'title' => $h['title'],
          'text' => $h['text'],
          'image' => $h['image'] ?? null,
      ],
  ])->values();
  $project = $variant === 'project';
  $first = $spots->first(fn (array $s): bool => $s['content']['image'] !== null || $s['content']['text'] !== '');
  $base = $still ? null : rtrim(asset($scene['base']), '/').'/';
  $firstFrame = $still ? $still['src'] : $base.'lg/f_'.sprintf('%03d', $scene['startFrame'] ?? 0).'.jpg';
  [$frameW, $frameH] = $still ? [$still['width'], $still['height']] : [1280, 720];
@endphp

<section
  @class(['scene', 'scene--masterplan' => $masterplan, 'scene--project' => $project, "scene--{$side}" => $project])
  data-scene
  @if (($masterplan['panel'] ?? null) === 'zoom') data-panel="zoom" @endif
  @if ($still)
    data-still="{{ $still['src'] }}"
    data-sweep="{{ $still['sweep'] ?? 12 }}"
  @else
    data-base="{{ $base }}"
  @endif
  data-start="{{ $scene['startFrame'] ?? 0 }}"
  data-spots="{{ json_encode($spots->pluck('content', 'key')) }}"
  aria-labelledby="{{ $id }}-title"
>
  <span id="{{ $id }}" class="scene__anchor" aria-hidden="true"></span>
  @if ($masterplan)
    <span class="scene__zoom-end" data-zoom-end aria-hidden="true"></span>
    <span class="scene__intro-end" data-intro-end aria-hidden="true"></span>
  @endif

  <div class="scene__stage">
    <canvas class="scene__media scene__canvas" aria-hidden="true"></canvas>
    <div class="scene__shade" aria-hidden="true"></div>

    <div class="scene__markers">
      @foreach ($spots as $s)
        <button @class(['spot', 'spot--marker' => $marker && ! $project]) type="button" data-spot="{{ $s['key'] }}" tabindex="-1" hidden>
          <span class="spot__dot">
            @if ($marker && ! $project)
              <x-project.marker :marker="$marker" />
            @elseif ($project && $pin)
              <img src="{{ $pin }}" alt="" />
            @else
              {{ $loop->iteration }}
            @endif
          </span>
          <span class="spot__label">{{ $s['short'] }}</span>
        </button>
      @endforeach
    </div>

    @if ($site['showPlaceholderBadges'] && ($scene['placeholder'] ?? false))
      <span class="badge scene__badge" data-phase="area">Sekuens contoh</span>
    @endif

    @if ($masterplan)
      <div class="mp" data-masterplan>
        {{-- The frames' and the video's proportions (1280 × 720, or the still's), for the cover-fit maths in masterplan.js. --}}
        <div class="mp__frame" data-mp-frame>
          @if ($masterplan['video'] ?? null)
            <video
              class="mp__img scene__video"
              muted
              loop
              playsinline
              preload="auto"
              poster="{{ $firstFrame }}"
              data-src="{{ asset($masterplan['video']) }}"
              data-mp-img
              data-w="{{ $frameW }}"
              data-h="{{ $frameH }}"
              aria-hidden="true"
            ></video>
          @else
            <img class="mp__img" src="{{ $firstFrame }}" alt="" width="{{ $frameW }}" height="{{ $frameH }}" data-mp-img data-w="{{ $frameW }}" data-h="{{ $frameH }}" />
          @endif
        </div>
        <span class="mp__box" data-mp-box aria-hidden="true"></span>
        <h2 class="display mp__title" data-mp-title>{{ $masterplan['title'] }}</h2>
        @if ($masterplan['text'])
          <p class="mp__text" data-mp-text>{!! nl2br(e($masterplan['text']), false) !!}</p>
        @endif
      </div>
    @endif

    <div class="scene__area wrap" data-phase="area">
      @if ($project)
        <div class="scene__box">
      @endif
      <h2 class="display scene__area-title" id="{{ $id }}-title">{{ $title }}</h2>
      @if ($intro)
        <p class="scene__intro">{{ $intro }}</p>
      @endif
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
      @if ($project)
        </div>
      @endif

    </div>

    @if ($card && $first)
      <!-- The open (or first) hotspot's picture, filled in by scroll-scene.js -->
      <figure class="scene__card" data-scene-card data-scene-avoid @unless ($first['content']['image']) hidden @endunless>
        <img class="scene__card-img" src="{{ $first['content']['image'] }}" alt="{{ $first['content']['title'] }}" />
      </figure>
    @endif

    @if (($masterplan['panel'] ?? null) === 'zoom')
      <!-- The open hotspot's picture, on the right (filled in by scroll-scene.js) -->
      <figure class="scene__place-figure" data-place-figure hidden>
        <img alt="" />
      </figure>
    @endif

    <!-- Location view: shown in place of the panel while a hotspot is open -->
    <div class="scene__loc wrap" role="group" aria-labelledby="{{ $id }}-loc-title" hidden>
      <button class="scene__back" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M19 12H5M11 6l-6 6 6 6"></path>
        </svg>
        Kembali ke {{ Str::lower($title) }}
      </button>
      @if ($project && ! $card)
        <img class="scene__loc-img" alt="" hidden />
      @endif
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
  </div>
</section>
