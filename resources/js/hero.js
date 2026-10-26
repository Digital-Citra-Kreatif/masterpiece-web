// Hero: the looping teaser video, or the slider. Both run while on screen and
// stay still (poster, first slide) for visitors who prefer reduced motion.
import { release, resume } from './video-hold.js';

const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const onScreen = (el, cb) => new IntersectionObserver(([e]) => cb(e.isIntersecting)).observe(el);

for (const video of document.querySelectorAll('.hero__video')) {
  video.src = video.dataset.src;
  if (reduced) continue;
  onScreen(video, (visible) => {
    if (visible) {
      resume(video);
      video.play().catch(() => {});
    } else {
      release(video);
    }
  });
}

// An image slide shows for 6s; a video slide until it ends. A lone video loops.
const IMAGE_MS = 6000;

for (const root of document.querySelectorAll('[data-hero-slides]')) {
  const slides = [...root.children];
  const videoOf = (slide) => slide.querySelector('video');
  let current = 0;
  let visible = false;
  let timer;

  for (const slide of slides) {
    const video = videoOf(slide);
    if (!video) continue;
    video.loop = slides.length === 1;
    video.addEventListener('ended', () => show(current + 1));
  }

  const run = () => {
    clearTimeout(timer);
    const video = videoOf(slides[current]);
    if (video) {
      if (visible) resume(video);
      if (visible && !reduced) video.play().catch(() => {});
      else if (!visible) release(video);
      else video.pause();
    } else if (visible && !reduced && slides.length > 1) {
      timer = setTimeout(() => show(current + 1), IMAGE_MS);
    }
  };

  const show = (index) => {
    const previous = slides[current];
    current = index % slides.length;
    if (previous !== slides[current]) {
      previous.classList.remove('is-active');
      previous.setAttribute('aria-hidden', 'true');
      const video = videoOf(previous);
      if (video) video.pause();
      slides[current].classList.add('is-active');
      slides[current].removeAttribute('aria-hidden');
      const next = videoOf(slides[current]);
      if (next) next.currentTime = 0;
    }
    run();
  };

  // Watched on the hero itself: the slides' box has no height of its own (the
  // slides are absolute), so it left the screen as soon as the page scrolled.
  onScreen(root.closest('.hero') ?? root, (isVisible) => {
    visible = isVisible;
    run();
  });
}
