// Transisi teks dan gambar
const TITLES = 'h2.display:not(.mp__title, .scene__area-title, .ov__info-title, .pj-sheet__name)';
const INTROS = '.sheet__intro, .fac__intro, .pj-loc__intro';
const IMAGES = '.prj__frame img, .card__img img, .fac__img img, .pj-band img, .tps__stage--image img';
// Karusel muncul bersama
const GROUPS = '.fac__rail';
const STEP = 0.12;
const MAX_STEP = 5;

/** Pecah judul per kata */
const split = (title) => {
  const words = title.textContent.trim().split(/\s+/);
  title.textContent = '';
  words.forEach((word, index) => {
    if (index) title.append(' ');
    const mask = document.createElement('span');
    const inner = document.createElement('span');
    mask.className = 'mo-word';
    inner.textContent = word;
    inner.style.setProperty('--i', index);
    mask.append(inner);
    title.append(mask);
  });
};

if (!matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
  // Target per elemen pantau
  const targets = new Map();
  const add = (root, el) => {
    if (!targets.has(root)) targets.set(root, []);
    targets.get(root).push(el);
  };

  for (const title of document.querySelectorAll(TITLES)) {
    if (title.children.length || !title.textContent.trim()) continue;
    split(title);
    title.classList.add('mo-text');
    add(title, title);
  }

  for (const intro of document.querySelectorAll(INTROS)) {
    intro.classList.add('mo-fade');
    add(intro, intro);
  }

  for (const img of document.querySelectorAll(IMAGES)) {
    img.classList.add('mo-img');
    add(img.closest(GROUPS) ?? img, img);
  }

  const io = new IntersectionObserver(
    (entries) => {
      // Jeda bertahap per gelombang
      let step = 0;
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        io.unobserve(entry.target);
        for (const el of targets.get(entry.target) ?? []) {
          const delay = Math.min(step, MAX_STEP) * STEP;
          if (el.classList.contains('mo-img')) step++;
          el.style.setProperty('--mo-delay', `${delay}s`);
          el.classList.add('is-in');
        }
      }
    },
    { rootMargin: '0px 0px -10% 0px' },
  );

  targets.forEach((_, root) => io.observe(root));
}
