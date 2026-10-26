// Facilities carousel, 'split' variant (components/home/facilities): Swiper, looping,
// so the cards drag with the pointer and the square arrows and the keyboard step
// through them round and round. A card's picture opens the lightbox (lightbox.js).
// A drag that ends on a card doesn't open it (Swiper stops that click).
import Swiper from 'swiper';
import { A11y, Keyboard, Navigation } from 'swiper/modules';
import 'swiper/css';

for (const el of document.querySelectorAll('[data-fac-swiper]')) {
  const section = el.closest('section');
  new Swiper(el, {
    modules: [A11y, Keyboard, Navigation],
    // Whole cards only, filling the column edge to edge: never one cut off at the right.
    slidesPerView: 1.15,
    spaceBetween: 8,
    breakpoints: {
      561: { slidesPerView: 2 },
      961: { slidesPerView: 3 },
    },
    grabCursor: true,
    loop: true,
    keyboard: { enabled: true, onlyInViewport: true },
    navigation: { prevEl: section.querySelector('[data-fac-prev]'), nextEl: section.querySelector('[data-fac-next]') },
    a11y: { prevSlideMessage: 'Fasilitas sebelumnya', nextSlideMessage: 'Fasilitas berikutnya' },
  });
}
