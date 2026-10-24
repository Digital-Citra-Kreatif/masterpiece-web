// Boots WebRotate 360 on every [data-turntable] when it nears the viewport.
//
// Input model (brief: "left/right rotates, vertical scroll moves the page"):
//   - drag / touch swipe: handled by WebRotate (its player uses touch-action: pan-y,
//     so vertical swipes still scroll the page on phones)
//   - wheel / trackpad: WebRotate's own wheel handling is off in config.xml; here a
//     mostly-horizontal wheel gesture (or Shift+wheel) rotates, and a vertical one
//     is left alone so the page scrolls
//   - ←/→ keys on the focused viewer, and the step buttons
//
// Licensing: WebRotate's free tier loads only the first viewer created on a page;
// later ones never start. The homepage has one (cluster), but every type page
// (facade + floors) has more than one, so the site expects a PRO/Enterprise
// license file at /wr360/license.lic. Without one, only the first viewer on the
// page boots and the others show their poster with a notice.
//
// Animated seek: dispatch `tt:goto` on the [data-turntable] element with
// { detail: { frame } } and the viewer plays through the frames in between (the
// shorter way round). Used by the type page to move between its parts.
//
// Hotspot clicks: config.xml gives each hotspot clickAction="11" clickData="drHotspot",
// which makes WebRotate call window.drHotspot(hotspotConfig).

import WR360 from '@webrotate360/imagerotator';
import '@webrotate360/imagerotator/build/css/empty.css';

// URL of public/wr360/, from asset() in the Blade component (works under a subpath).
const WR360_DIR = document.querySelector('[data-wr360]')?.dataset.wr360 ?? '/wr360';
const GRAPHICS = `${WR360_DIR}/graphics`;
// Free tier: empty file, viewer shows "powered by WebRotate 360". Replace with the
// PRO/Enterprise license file if the client buys one.
const LICENSE = `${WR360_DIR}/license.lic`;
const WHEEL_PX_PER_FRAME = 24;
const KEY_STEP = 2;

const hotspotContent = new Map();

// Empty file = free tier. (WebRotate itself validates a real license file.)
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
  const order = booted++; // claim a slot before awaiting, so simultaneous boots can't both be "first"
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
  let pending = 0; // steps requested before the viewer is ready
  // Seek requested before the viewer is ready (the page may set data-goto early).
  let pendingGoTo = root.dataset.goto ? Number(root.dataset.goto) : null;
  let seek = 0; // running seek animation (rAF id)
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

  // WebRotate reads the container height at start-up as "height at
  // responsiveBaseWidth" and scales it with the width from there, so start from
  // the native frame height rather than the already-scaled CSS height.
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
    // Grabbing the viewer takes over from a running seek.
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

  // Horizontal wheel / trackpad rotates; vertical passes through to the page.
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

  // Step buttons: tap for one notch, hold to keep turning.
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

  // Hotspot list under the viewer opens the same panels as the on-image markers.
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
