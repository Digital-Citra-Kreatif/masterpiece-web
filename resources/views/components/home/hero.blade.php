{{-- Banner hero --}}
@props(['hero'])

<section class="hero" aria-labelledby="hero-title">
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
    {{-- Per kata untuk animasi --}}
    @php($word = 0)
    <h1 class="hero__title" id="hero-title">
      @foreach (preg_split('/\R/', (string) $hero['title']) as $line)
        @if (! $loop->first)<br>@endif
        @foreach (preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY) as $text)
          <span class="hero__word" style="--i: {{ $word++ }}">{{ $text }}</span>
        @endforeach
      @endforeach
    </h1>
    @if (filled($hero['cue'] ?? null))
      <p class="hero__cue" aria-hidden="true">{{ $hero['cue'] }}</p>
    @endif
    @if (filled($hero['subtitle']))
    <div class="hero__foot">
      <p class="hero__lead">{!! nl2br(e($hero['subtitle']), false) !!}</p>
    </div>
    @endif
  </div>
</section>
