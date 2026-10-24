{{--
  Facilities carousel (max 10 slides): native horizontal scroll-snap with
  prev/next buttons, so touch, trackpad and keyboard all work without a library.
  Logic in resources/js/carousel.js.
--}}
@props(['facilities'])

<section id="fasilitas" class="fac" aria-labelledby="fasilitas-title">
  <div class="wrap">
    <x-sheet-header
      id="fasilitas-title"
      title="Ruang bersama"
      intro="Fasilitas yang melengkapi kawasan, dari tepi danau hingga pusat komersial."
    />
  </div>

  <div class="fac__rail" data-carousel>
    <ul class="fac__track" data-track tabindex="0" aria-label="Galeri fasilitas">
      @foreach ($facilities as $f)
        <li class="fac__slide">
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

    <div class="fac__controls wrap">
      <div class="fac__progress" aria-hidden="true"><span data-progress></span></div>
      <button class="icon-btn" type="button" data-dir="-1" aria-label="Fasilitas sebelumnya">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M15 5l-7 7 7 7"></path></svg>
      </button>
      <button class="icon-btn" type="button" data-dir="1" aria-label="Fasilitas berikutnya">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"></path></svg>
      </button>
    </div>
  </div>
</section>
