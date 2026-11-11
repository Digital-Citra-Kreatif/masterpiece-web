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

// Latar hitam melebar
const spinSection = document.querySelector('.tps--spin');
if (spinSection) {
  let queued = false;
  const open = () => {
    queued = false;
    const y = spinSection.getBoundingClientRect().top;
    const span = Math.max(1, Math.min(y + window.scrollY, window.innerHeight * 0.5)); // Dari posisi awal
    spinSection.style.setProperty('--spin-open', Math.min(1, Math.max(0, 1 - y / span)).toFixed(3));
  };
  const request = () => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(open);
  };
  open();
  window.addEventListener('scroll', request, { passive: true });
  window.addEventListener('resize', request);
}
