// Entri halaman proyek
import './hero.js';
import './masterplan.js';
import './scroll-scene.js';
import './facilities-swiper.js';
import './view-cursor.js';
import './lightbox.js';
import './unit-filter.js';
import './project-page.js';
import './whatsapp.js';

// WebRotate hanya bila ada 360°
if (document.querySelector('[data-parts]')) {
  import('./turntable.js');
  import('./type-parts.js');
}
