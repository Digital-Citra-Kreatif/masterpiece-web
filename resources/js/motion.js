// Transisi masuk
import { gsap } from 'gsap';
import { SplitText } from 'gsap/SplitText';

// Judul per kata
const HEADINGS = [
  'h2.display:not(.mp__title, .scene__area-title, .ov__info-title, .pj-sheet__name)',
  '.prj__title',
  '.card__name',
  '.tps__size',
].join(', ');
// Paragraf per baris
const TEXTS = [
  '.sheet__intro',
  '.pj-loc__intro',
  '.units__intro',
  '.prj__desc',
  '.ft__text',
  // Halaman type
  '.tps__label',
  '.tps__mark',
  '.tps__intro p',
  '.tps__uses-title',
  '.tps__uses li',
  '.tps__specs :is(dt, dd)',
  '.tpb__list :is(dt, dd)',
  '.tpb__note',
].join(', ');
const IMAGES = '.prj__frame img, .card__img img, .fac__img img, .pj-band img, .tps__stage--image img, .tpd__plan img';
// Karusel muncul bersama
const GROUPS = '.fac__rail';
const STEP = 0.12;
const MAX_STEP = 5;

if (!matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
  gsap.registerPlugin(SplitText);

  // Target pantau
  const targets = new Map();
  const add = (root, item) => {
    if (!targets.has(root)) targets.set(root, []);
    targets.get(root).push(item);
  };

  for (const el of document.querySelectorAll(HEADINGS)) {
    if (!el.textContent.trim()) continue;
    const { words } = SplitText.create(el, { type: 'words', wordsClass: 'word-in', tag: 'span' });
    words.forEach((word, index) => word.style.setProperty('--i', index));
    // Tahan animasi
    el.classList.add('mo-words', 'mo-wait');
    add(el, { el, heading: true });
  }

  for (const el of document.querySelectorAll(TEXTS)) {
    if (!el.textContent.trim()) continue;
    const item = { el, shown: false, split: null };
    // Pecah ulang
    item.split = SplitText.create(el, {
      type: 'lines',
      mask: 'lines',
      tag: 'span',
      linesClass: 'mo-line',
      autoSplit: true,
      onSplit: (self) => gsap.set(self.lines, { yPercent: item.shown ? 0 : 100 }),
    });
    add(el, item);
  }

  for (const img of document.querySelectorAll(IMAGES)) {
    const frame = img.parentElement;
    const cover = document.createElement('span');
    cover.className = 'mo-cover';
    cover.setAttribute('aria-hidden', 'true');
    frame.classList.add('mo-frame');
    frame.append(cover);
    img.classList.add('mo-img');
    // Pantau bingkai
    add(img.closest(GROUPS) ?? frame, { el: img, cover });
  }

  /** Overlay naik lalu lepas */
  const reveal = ({ el, cover }, delay) => {
    gsap
      .timeline({ delay, onComplete: () => cover.remove() })
      .fromTo(cover, { scaleY: 0, transformOrigin: '50% 100%' }, { scaleY: 1, duration: 0.55, ease: 'power3.inOut' })
      .add(() => el.classList.add('is-in'))
      .set(cover, { transformOrigin: '50% 0%' })
      .to(cover, { scaleY: 0, duration: 0.75, ease: 'power3.inOut' });
  };

  const io = new IntersectionObserver(
    (entries) => {
      // Jeda bertahap
      let step = 0;
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        io.unobserve(entry.target);
        for (const item of targets.get(entry.target) ?? []) {
          const delay = Math.min(step, MAX_STEP) * STEP;
          step++;
          if (item.heading) {
            item.el.classList.remove('mo-wait');
          } else if (item.split) {
            item.shown = true;
            gsap.to(item.split.lines, { yPercent: 0, duration: 1.1, delay, stagger: 0.08, ease: 'expo.out' });
          } else {
            reveal(item, delay);
          }
        }
      }
    },
    { rootMargin: '0px 0px -10% 0px' },
  );

  // Decode awal
  const near = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        near.unobserve(entry.target);
        for (const { el } of targets.get(entry.target) ?? []) {
          if (el.tagName !== 'IMG') continue;
          el.loading = 'eager';
          el.decode?.().catch(() => {});
        }
      }
    },
    { rootMargin: '100% 0px' },
  );

  targets.forEach((items, root) => {
    io.observe(root);
    if (items.some(({ el }) => el.tagName === 'IMG')) near.observe(root);
  });

  // Deskripsi 360
  const lineSplit = (el) => SplitText.create(el, { type: 'lines', mask: 'lines', tag: 'span', linesClass: 'mo-line' });
  const rise = (lines) => gsap.fromTo(lines, { yPercent: 100 }, { yPercent: 0, duration: 1, stagger: 0.07, ease: 'expo.out' });
  /** Sekali saat terlihat */
  const whenSeen = (el, run) => {
    const seen = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return;
      seen.disconnect();
      run();
    });
    seen.observe(el);
  };
  /** Pecah ulang saat lebar berubah */
  const onResize = (run) => {
    let timer;
    let width = innerWidth;
    addEventListener('resize', () => {
      if (innerWidth === width) return;
      width = innerWidth;
      clearTimeout(timer);
      timer = setTimeout(run, 200);
    });
  };

  // Teks diganti skrip
  for (const el of document.querySelectorAll('[data-spin-text]')) {
    let shown = '';
    let ready = false;
    const watcher = new MutationObserver(() => ready && el.textContent !== shown && play(true));
    const play = (animate) => {
      watcher.disconnect();
      shown = el.textContent;
      if (!el.hidden && shown.trim()) {
        el.textContent = shown;
        const { lines } = lineSplit(el);
        if (animate) rise(lines);
      }
      watcher.observe(el, { childList: true });
    };
    whenSeen(el, () => {
      ready = true;
      play(true);
    });
    onResize(() => ready && play(false));
  }

  // Paragraf per tab
  for (const tabs of document.querySelectorAll('.tph__tabs')) {
    const active = () => tabs.querySelector('.tph__tab[aria-pressed="true"] + .tph__desc');
    if (!active()) continue;
    const splits = new Map();
    let shown = null;
    let ready = false;
    const play = (animate) => {
      const desc = active();
      if (!desc) return;
      shown = desc;
      splits.get(desc)?.revert();
      const split = lineSplit(desc);
      splits.set(desc, split);
      if (animate) rise(split.lines);
    };
    new MutationObserver(() => ready && active() !== shown && play(true)).observe(tabs, { attributes: true, attributeFilter: ['aria-pressed'], subtree: true });
    whenSeen(tabs, () => {
      ready = true;
      play(true);
    });
    onResize(() => ready && play(false));
  }
}
