// Entri halaman tipe
import './lightbox.js';
import './view-cursor.js';
import './whatsapp.js';

// Parallax Spesifikasi, ditutup Tipe lain
const covered = document.querySelector('#tipe-lain')?.previousElementSibling;
if (covered && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  import('./cover-parallax.js').then(({ coverParallax }) => coverParallax(covered, covered));
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
