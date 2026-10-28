// Parallax hero tipe
import { coverParallax } from './cover-parallax.js';

const hero = document.querySelector('.tph');
if (hero && hero.nextElementSibling && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  coverParallax(hero, hero);
}
