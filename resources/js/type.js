// Entri halaman tipe
import './lightbox.js';
import './view-cursor.js';
import './whatsapp.js';
import './hero-parallax.js';

// Parallax Spesifikasi tertutup
const covered = document.querySelector('#tipe-lain')?.previousElementSibling;
if (covered && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  import('./cover-parallax.js').then(({ coverParallax }) => coverParallax(covered, covered, { hold: true }));
}

// Slider denah
if (document.querySelector('[data-plans-swiper]')) {
  import('./plans-swiper.js');
}

// 360° bila ada
if (document.querySelector('[data-spin]')) {
  import('./spin-scene.js');
}
