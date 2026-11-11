// Transisi teks dan gambar
import { gsap } from 'gsap';
import { SplitText } from 'gsap/SplitText';

const TEXTS = [
  'h2.display:not(.mp__title, .scene__area-title, .ov__info-title, .pj-sheet__name)',
  '.prj__title',
  '.card__name',
  '.sheet__intro',
  '.fac__intro',
  '.pj-loc__intro',
  '.units__intro',
  '.prj__desc',
  '.ft__text',
].join(', ');
const IMAGES = '.prj__frame img, .card__img img, .fac__img img, .pj-band img, .tps__stage--image img';
// Karusel muncul bersama
const GROUPS = '.fac__rail';
const STEP = 0.12;
const MAX_STEP = 5;

if (!matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
  gsap.registerPlugin(SplitText);

  // Target per elemen pantau
  const targets = new Map();
  const add = (root, item) => {
    if (!targets.has(root)) targets.set(root, []);
    targets.get(root).push(item);
  };

  for (const el of document.querySelectorAll(TEXTS)) {
    if (!el.textContent.trim()) continue;
    const item = { el, shown: false, split: null };
    // Pecah ulang saat lebar berubah
    item.split = SplitText.create(el, {
      type: 'lines',
      mask: 'lines',
      linesClass: 'mo-line',
      autoSplit: true,
      onSplit: (self) => gsap.set(self.lines, { yPercent: item.shown ? 0 : 100 }),
    });
    add(el, item);
  }

  for (const img of document.querySelectorAll(IMAGES)) {
    img.classList.add('mo-img');
    // Pantau bingkai, bukan gambar terpotong
    add(img.closest(GROUPS) ?? img.parentElement, { el: img });
  }

  const io = new IntersectionObserver(
    (entries) => {
      // Jeda bertahap per gelombang
      let step = 0;
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        io.unobserve(entry.target);
        for (const item of targets.get(entry.target) ?? []) {
          const delay = Math.min(step, MAX_STEP) * STEP;
          step++;
          if (item.split) {
            item.shown = true;
            gsap.to(item.split.lines, { yPercent: 0, duration: 1.1, delay, stagger: 0.08, ease: 'expo.out' });
          } else {
            item.el.style.setProperty('--mo-delay', `${delay}s`);
            item.el.classList.add('is-in');
          }
        }
      }
    },
    { rootMargin: '0px 0px -10% 0px' },
  );

  targets.forEach((_, root) => io.observe(root));
}
