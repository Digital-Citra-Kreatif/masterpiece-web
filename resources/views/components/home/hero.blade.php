{{--
  The hero banner: a full-screen loop of the aerial orbit, or slides that take
  turns behind the headline, the lead and the "Play Full Video" card. The
  Masterplan section follows it.

  hero: { title, subtitle, background: 'video'|'slider', slides: [{ type, src, alt }], teaser, full, cover, fullThumb }

  Logic in resources/js/hero.js.
--}}
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
  @else
    <video
      class="hero__video"
      muted
      loop
      playsinline
      preload="auto"
      poster="{{ asset($hero['cover']) }}"
      data-src="{{ asset($hero['teaser']) }}"
      aria-hidden="true"
    ></video>
  @endif
  <div class="hero__shade" aria-hidden="true"></div>

  <div class="hero__body wrap">
    <h1 class="hero__title" id="hero-title">{!! nl2br(e($hero['title']), false) !!}</h1>
    <div class="hero__foot">
      <span class="hero__rule" aria-hidden="true"></span>
      <p class="hero__lead">{!! nl2br(e($hero['subtitle']), false) !!}</p>
      <button
        class="hero__play"
        type="button"
        data-open="video"
        data-payload="{{ json_encode(['src' => asset($hero['full']), 'title' => $site['fullName']]) }}"
      >
        <img class="hero__play-thumb" src="{{ asset($hero['fullThumb']) }}" alt="" width="98" height="98" />
        <span class="hero__play-label">Play Full Video</span>
        <span class="hero__play-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M9 6.5v11l9-5.5z" fill="currentColor" /></svg>
        </span>
      </button>
    </div>
  </div>
</section>
