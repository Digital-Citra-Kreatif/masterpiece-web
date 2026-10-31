// Scene scroll

const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const size = Math.min(window.innerWidth, 1600) * Math.min(devicePixelRatio || 1, 2) > 1100 ? 'lg' : 'sm';
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
const ramp = (v, a, b) => clamp((v - a) / (b - a), 0, 1);
// Ramp halus
const smooth = (v, a, b) => {
  const t = ramp(v, a, b);
  return t * t * (3 - 2 * t);
};

import { release, resume } from './video-hold.js';

// Set frame
const sets = new Map();

function frameSet(base) {
  if (sets.has(base)) return sets.get(base);
  const listeners = new Set();
  const set = {
    listeners,
    ready: fetch(`${base}manifest.json`)
      .then((r) => r.json())
      .then((m) => {
        const N = m.count;
        set.m = m;
        set.frames = new Array(N).fill(null);
        const order = [];
        for (let stride = 16; stride >= 1; stride /= 2) {
          for (let i = 0; i < N; i += stride) if (!order.includes(i)) order.push(i);
        }
        let next = 0;
        const loadOne = () => {
          if (next >= order.length) return;
          const i = order[next++];
          const img = new Image();
          img.decoding = 'async';
          // Decode luar thread
          img.onload = () => img.decode().catch(() => {}).then(() => {
            set.frames[i] = img;
            listeners.forEach((fn) => fn());
            loadOne();
          });
          img.onerror = loadOne;
          img.src = base + m.pattern.replace('{size}', size).replace('{n}', String(i).padStart(3, '0'));
        };
        for (let k = 0; k < 6; k++) loadOne();
        // Frame detail opsional
        set.detail = new Map();
        set.loadDetail = () => {
          if (set.detailRequested || !m.detail) return;
          set.detailRequested = true;
          for (const f of m.detail.frames) {
            const img = new Image();
            img.decoding = 'async';
            img.onload = () => img.decode().catch(() => {}).then(() => {
              set.detail.set(f, img);
              listeners.forEach((fn) => fn());
            });
            img.src = base + m.detail.pattern.replace('{n}', String(f).padStart(3, '0'));
          }
        };
        return set;
      }),
    // Frame termuat terdekat
    nearestIndex(i) {
      const { frames } = set;
      const N = frames.length;
      for (let d = 0; d < N; d++) {
        if (frames[(i + d) % N]) return (i + d) % N;
        if (frames[(i - d + N) % N]) return (i - d + N) % N;
      }
      return -1;
    },
  };
  sets.set(base, set);
  return set;
}

// Titik ditempatkan
function placePoints(m, spots) {
  for (const s of Object.values(spots)) {
    if (!s.point || m.hotspots[s.track]) continue;
    const p = [(s.point[0] / 100) * m.width, (s.point[1] / 100) * m.height];
    m.hotspots[s.track] = new Array(m.count).fill(p);
  }
}

// Gambar diam
const STILL_FRAMES = 120;

function stillSet(src) {
  const set = {
    listeners: new Set(),
    ready: new Promise((resolve) => {
      const img = new Image();
      img.decoding = 'async';
      img.onload = () => {
        set.m = { count: STILL_FRAMES, width: img.naturalWidth, height: img.naturalHeight, step: 1, hotspots: {} };
        set.frames = new Array(STILL_FRAMES).fill(img);
        set.still = img;
        resolve(set);
      };
      img.src = src;
    }),
    nearestIndex: (i) => i,
  };
  return set;
}

// Satu scene
async function init(root) {
  const stage = root.querySelector('.scene__stage');
  const introEnd = root.querySelector('[data-intro-end]');
  const video = root.querySelector('.scene__video');
  const canvas = root.querySelector('.scene__canvas');
  const ctx = canvas.getContext('2d');
  const markers = [...root.querySelectorAll('.spot')];
  const spots = JSON.parse(root.dataset.spots);
  const hasIntro = Boolean(introEnd);
  const hasVideo = Boolean(video);

  const keys = Object.keys(spots);
  const place = {
    panel: root.querySelector('.scene__loc'),
    title: root.querySelector('.scene__loc-title'),
    text: root.querySelector('.scene__loc-text'),
  };

  root.addEventListener('click', (e) => {
    const spot = e.target.closest('[data-spot], [data-spot-open]');
    if (spot) return openPlace(spot.dataset.spot || spot.dataset.spotOpen);
    if (e.target.closest('.scene__back')) return closePlace();
    const step = e.target.closest('[data-loc-step]');
    if (step) stepPlace(Number(step.dataset.locStep));
  });

  // Sorot penanda
  for (const row of root.querySelectorAll('[data-spot-open]')) {
    const marker = root.querySelector(`[data-spot="${row.dataset.spotOpen}"]`);
    if (!marker) continue;
    const mark = (on) => () => marker.classList.toggle('is-active', on);
    row.addEventListener('pointerenter', mark(true));
    row.addEventListener('pointerleave', mark(false));
    row.addEventListener('focus', mark(true));
    row.addEventListener('blur', mark(false));
  }

  // Waktu frame tampil
  let shownTime = null;
  if (hasVideo) {
    video.src = video.dataset.src;
    if ('requestVideoFrameCallback' in video) {
      const onFrame = (_now, meta) => {
        shownTime = meta.mediaTime;
        video.requestVideoFrameCallback(onFrame);
      };
      video.requestVideoFrameCallback(onFrame);
    }
  }

  const set = await (root.dataset.still ? stillSet(root.dataset.still) : frameSet(root.dataset.base)).ready;
  const { m } = set;
  const N = m.count;
  // Jalur titik
  if (root.dataset.tracks) Object.assign(m.hotspots, JSON.parse(root.dataset.tracks));
  if (!set.still) placePoints(m, spots);

  // Rotasi gambar diam
  const sweep = ((Number(root.dataset.sweep) || 0) * Math.PI) / 180;
  const turn = (fw) => {
    const a = sweep * Math.sin((2 * Math.PI * fw) / N);
    const c = Math.abs(Math.cos(a));
    const sn = Math.abs(Math.sin(a));
    const dw = m.width * scale;
    const dh = m.height * scale;
    return { a, s: Math.max(1, (W * c + H * sn) / dw, (W * sn + H * c) / dh) };
  };
  const stillPoints = {};
  if (set.still) {
    for (const spot of Object.values(spots)) {
      if (spot.point) stillPoints[spot.track] = [(spot.point[0] / 100) * m.width, (spot.point[1] / 100) * m.height];
    }
  }

  // Konversi waktu video
  const perSecond = hasVideo ? (m.video.fps * m.video.step) / m.step : 0;
  // Tanpa pembulatan
  const videoToFrame = (t) => (Math.floor(t * m.video.fps + 0.01) * (m.video.step / m.step)) % N;
  const frameToVideo = (f) => (((f % N) + N) % N) / perSecond;

  // Status scene
  let mode = hasVideo ? 'video' : 'scrub';
  let offset = Number(root.dataset.start) || 0; // Frame awal
  let current = offset; // Posisi dihaluskan
  let drawn = NaN; // Posisi di canvas
  let shownF = 0; // Posisi tampil
  let shownIndex = 0; // Frame terdekat
  let lastTarget = NaN; // Target sebelumnya
  let override = null; // Frame dari hotspot
  let painted = 0; // Frame di canvas
  let ticking = false;

  // Tata letak
  let W = 0, H = 0, dpr = 1, scale = 1, ox = 0, oy = 0;
  let avoid = []; // Area teks
  const measureAvoid = () => {
    const area = root.querySelector('.scene__area');
    avoid = [...area.children].map((el) => ({
      x: area.offsetLeft + el.offsetLeft,
      y: area.offsetTop + el.offsetTop,
      w: el.offsetWidth,
      h: el.offsetHeight,
    }));
    // Area hindari penanda
    for (const el of root.querySelectorAll('[data-scene-avoid]')) {
      avoid.push({ x: el.offsetLeft, y: el.offsetTop, w: el.offsetWidth, h: el.offsetHeight });
    }
  };
  const covered = (x, y) => avoid.some((r) => x > r.x - 28 && x < r.x + r.w + 28 && y > r.y - 28 && y < r.y + r.h + 28);
  const resize = () => {
    W = stage.clientWidth;
    H = stage.clientHeight;
    dpr = Math.min(devicePixelRatio || 1, 2);
    canvas.width = Math.round(W * dpr);
    canvas.height = Math.round(H * dpr);
    // Cover-fit frame
    scale = Math.max(W / m.width, H / m.height);
    ox = (W - m.width * scale) / 2;
    oy = (H - m.height * scale) / 2;
    measureAvoid();
    drawn = NaN;
    if (placeKey) applyCam(spotCam(placeKey));
    requestTick();
  };

  // Jarak scroll
  const scrolled = () => -root.getBoundingClientRect().top;
  const introLength = () => (hasIntro ? introEnd.offsetTop : 0);
  // Panel mode zoom
  const zoomEnd = root.querySelector('[data-zoom-end]');
  const panelFrom = () => (root.dataset.panel === 'zoom' && zoomEnd ? zoomEnd.offsetTop : introLength());
  const progress = () => {
    const intro = introLength();
    const range = root.offsetHeight - stage.clientHeight - intro;
    return range > 0 ? clamp((scrolled() - intro) / range, 0, 1) : 0;
  };
  // Terlihat tanpa ukur
  let inView = false;
  new IntersectionObserver(([entry]) => {
    inView = entry.isIntersecting;
    if (inView) requestTick();
  }, { rootMargin: '50px 0px' }).observe(root);
  const onScreen = () => inView;

  // Tampilkan posisi frame
  function showFrame(f) {
    const fw = ((f % N) + N) % N;
    shownF = fw;
    shownIndex = Math.round(fw) % N;
    // Kapan redraw
    const key = set.still ? fw : shownIndex;
    if (!(Math.abs(key - drawn) < 0.002) && draw(fw)) {
      drawn = key;
      // Pembuka tanpa video
      if (!(hasIntro && !hasVideo)) root.classList.add('is-scrub');
    }
    return fw;
  }

  // Gambar lewat kamera
  function draw(fw) {
    const t = fw - Math.floor(fw);
    const near = Math.round(fw) % N;
    const whole = t < 0.01 || t > 0.99;
    const detail = cam && whole && set.detail?.get(near);
    const index = detail || set.frames[near] ? near : set.nearestIndex(near);
    const img = detail || set.frames[index];
    if (!img) return false;
    painted = index;
    const k = cam ? W / cam[2] : 1;
    const tx = cam ? W / 2 - cam[0] * k : 0;
    const ty = cam ? H / 2 - cam[1] * k : 0;
    ctx.setTransform(dpr * k, 0, 0, dpr * k, dpr * tx, dpr * ty);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    const dw = m.width * scale;
    const dh = m.height * scale;
    if (set.still) {
      const { a, s } = turn(fw);
      ctx.translate(ox + dw / 2, oy + dh / 2);
      ctx.rotate(a);
      ctx.scale(s, s);
      ctx.drawImage(set.still, -dw / 2, -dh / 2, dw, dh);
      return true;
    }
    ctx.drawImage(img, ox, oy, dw, dh);
    return true;
  }

  // Titik terlacak
  const trackAt = (track, fw) => {
    if (set.still) {
      const p = stillPoints[track];
      if (!p) return null;
      const { a, s } = turn(fw);
      const dx = p[0] - m.width / 2;
      const dy = p[1] - m.height / 2;
      return [m.width / 2 + s * (dx * Math.cos(a) - dy * Math.sin(a)), m.height / 2 + s * (dx * Math.sin(a) + dy * Math.cos(a))];
    }
    const pts = m.hotspots[track];
    if (!pts) return null;
    const base = Math.floor(fw) % N;
    const t = fw - Math.floor(fw);
    const p0 = pts[base];
    const p1 = pts[(base + 1) % N];
    if (p0 && p1) return [p0[0] + (p1[0] - p0[0]) * t, p0[1] + (p1[1] - p0[1]) * t];
    return t < 0.5 ? p0 : p1;
  };

  function placeMarkers(fw, visible) {
    for (const el of markers) {
      // Ikut frame tergambar
      const p = visible && trackAt(spots[el.dataset.spot].track, set.still ? fw : painted);
      const x = p && ox + p[0] * scale;
      const y = p && oy + p[1] * scale;
      const show = p && x > 24 && y > 80 && x < W - 24 && y < H - 24 && !covered(x, y);
      el.hidden = !show;
      if (!show) continue;
      // Label di kiri
      const flip = x + Number((el.dataset.w ||= el.offsetWidth)) > W - 16;
      el.classList.toggle('spot--flip', flip);
      const left = flip ? x - el.offsetWidth : x;
      el.style.transform = `translate3d(${left.toFixed(1)}px, ${y.toFixed(1)}px, 0)`;
    }
  }

  // Menuju lokasi
  const ZOOM = 2.2; // Jarak zoom
  let placeKey = null; // Hotspot terbuka
  let placeY = 0; // Scroll saat dibuka
  let cam = null; // Kamera aktif
  let run = 0; // ID gerakan
  let flyTimer = 0;

  const flying = () => {
    root.classList.add('is-flying');
    clearTimeout(flyTimer);
    flyTimer = setTimeout(() => root.classList.remove('is-flying'), 3000);
  };

  const wrap = (i) => ((i % N) + N) % N;
  const shortest = (a, b) => {
    const d = wrap(b - a);
    return d > N / 2 ? d - N : d;
  };
  const overview = () => [W / 2, H / 2, W];
  const clampCam = ([x, y, w]) => {
    const h = (w * H) / W;
    return [clamp(x, w / 2, W - w / 2), clamp(y, h / 2, H - h / 2), w];
  };
  // Posisi hotspot
  const posAt = (key, fw) => {
    const p = trackAt(spots[key].track, fw);
    return p ? [ox + p[0] * scale, oy + p[1] * scale] : null;
  };
  const spotCam = (key) => {
    const p = posAt(key, shownF) || [W / 2, H / 2];
    return clampCam([p[0], p[1], W / ZOOM]);
  };

  // Atur kamera
  const applyCam = (c) => {
    cam = c;
    if (draw(shownF)) drawn = shownF;
  };

  const ease = (k) => (k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2);
  const lerp = (a, b, t) => a + (b - a) * t;
  // Animasi berdurasi
  const tween = (id, ms, fn) =>
    new Promise((resolve) => {
      const t0 = performance.now();
      const frame = (now) => {
        if (id !== run) return resolve(false);
        const k = reduced ? 1 : Math.min(1, (now - t0) / ms);
        fn(k);
        k < 1 ? requestAnimationFrame(frame) : resolve(true);
      };
      requestAnimationFrame(frame);
    });

  // Satu gerakan
  function travel(id, { fromKey = null, toKey = null, toFrame }) {
    const c0 = cam || overview();
    const l0 = Math.log(W / c0[2]);
    const l1 = toKey ? Math.log(ZOOM) : 0;
    const f0 = override ?? current;
    const d = shortest(f0, toFrame);
    const ms = clamp(1300 + Math.abs(d) * 16, 1300, 2600);
    let lastA = null;
    let lastB = null;
    const done = tween(id, ms, (t) => {
      const e = ease(t);
      override = f0 + d * e; // Sudut dari hotspot
      const fw = wrap(override);
      const a = fromKey ? (lastA = posAt(fromKey, fw) || lastA) : null;
      const b = toKey ? (lastB = posAt(toKey, fw) || lastB) : null;
      const start = a || [c0[0], c0[1]];
      const end = b || [W / 2, H / 2];
      // Kurva gerak
      const pan = toKey && !fromKey ? ease(clamp(t / 0.8, 0, 1)) : e;
      let ls;
      if (fromKey && toKey) ls = lerp(l0, l1, e) - 0.55 * Math.log(ZOOM) * Math.sin(Math.PI * t);
      else if (toKey) ls = lerp(l0, l1, ease(clamp((t - 0.15) / 0.85, 0, 1)));
      else ls = lerp(l0, l1, e);
      cam = clampCam([lerp(start[0], end[0], pan), lerp(start[1], end[1], pan), W / Math.exp(Math.max(0, ls))]);
      drawn = NaN; // Paksa redraw
      showFrame(override);
    });
    return { done, ms };
  }

  // Gambar hotspot proyek
  const picture = root.querySelector('[data-scene-card]');
  const panelImg = root.querySelector('.scene__loc-img');
  const figure = root.querySelector('[data-place-figure]');
  // Pramuat gambar titik
  if (figure) {
    const preload = () => {
      for (const { image } of Object.values(spots)) {
        if (image) Object.assign(new Image(), { fetchPriority: 'low', src: image });
      }
    };
    const near = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return;
      near.disconnect();
      (window.requestIdleCallback || setTimeout)(preload);
    }, { rootMargin: '50% 0px' });
    near.observe(root);
  }
  // Fade latar zoom
  const zoomLayer = root.querySelector('[data-scene-zoom]');
  const zoomCache = new Map();
  const preloadZoom = (key) => {
    const src = spots[key]?.zoom;
    if (!zoomLayer || !src) return Promise.resolve(false);
    if (!zoomCache.has(src)) {
      const img = new Image();
      img.decoding = 'async';
      img.src = src;
      zoomCache.set(src, img.decode().then(() => true, () => false));
    }
    return zoomCache.get(src);
  };
  const hideZoom = () => {
    if (!zoomLayer?.classList.contains('is-on')) return Promise.resolve();
    zoomLayer.classList.remove('is-on');
    return new Promise((resolve) => setTimeout(resolve, reduced ? 0 : 400));
  };
  const showZoom = (key) => {
    zoomLayer.src = spots[key].zoom;
    zoomLayer.classList.add('is-on');
  };
  // Pramuat saat hover
  if (zoomLayer) {
    root.addEventListener('pointerover', (e) => {
      const spot = e.target.closest('[data-spot], [data-spot-open]');
      if (spot) preloadZoom(spot.dataset.spot || spot.dataset.spotOpen);
    });
  }

  const fillPanel = (key) => {
    const c = spots[key];
    place.title.textContent = c.title;
    place.text.textContent = c.text;
    if (picture && c.image) {
      picture.hidden = false;
      picture.querySelector('img').src = c.image;
      picture.querySelector('img').alt = c.title;
    }
    if (panelImg) {
      panelImg.hidden = !c.image;
      if (c.image) panelImg.src = c.image;
    }
  };
  const showPanel = (key) => {
    fillPanel(key);
    place.panel.hidden = false;
    if (figure) {
      const { image, title } = spots[key];
      figure.hidden = !image;
      // Muat gambar dulu
      if (image) Object.assign(figure.querySelector('img'), { fetchPriority: 'high', src: image, alt: title });
    }
    place.title.focus({ preventScroll: true });
  };

  // Video ke frame
  const handOver = () => {
    offset = videoToFrame(shownTime ?? video.currentTime);
    current = offset;
    drawn = NaN;
    mode = 'scrub';
    release(video);
  };

  async function openPlace(key) {
    // Ambil alih video
    if (mode === 'video' && hasVideo && root.dataset.panel === 'zoom') {
      handOver();
      showFrame(current);
    }
    if (mode !== 'scrub' || !spots[key] || key === placeKey) return;
    set.loadDetail?.();
    const id = ++run;
    const from = placeKey;
    placeKey = key;
    const zoomReady = preloadZoom(key);
    // Pudar sebelum pindah
    if (from) {
      await hideZoom();
      if (id !== run) return;
    }
    placeY = window.scrollY;
    place.panel.hidden = true;
    if (figure) figure.hidden = true;
    flying();
    root.classList.remove('is-returning');
    root.classList.add('is-place');
    const toFrame = spots[key].frame ?? shownIndex;
    const { done, ms } = travel(id, { fromKey: from, toKey: key, toFrame });
    const panelAt = setTimeout(() => id === run && showPanel(key), ms * 0.75);
    if (!(await done)) return clearTimeout(panelAt);
    if ((await zoomReady) && id === run) showZoom(key);
  }

  async function closePlace() {
    if (!placeKey) return;
    const id = ++run;
    const key = placeKey;
    placeKey = null;
    place.panel.hidden = true;
    if (figure) figure.hidden = true;
    // Pudar sebelum kembali
    await hideZoom();
    if (id !== run) return;
    flying();
    if (root.contains(document.activeElement) || document.activeElement === document.body) {
      root.querySelector(`[data-spot-open="${key}"]`)?.focus({ preventScroll: true });
    }
    // Kembali ke sudut
    root.classList.add('is-returning');
    const { done } = travel(id, { fromKey: key, toFrame: Math.round(current) });
    setTimeout(() => id === run && root.classList.remove('is-place'), 500);
    if (!(await done)) return;
    override = null;
    root.classList.remove('is-place', 'is-returning');
    applyCam(null);
  }

  const stepPlace = (d) => {
    const at = placeKey ?? keys[0];
    openPlace(keys[(keys.indexOf(at) + d + keys.length) % keys.length]);
  };

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && placeKey && !document.querySelector('dialog[open]')) closePlace();
  });

  let lastArea = '';
  function tick() {
    ticking = false;
    if (placeKey && Math.abs(window.scrollY - placeY) > 120) closePlace(); // Scroll menutup hotspot
    if (!onScreen()) return;
    const p = progress();

    // Fase scene
    const intro = introLength();
    const from = panelFrom();
    const areaIn = hasIntro ? smooth(scrolled(), from, from + H * 0.3) : 1;
    // Tulis bila berubah
    const area = areaIn.toFixed(3);
    if (area !== lastArea) {
      lastArea = area;
      root.style.setProperty('--p-area', area);
      root.style.setProperty('--v-area', areaIn > 0 ? 'visible' : 'hidden');
    }
    if (hasIntro && !hasVideo) root.classList.toggle('is-scrub', p > 0);

    if (hasVideo && p <= 0 && mode === 'scrub' && !placeKey && override === null && Math.abs(current - offset) < 0.05) {
      // Kembalikan ke video
      mode = 'video';
      shownTime = null;
      resume(video);
      video.currentTime = frameToVideo(current);
      root.classList.remove('is-scrub');
      placeMarkers(0, false);
    }

    if (mode === 'video' && video.paused && !reduced) {
      resume(video);
      video.play().catch(() => {});
    }

    if (hasVideo && p > 0 && mode === 'video') handOver();

    if (mode !== 'scrub') return;

    // Orbit ikut scroll
    const target = offset + p * N;
    const idle = target === lastTarget;
    lastTarget = target;
    const goal = idle && p > 0 ? Math.round(target) : target;
    current = reduced ? goal : current + (goal - current) * 0.25;
    if (Math.abs(goal - current) < 0.004) current = goal;

    const fw = showFrame(override ?? current);
    placeMarkers(fw, areaIn > 0.3);

    if (current !== goal || !idle) requestTick();
  }

  function requestTick() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(tick);
  }

  // Canvas siap awal
  let warm = !hasVideo;
  const warmUp = () => {
    if (!warm && draw(offset)) warm = true;
  };
  // Pra-decode frame
  if (hasVideo && 'requestVideoFrameCallback' in video) {
    let followed = NaN;
    const follow = (_now, meta) => {
      if (mode === 'video' && warm && onScreen()) {
        const f = videoToFrame(meta.mediaTime);
        if (!(Math.abs(f - followed) < 0.5) && draw(f)) followed = f;
      }
      video.requestVideoFrameCallback(follow);
    };
    video.requestVideoFrameCallback(follow);
  }

  // Frame lebih tajam
  set.listeners.add(() => {
    warmUp();
    if (mode === 'scrub') {
      drawn = NaN;
      requestTick();
    }
  });
  // Frame detail
  root.addEventListener('pointerover', (e) => e.target.closest('[data-spot], [data-spot-open]') && set.loadDetail?.(), { passive: true });
  root.addEventListener('focusin', (e) => e.target.closest('[data-spot-open]') && set.loadDetail?.());
  window.addEventListener('scroll', requestTick, { passive: true });
  window.addEventListener('resize', () => {
    resize();
    if (mode === 'video') draw(offset);
  });
  resize();
  warmUp();

  // Jeda video
  if (hasVideo) {
    new IntersectionObserver(([e]) => {
      if (mode === 'video' && !e.isIntersecting) release(video);
      else requestTick();
    }).observe(root);
  }
}

// Mulai scene
const scenes = [...document.querySelectorAll('[data-scene]')];
const lazy = new IntersectionObserver(
  (entries) => {
    for (const e of entries) {
      if (!e.isIntersecting) continue;
      lazy.unobserve(e.target);
      init(e.target);
    }
  },
  { rootMargin: '150% 0px' },
);
for (const s of scenes) lazy.observe(s);
