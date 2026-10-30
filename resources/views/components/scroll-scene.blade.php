{{-- Scene scroll --}}
@props(['id', 'title', 'intro', 'scene', 'hotspots', 'masterplan' => null, 'variant' => 'default', 'side' => 'left', 'pin' => null, 'card' => false, 'marker' => null, 'tracks' => null, 'panel' => null])

@php
  $still = $scene['still'] ?? null;
  $spots = collect($hotspots)->map(fn ($h, $key) => [
      'key' => $key,
      'number' => $h['number'] ?? null,
      'short' => ($h['short'] ?? null) ?: $h['title'],
      'content' => [
          'track' => $h['track'] ?? $key,
          'point' => isset($h['x'], $h['y']) ? [$h['x'], $h['y']] : null,
          'frame' => $h['frame'] ?? null,
          'title' => $h['title'],
          'text' => $h['text'],
          'image' => $h['image'] ?? null,
          'zoom' => $h['zoom'] ?? null,
      ],
  ])->values();
  $project = $variant === 'project';
  $zoom = ($masterplan['panel'] ?? $panel) === 'zoom';
  $first = $spots->first(fn (array $s): bool => $s['content']['image'] !== null || $s['content']['text'] !== '');
  $base = $still ? null : rtrim(asset($scene['base']), '/').'/';
  $firstFrame = $still ? $still['src'] : $base.'lg/f_'.sprintf('%03d', $scene['startFrame'] ?? 0).'.jpg';
  [$frameW, $frameH] = $still ? [$still['width'], $still['height']] : [1280, 720];
@endphp

<section
  @class(['scene', 'scene--masterplan' => $masterplan, 'scene--project' => $project, "scene--{$side}" => $project])
  data-scene
  @if ($tracks) data-tracks="{{ json_encode($tracks) }}" @endif
  @if ($zoom) data-panel="zoom" @endif
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
    @if ($spots->contains(fn (array $s): bool => $s['content']['zoom'] !== null))
      {{-- Latar tajam saat titik di-zoom --}}
      <img class="scene__media scene__zoom" alt="" aria-hidden="true" decoding="async" data-scene-zoom />
    @endif
    <div class="scene__shade" aria-hidden="true"></div>

    <div class="scene__markers">
      @foreach ($spots as $s)
        <button @class(['spot', 'spot--marker' => $marker && ! $project]) type="button" data-spot="{{ $s['key'] }}" tabindex="-1" hidden>
          <span class="spot__dot">
            @if ($marker && ! $project)
              <x-project.marker :marker="$marker" />
              <span class="spot__no">{{ $s['number'] ?? $loop->iteration }}</span>
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
        {{-- Rasio frame --}}
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
      <!-- Kartu gambar hotspot -->
      <figure class="scene__card" data-scene-card data-scene-avoid @unless ($first['content']['image']) hidden @endunless>
        <img class="scene__card-img" src="{{ $first['content']['image'] }}" alt="{{ $first['content']['title'] }}" />
      </figure>
    @endif

    @if ($zoom)
      <!-- Gambar titik terbuka -->
      <figure class="scene__place-figure" data-place-figure hidden>
        <img alt="" />
      </figure>
    @endif

    <!-- Tampilan lokasi -->
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
