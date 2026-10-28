{{-- Overlay dialog --}}
<dialog class="ov" id="overlay" aria-labelledby="ov-title">
  <div class="ov__bar">
    <p class="ov__title eyebrow" id="ov-title"></p>
    <div class="ov__tools">
      <span class="ov__count eyebrow" hidden></span>
      <a class="btn btn--light ov__ext" target="_blank" rel="noopener" hidden>Buka di tab baru</a>
      <button class="icon-btn ov__close" type="button" aria-label="Tutup">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 5l14 14M19 5L5 19"></path></svg>
      </button>
    </div>
  </div>

  <div class="ov__body">
    <!-- Video -->
    <video class="ov__video" controls playsinline preload="none" @if ($site['protectMedia']) controlslist="nodownload" disablepictureinpicture @endif hidden></video>

    <!-- Galeri -->
    <figure class="ov__gallery" hidden>
      <img class="ov__img" alt="" />
      <figcaption class="ov__caption"></figcaption>
      <button class="icon-btn ov__prev" type="button" aria-label="Sebelumnya">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M15 5l-7 7 7 7"></path></svg>
      </button>
      <button class="icon-btn ov__next" type="button" aria-label="Berikutnya">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"></path></svg>
      </button>
    </figure>

    <!-- Tur VR -->
    <iframe class="ov__frame" title="" allow="fullscreen; xr-spatial-tracking; gyroscope; accelerometer" hidden></iframe>

    <!-- Info hotspot -->
    <article class="ov__info" hidden>
      <img class="ov__info-img" alt="" />
      <div class="ov__info-text">
        <h2 class="display ov__info-title"></h2>
        <p class="ov__info-body"></p>
      </div>
    </article>
  </div>
</dialog>
