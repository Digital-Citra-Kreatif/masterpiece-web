// Pemuat WebRotate

import WR360 from '@webrotate360/imagerotator';
import '@webrotate360/imagerotator/build/css/empty.css';

// URL wr360
const WR360_DIR = document.querySelector('[data-wr360]')?.dataset.wr360 ?? '/wr360';
const GRAPHICS = `${WR360_DIR}/graphics`;
// Lisensi WebRotate
const LICENSE = `${WR360_DIR}/license.lic`;
const WHEEL_PX_PER_FRAME = 24;
const KEY_STEP = 2;

const hotspotContent = new Map();

// Cek lisensi
const licensed = fetch(LICENSE)
  .then((r) => (r.ok ? r.text() : ''))
  .then((text) => text.trim().length > 0)
  .catch(() => false);
let booted = 0;

window.drHotspot = (config) => {
  const content = hotspotContent.get(config?.id);
  if (content) window.drOverlay?.open('info', content);
};

async function boot(root) {
  const order = booted++; // Klaim slot dulu
  if (!(await licensed) && order > 0) {
    root.classList.add('is-locked');
    for (const btn of root.querySelectorAll('[data-step]')) btn.disabled = true;
    console.warn(
      `[turntable] ${root.querySelector('.tt__viewer').id} not started: WebRotate 360 free tier loads one viewer per page. Add a PRO license at /wr360/license.lic.`,
    );
    return;
  }

  const stage = root.querySelector('.tt__stage');
  const viewerEl = root.querySelector('.tt__viewer');
  const progress = root.querySelector('.tt__loading');
  const needle = root.querySelector('.tt__dial');
  const deg = root.querySelector('.tt__deg');
  const spots = JSON.parse(root.dataset.hotspots || '{}');
  for (const [id, content] of Object.entries(spots)) hotspotContent.set(id, content);

  let api = null;
  let total = 1;
  let pending = 0; // Langkah tertunda
  // Seek tertunda
  let pendingGoTo = root.dataset.goto ? Number(root.dataset.goto) : null;
  let seek = 0; // Animasi seek
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

  const setAngle = (index) => {
    const d = Math.round((index / total) * 360) % 360;
    deg.textContent = `${d}°`;
    needle.style.setProperty('--deg', `${d}deg`);
  };

  const stopSeek = () => cancelAnimationFrame(seek);

  const step = (n) => {
    if (!n) return;
    stopSeek();
    if (!api) {
      pending += n;
      return;
    }
    api.images.showImageByDelta(n);
  };

  const goTo = (target) => {
    if (!api) {
      pendingGoTo = target;
      return;
    }
    stopSeek();
    const from = api.images.getCurrentImageIndex();
    let d = (((target - from) % total) + total) % total;
    if (d > total / 2) d -= total;
    if (!d) return;
    if (reduced) return api.images.showImageByIndex(((target % total) + total) % total);
    const duration = Math.min(1600, Math.max(450, Math.abs(d) * 24));
    const t0 = performance.now();
    const ease = (k) => (k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2);
    const tick = (now) => {
      const k = Math.min(1, (now - t0) / duration);
      const index = (((Math.round(from + d * ease(k)) % total) + total) % total);
      if (index !== api.images.getCurrentImageIndex()) api.images.showImageByIndex(index);
      if (k < 1) seek = requestAnimationFrame(tick);
    };
    seek = requestAnimationFrame(tick);
  };
  root.addEventListener('tt:goto', (e) => goTo(e.detail.frame));

  // Tinggi frame asli
  viewerEl.style.height = `${stage.dataset.height}px`;

  const viewer = WR360.ImageRotator.Create(viewerEl.id);
  viewer.licenseFileURL = LICENSE;
  viewer.settings.configFileURL = stage.dataset.config;
  viewer.settings.rootPath = stage.dataset.root;
  viewer.settings.graphicsPath = GRAPHICS;
  viewer.settings.responsiveBaseWidth = Number(stage.dataset.width);
  viewer.settings.responsiveMinHeight = 0;
  viewer.settings.alt = viewerEl.getAttribute('aria-label');
  viewer.settings.progressCallback = (_fs, percent) => progress.style.setProperty('--progress', `${percent}%`);
  viewer.settings.apiReadyCallback = (readyApi) => {
    api = readyApi;
    total = api.images.getTotalImageCount() || 1;
    setAngle(api.images.getCurrentImageIndex());
    api.images.onFrame((e) => setAngle(e.index.image));
    // Drag batalkan seek
    api.images.onDrag((e) => e.action === 'dragStart' && stopSeek());
    root.classList.add('is-ready');
    if (pendingGoTo !== null) {
      api.images.showImageByIndex(pendingGoTo);
      pendingGoTo = null;
    }
    step(pending);
    pending = 0;
  };
  viewer.runImageRotator();

  // Wheel horizontal
  let acc = 0;
  stage.addEventListener(
    'wheel',
    (e) => {
      const unit = e.deltaMode === 1 ? 16 : e.deltaMode === 2 ? 400 : 1;
      let dx = e.deltaX * unit;
      if (e.shiftKey && !dx) dx = e.deltaY * unit;
      if (Math.abs(dx) <= Math.abs(e.deltaY * unit) && !e.shiftKey) return;
      e.preventDefault();
      acc += dx;
      const n = Math.trunc(acc / WHEEL_PX_PER_FRAME);
      if (n) {
        acc -= n * WHEEL_PX_PER_FRAME;
        step(n);
      }
    },
    { passive: false },
  );

  viewerEl.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') step(-KEY_STEP);
    else if (e.key === 'ArrowRight') step(KEY_STEP);
    else return;
    e.preventDefault();
  });

  // Tombol langkah
  for (const btn of root.querySelectorAll('[data-step]')) {
    const dir = Number(btn.dataset.step);
    let timer = null;
    const stop = () => clearInterval(timer);
    btn.addEventListener('pointerdown', () => {
      step(dir * KEY_STEP);
      stop();
      timer = setInterval(() => step(dir), 60);
    });
    for (const ev of ['pointerup', 'pointerleave', 'pointercancel']) btn.addEventListener(ev, stop);
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        step(dir * KEY_STEP);
      }
    });
  }

  // Daftar hotspot
  for (const btn of root.querySelectorAll('[data-spot]')) {
    btn.addEventListener('click', () => window.drHotspot({ id: btn.dataset.spot }));
  }
}

const roots = [...document.querySelectorAll('[data-turntable]')];

if ('IntersectionObserver' in window) {
  const io = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        io.unobserve(entry.target);
        boot(entry.target);
      }
    },
    { rootMargin: '600px 0px' },
  );
  roots.forEach((r) => io.observe(r));
} else {
  roots.forEach(boot);
}
