// Karusel fasilitas
import Swiper from 'swiper';
import { A11y, Keyboard, Navigation } from 'swiper/modules';
import 'swiper/css';

const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

for (const el of document.querySelectorAll('[data-fac-swiper]')) {
  const section = el.closest('section');
  const bar = section.querySelector('[data-fac-progress]');
  const count = Number(el.dataset.facCount) || 1;
  // Geser isian
  const place = (x, transition = '') => {
    bar.style.transition = transition;
    bar.style.transform = `translateX(${x}%)`;
  };
  const at = (index) => -(1 - (index + 1) / count) * 100;
  let last = null;
  let run = 0;
  /** Tembus tepi lalu masuk lagi */
  const wrap = (out, from, to) => {
    const turn = ++run;
    place(out, 'transform 0.45s cubic-bezier(0.5, 0, 0.75, 0)');
    bar.addEventListener('transitionend', () => {
      if (turn !== run) return;
      place(from, 'none');
      bar.offsetWidth;
      place(to, 'transform 0.7s cubic-bezier(0.16, 1, 0.3, 1)');
    }, { once: true });
  };
  // Kartu ke berapa
  const progress = (swiper) => {
    if (!bar) return;
    const index = swiper.realIndex % count;
    if (last === null || count < 2 || reduced) {
      place(at(index), 'none');
    } else if (last === count - 1 && index === 0) {
      wrap(100, -100, at(index));
    } else if (last === 0 && index === count - 1) {
      wrap(-100, 100, at(index));
    } else if (index !== last) {
      run++;
      place(at(index));
    }
    last = index;
  };

  new Swiper(el, {
    modules: [A11y, Keyboard, Navigation],
    // Lebar kartu sendiri
    slidesPerView: 'auto',
    spaceBetween: 16,
    grabCursor: true,
    loop: true,
    keyboard: { enabled: true, onlyInViewport: true },
    navigation: { prevEl: section.querySelector('[data-fac-prev]'), nextEl: section.querySelector('[data-fac-next]') },
    a11y: { prevSlideMessage: 'Fasilitas sebelumnya', nextSlideMessage: 'Fasilitas berikutnya' },
    on: { init: progress, realIndexChange: progress },
  });
}
