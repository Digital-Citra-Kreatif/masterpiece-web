// Entri halaman tipe
import './lightbox.js';
import './view-cursor.js';
import './whatsapp.js';

// Slider denah
if (document.querySelector('[data-plans-swiper]')) {
  import('./plans-swiper.js');
}

// WebRotate hanya bila ada 360°
if (document.querySelector('[data-parts]')) {
  import('./turntable.js');
  import('./type-parts.js');
}
