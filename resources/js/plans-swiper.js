// Slider denah
import Swiper from 'swiper';
import { A11y, Keyboard, Navigation } from 'swiper/modules';
import 'swiper/css';

for (const el of document.querySelectorAll('[data-plans-swiper]')) {
  const section = el.closest('section');
  new Swiper(el, {
    modules: [A11y, Keyboard, Navigation],
    slidesPerView: 1,
    grabCursor: true,
    autoHeight: true,
    keyboard: { enabled: true, onlyInViewport: true },
    navigation: { prevEl: section.querySelector('[data-plans-prev]'), nextEl: section.querySelector('[data-plans-next]') },
    a11y: { prevSlideMessage: 'Denah sebelumnya', nextSlideMessage: 'Denah berikutnya' },
  });
}
