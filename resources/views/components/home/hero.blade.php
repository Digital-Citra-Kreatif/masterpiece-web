{{-- Banner hero --}}
@props(['hero'])

{{-- Posisi konten CMS --}}
@php([$alignY, $alignX] = ($hero['align'] ?? 'split') === 'split' ? [null, null] : explode('-', $hero['align']))

<section @class(['hero', 'hero--placed' => $alignY, "hero--y-{$alignY}" => $alignY, "hero--x-{$alignX}" => $alignX]) @if (filled($hero['id'] ?? null)) id="{{ $hero['id'] }}" @endif aria-labelledby="hero-title">
  @if ($hero['background'] === 'slider')
    <div class="hero__slides" data-hero-slides>
      @foreach ($hero['slides'] as $slide)
        <div class="hero__slide{{ $loop->first ? ' is-active' : '' }}" @unless ($loop->first) aria-hidden="true" @endunless>
          @if ($slide['type'] === 'image')
            <img
              class="hero__media"
              src="{{ asset($slide['src']) }}"
              alt="{{ $slide['alt'] }}"
              decoding="async"
              @if ($loop->first) fetchpriority="high" @endif
            />
          @else
            <video class="hero__media" muted playsinline preload="{{ $loop->first ? 'auto' : 'none' }}" data-src="{{ asset($slide['src']) }}" aria-hidden="true"></video>
          @endif
        </div>
      @endforeach
    </div>
  @elseif ($hero['background'] === 'video')
    <video
      class="hero__video"
      muted
      loop
      playsinline
      preload="auto"
      @if (filled($hero['cover'])) poster="{{ asset($hero['cover']) }}" @endif
      data-src="{{ asset($hero['teaser']) }}"
      aria-hidden="true"
    ></video>
  @endif
  <div class="hero__shade" aria-hidden="true"></div>

  <div class="hero__body wrap">
    @if (filled($hero['eyebrow'] ?? null))
      <p class="hero__eyebrow">{{ $hero['eyebrow'] }}</p>
    @endif
    {{-- Animasi per kata --}}
    @php($word = 0)
    <h1 class="hero__title" id="hero-title">
      @foreach (preg_split('/\R/', (string) $hero['title']) as $line)
        @if (! $loop->first)<br>@endif
        @foreach (preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY) as $text)
          <span class="hero__word" style="--i: {{ $word++ }}">{{ $text }}</span>
        @endforeach
      @endforeach
    </h1>
    @if ($hero['video'] ?? null)
      {{-- Tombol video popup --}}
      <div class="hero__action">
        <button class="btn btn--light hero__play" type="button" data-open="{{ $hero['video']['kind'] }}" data-payload="{{ json_encode(['src' => $hero['video']['src'], 'title' => $hero['title']]) }}" data-framed>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M10 8.5v7l6-3.5z" fill="currentColor" stroke="none" /></svg>
          Putar video
        </button>
      </div>
    @elseif (filled($hero['cue'] ?? null))
      <p class="hero__cue" aria-hidden="true">{{ $hero['cue'] }}</p>
    @endif
    @if (filled($hero['subtitle']) || filled($hero['scrollTo'] ?? null))
    <div class="hero__foot">
      @if (filled($hero['scrollTo'] ?? null))
        <a class="hero__scroll" href="{{ $hero['scrollTo'] }}" aria-label="Gulir ke bagian berikutnya">
          <svg viewBox="0 0 16 64" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><path d="M8 2v59M2 55l6 6 6-6" /></svg>
        </a>
      @endif
      @if (filled($hero['subtitle']))
        <p class="hero__lead">{!! nl2br(e($hero['subtitle']), false) !!}</p>
      @endif
    </div>
    @endif
  </div>
</section>
