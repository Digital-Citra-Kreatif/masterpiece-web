// Entri semua halaman
import './scroll-lock.js';
import './scrollbar.js';
import './reveal.js';
import './motion.js';
import { initParallax } from './parallax.js';
import { initSections } from './sections.js';
import './overlay.js';
import './protect-media.js';
import './menu.js';
import './header.js';
import './scroll-top.js';

// Hitung mundur maintenance
if (document.querySelector('[data-countdown]')) {
  import('./countdown.js');
}

// Preload dari head
if (document.documentElement.classList.contains('is-preload')) {
  import('./preload.js');
}

initParallax();
initSections();
