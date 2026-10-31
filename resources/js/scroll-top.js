// Tombol ke atas
import { lenis } from './smooth-scroll.js';

const button = document.querySelector('[data-to-top]');
if (button) {
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  // Muncul setelah selayar
  const update = (y) => button.classList.toggle('is-visible', y > innerHeight);
  lenis.on('scroll', ({ scroll }) => update(scroll));
  update(scrollY);

  button.addEventListener('click', () => {
    lenis.scrollTo(0, { immediate: reduced.matches, duration: 1.2 });
    // Fokus ke awal
    document.querySelector('header a, main')?.focus({ preventScroll: true });
  });
}
