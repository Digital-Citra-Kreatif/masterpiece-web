// Karusel fasilitas
import Swiper from 'swiper';
import { A11y, Keyboard, Navigation } from 'swiper/modules';
import 'swiper/css';

for (const el of document.querySelectorAll('[data-fac-swiper]')) {
  const section = el.closest('section');
  const bar = section.querySelector('[data-fac-progress]');
  // Posisi di deretan
  const progress = (swiper) => {
    if (!bar || !swiper.slides.length) return;
    const start = 1 / swiper.slides.length;
    const at = swiper.isLocked ? 1 : start + (1 - start) * Math.min(1, Math.max(0, swiper.progress));
    bar.style.transform = `scaleX(${at.toFixed(4)})`;
  };

  new Swiper(el, {
    modules: [A11y, Keyboard, Navigation],
    // Lebar kartu sendiri
    slidesPerView: 'auto',
    spaceBetween: 16,
    grabCursor: true,
    keyboard: { enabled: true, onlyInViewport: true },
    navigation: { prevEl: section.querySelector('[data-fac-prev]'), nextEl: section.querySelector('[data-fac-next]') },
    a11y: { prevSlideMessage: 'Fasilitas sebelumnya', nextSlideMessage: 'Fasilitas berikutnya' },
    on: { init: progress, setTranslate: progress, resize: progress },
  });
}
