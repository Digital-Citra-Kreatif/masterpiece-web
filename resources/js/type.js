// Entri halaman tipe
import './lightbox.js';
import './view-cursor.js';
import './whatsapp.js';

// Parallax Tipe lain, ditutup footer
const others = document.querySelector('#tipe-lain');
if (others && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  import('./cover-parallax.js').then(({ coverParallax }) => coverParallax(others, others));
}

// Slider denah
if (document.querySelector('[data-plans-swiper]')) {
  import('./plans-swiper.js');
}

// WebRotate hanya bila ada 360°
if (document.querySelector('[data-parts]')) {
  import('./turntable.js');
  import('./type-parts.js');
}
