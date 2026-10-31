// Entri semua halaman
import './smooth-scroll.js';
import './scrollbar.js';
import './reveal.js';
import './overlay.js';
import './protect-media.js';
import './menu.js';
import './scroll-top.js';

// Hitung mundur maintenance
if (document.querySelector('[data-countdown]')) {
  import('./countdown.js');
}

// Preload dari head
if (document.documentElement.classList.contains('is-preload')) {
  import('./preload.js');
}
