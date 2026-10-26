// Karusel fasilitas
import Swiper from 'swiper';
import { A11y, Keyboard, Navigation } from 'swiper/modules';
import 'swiper/css';

for (const el of document.querySelectorAll('[data-fac-swiper]')) {
  const section = el.closest('section');
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
  });
}
