{{--
  Facilities carousel (max 10 slides): native horizontal scroll-snap with
  prev/next buttons, so touch, trackpad and keyboard all work without a library.
  Logic in resources/js/carousel.js.

  variant 'split' (project pages, after design-project.pdf): the title, its intro
  and two square arrows in a narrow left column, the cards running off to the
  right in a Swiper carousel (resources/js/facilities-swiper.js: drag, arrows, keys).
--}}
@props(['facilities', 'heading', 'id' => 'fasilitas', 'variant' => 'default'])

@php($split = $variant === 'split')

<section id="{{ $id }}" @class(['fac', 'fac--split' => $split]) aria-labelledby="{{ $id }}-title">
  <div @class(['wrap', 'fac__split' => $split])>
    @if ($split)
      <div class="fac__head">
        <h2 class="display fac__title" id="{{ $id }}-title">{{ $heading['title'] }}</h2>
        @if ($heading['intro'])
          <p class="fac__intro">{{ $heading['intro'] }}</p>
        @endif
        <div class="fac__arrows">
          <button class="fac__arrow" type="button" data-fac-prev aria-label="Fasilitas sebelumnya">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M15 5l-7 7 7 7"></path></svg>
          </button>
          <button class="fac__arrow" type="button" data-fac-next aria-label="Fasilitas berikutnya">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 5l7 7-7 7"></path></svg>
          </button>
        </div>
      </div>
    @else
      <x-sheet-header
        id="{{ $id }}-title"
        :title="$heading['title']"
        :intro="$heading['intro']"
      />
    @endif

  @unless ($split)
  </div>
  @endunless

  <div @class(['fac__rail', 'swiper' => $split]) @if ($split) data-fac-swiper @else data-carousel @endif>
    <ul @class(['fac__track', 'swiper-wrapper' => $split]) @unless ($split) data-track tabindex="0" @endunless aria-label="Galeri fasilitas">
      @foreach ($facilities as $f)
        <li @class(['fac__slide', 'swiper-slide' => $split])>
          <figure>
            <div class="fac__img">
              <img src="{{ asset($f['image']) }}" alt="{{ $f['title'] }}" loading="lazy" />
              @if ($site['showPlaceholderBadges'] && $loop->first)
                <span class="badge fac__badge">Gambar contoh</span>
              @endif
            </div>
            <figcaption>
              {{ $f['title'] }}
            </figcaption>
          </figure>
        </li>
      @endforeach
    </ul>

    @unless ($split)
      <div class="fac__controls wrap">
        <div class="fac__progress" aria-hidden="true"><span data-progress></span></div>
        <button class="icon-btn" type="button" data-dir="-1" aria-label="Fasilitas sebelumnya">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M15 5l-7 7 7 7"></path></svg>
        </button>
        <button class="icon-btn" type="button" data-dir="1" aria-label="Fasilitas berikutnya">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"></path></svg>
        </button>
      </div>
    @endunless
  </div>

  @if ($split)
  </div>
  @endif
</section>
