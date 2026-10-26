// Facilities carousel, 'split' variant (components/home/facilities): Swiper, so the
// cards drag with the pointer and the square arrows and the keyboard step through them.
import Swiper from 'swiper';
import { A11y, Keyboard, Navigation } from 'swiper/modules';
import 'swiper/css';

for (const el of document.querySelectorAll('[data-fac-swiper]')) {
  const section = el.closest('section');
  new Swiper(el, {
    modules: [A11y, Keyboard, Navigation],
    slidesPerView: 'auto',
    spaceBetween: 16,
    grabCursor: true,
    watchOverflow: true,
    keyboard: { enabled: true, onlyInViewport: true },
    navigation: { prevEl: section.querySelector('[data-fac-prev]'), nextEl: section.querySelector('[data-fac-next]') },
    a11y: { prevSlideMessage: 'Fasilitas sebelumnya', nextSlideMessage: 'Fasilitas berikutnya' },
  });
}
