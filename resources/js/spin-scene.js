// Mesin 360°: frame kanvas, diputar lewat scroll atau drag
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const sets = new Map();

// Urutan muat: kasar dulu, lalu rapat
const loadOrder = (n) => {
  const order = [];
  for (let stride = 16; stride >= 1; stride /= 2) {
    for (let i = 0; i < n; i += stride) if (!order.includes(i)) order.push(i);
  }
  return order;
};

function frameSet(urls, onFrame) {
  const key = urls.join('|');
  if (sets.has(key)) {
    sets.get(key).listeners.add(onFrame);
    return sets.get(key);
  }
  const set = { frames: new Array(urls.length).fill(null), listeners: new Set([onFrame]) };
  sets.set(key, set);
  const order = loadOrder(urls.length);
  let next = 0;
  const loadOne = () => {
    if (next >= order.length) return;
    const i = order[next++];
    const img = new Image();
    // Decode di luar thread utama
    img.onload = () => img.decode().catch(() => {}).then(() => {
      set.frames[i] = img;
      set.listeners.forEach((fn) => fn());
      loadOne();
    });
    img.onerror = loadOne;
    img.src = urls[i];
  };
  for (let k = 0; k < 4; k++) loadOne();
  return set;
}

const nearest = (frames, i) => {
  const n = frames.length;
  for (let d = 0; d < n; d++) {
    const a = frames[(i + d) % n];
    if (a) return a;
    const b = frames[(i - d + n) % n];
    if (b) return b;
  }
  return null;
};

export function spin(root) {
  const tabs = JSON.parse(root.dataset.tabs || '[]');
  if (!tabs.length) return null;
  const mode = root.dataset.spin;
  const canvas = root.querySelector('[data-spin-canvas]');
  const poster = root.querySelector('[data-spin-poster]');
  const text = root.querySelector('[data-spin-text]');
  const name = root.querySelector('[data-spin-name]');
  const buttons = [...root.querySelectorAll('[data-spin-tab]')];
  const steps = [...root.querySelectorAll('[data-spin-step]')];
  const pin = root.querySelector('[data-spin-pin]'); // Isi yang tertahan saat gulir
  // Indikator tab dan detailnya
  const spot = root.querySelector('[data-spin-spot]');
  const zoomLayer = root.querySelector('[data-spin-zoom]');
  const detail = root.querySelector('[data-spin-detail]');
  const tabList = root.querySelector('[data-spin-tabs]');
  const ctx = canvas.getContext('2d');
  const same = (a, b) => a.frames.join('|') === b.frames.join('|');
  // Potongan bersambung jadi satu putaran
  const joined = tabs.length > 1
    && tabs.every((t, k) => t.frames.length > 1 && (k === tabs.length - 1 || t.frames[t.frames.length - 1] === tabs[k + 1].frames[0]));
  if (joined) {
    const ring = [...tabs[0].frames];
    const starts = [0];
    for (const t of tabs.slice(1)) {
      starts.push(ring.length - 1);
      ring.push(...t.frames.slice(1));
    }
    tabs.forEach((t, k) => Object.assign(t, { frames: ring, frame: starts[k] }));
  }
  // Satu putaran bersama: tab ikut sudut
  const together = tabs.length > 1 && tabs[0].frames.length > 1 && tabs.every((t) => same(t, tabs[0]));
  let active = 0;
  let seg = 0; // Bagian gulir
  let offset = 0; // Putaran drag, dalam frame
  let glideId = 0; // Animasi antar tab
  let drawn = '';
  let queued = false;
  let inView = false;
  let top = 0;
  let range = 0;
  let W = 0;
  let H = 0;

  const tab = () => tabs[active];
  const set = () => frameSet(tab().frames, request);

  // Ukur sekali, bukan tiap frame
  const measure = () => {
    W = canvas.clientWidth;
    H = canvas.clientHeight;
    // Tidak lebih tajam dari gambar sumber
    const ratio = Math.min(devicePixelRatio || 1, 2, Math.max(1, (tab().width || W) / Math.max(W, 1)));
    canvas.width = Math.max(1, Math.round(W * ratio));
    canvas.height = Math.max(1, Math.round(H * ratio));
    if (mode === 'scroll') {
      top = root.getBoundingClientRect().top + window.scrollY;
      range = Math.max(1, root.offsetHeight - (pin ? pin.offsetHeight : window.innerHeight));
    }
    drawn = '';
    request();
  };

  // Gulir: tab aktif dan frame dari posisi gulir
  const scrolled = () => {
    const count = tabs.length;
    const p = clamp((window.scrollY - top) / range, 0, 0.9999);
    const k = Math.min(count - 1, Math.floor(p * count));
    if (k !== seg) {
      seg = k;
      if (offset) glide(); // Geseran kembali ke sudut tab
      if (!together) {
        active = k;
        label();
      }
    }
    const n = tabs[k].frames.length;
    // Putaran bersama: sampai sudut tab berikutnya; selain itu satu putaran
    const span = together ? (((((k + 1 < count ? tabs[k + 1].frame : n) - tabs[k].frame) % n) + n) % n || n) : n;
    return Math.floor(tabs[k].frame + (p * count - k) * span) % n;
  };

  // Tab pemilik sudut yang tampil
  const owner = (at) => {
    let k = tabs.length - 1;
    tabs.forEach((t, i) => { if (t.frame <= at) k = i; });
    return k;
  };

  const frameIndex = () => {
    if (mode === 'scroll') {
      const base = scrolled() + Math.floor(offset); // Gulir ditambah geseran
      const n = tabs[seg].frames.length;
      const at = ((base % n) + n) % n;
      if (together && owner(at) !== active) {
        active = owner(at);
        label();
      }
      return at;
    }
    const n = tab().frames.length;
    return ((Math.floor(tab().frame + offset) % n) + n) % n;
  };

  function draw() {
    queued = false;
    if (!inView) return;
    const i = frameIndex();
    const frames = set().frames;
    const img = frames[i] || nearest(frames, i);
    const key = `${active}:${img ? frames.indexOf(img) : -1}:${canvas.width}`;
    if (!img || key === drawn) return;
    drawn = key;
    // Diputar penuh, gambar diam utuh
    const fit = tab().frames.length > 1 ? Math.max : Math.min;
    const s = fit(canvas.width / img.naturalWidth, canvas.height / img.naturalHeight);
    const dw = img.naturalWidth * s;
    const dh = img.naturalHeight * s;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, (canvas.width - dw) / 2, (canvas.height - dh) / 2, dw, dh);
    if (poster) poster.hidden = true;
  }

  function request() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(draw);
  }

  // Putar halus ke sudut tab, jalur terpendek
  const glide = () => {
    const n = tab().frames.length;
    offset = ((offset % n) + n) % n;
    if (offset > n / 2) offset -= n;
    const from = offset;
    const id = ++glideId;
    if (reduced || Math.abs(from) < 0.5) {
      offset = 0;
      return;
    }
    const t0 = performance.now();
    const ms = clamp(Math.abs(from) * 20, 500, 1400);
    const step = (now) => {
      if (id !== glideId) return;
      const k = Math.min(1, (now - t0) / ms);
      const e = k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;
      offset = from * (1 - e);
      request();
      if (k < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };

  // Teks dan tombol tab aktif
  const label = () => {
    buttons.forEach((b, k) => b.setAttribute('aria-pressed', String(k === active)));
    if (text) {
      text.textContent = tab().text;
      text.hidden = !tab().text;
    }
    if (name) name.textContent = tab().name;
    canvas.setAttribute('aria-label', canvas.getAttribute('aria-label').replace(/, [^:]+:/, `, ${tab().name}:`));
    still();
    placeSpot();
  };

  // Indikator hanya untuk tab bergambar zoom
  const placeSpot = () => {
    if (!spot) return;
    spot.hidden = !tab().zoom;
    // Posisi dari CMS, juga titik tuju zoom
    spot.parentElement.style.setProperty('--spot-x', `${tab().x ?? 50}%`);
    spot.parentElement.style.setProperty('--spot-y', `${tab().y ?? 50}%`);
    spot.querySelector('.spot__label').textContent = tab().name;
    spot.setAttribute('aria-label', `Lihat ${tab().name}`);
  };

  const show = (index) => {
    if (index === active) return;
    const before = tab();
    const shown = frameIndex();
    active = index;
    offset = 0;
    // Gambar sama: lanjut dari sudut sekarang
    if (mode === 'drag' && same(before, tab())) {
      offset = shown - tab().frame;
      glide();
    } else {
      glideId++;
    }
    label();
    measure();
  };
  buttons.forEach((b, k) => b.addEventListener('click', () => {
    if (mode !== 'scroll') return show(k);
    // Gulir ke awal bagian tab
    if (offset) glide();
    window.scrollTo({ top: top + (k / tabs.length) * range + 2, behavior: reduced ? 'auto' : 'smooth' });
  }));

  // Geser melewati potongan: tab berikutnya aktif
  const follow = () => {
    if (!together || mode === 'scroll') return 0; // Mode gulir: diatur frameIndex
    const n = tab().frames.length;
    const k = owner((((tab().frame + offset) % n) + n) % n);
    if (k === active) return 0;
    // Sudut tetap, acuan pindah
    const shift = tab().frame - tabs[k].frame;
    offset += shift;
    active = k;
    label();
    return shift;
  };

  // Gambar diam tanpa putar
  const still = () => {
    const fixed = tab().frames.length < 2;
    steps.forEach((b) => { b.hidden = fixed; });
    canvas.classList.toggle('is-still', fixed);
  };
  still();

  // Drag dan tombol putar, di kedua mode
  let startX = null;
  let startOffset = 0;
  const perPixel = () => tab().frames.length / Math.max(W, 1);
  canvas.addEventListener('pointerdown', (e) => {
    glideId++; // Geser menghentikan animasi
    startX = e.clientX;
    startOffset = offset;
    canvas.setPointerCapture(e.pointerId);
  });
  canvas.addEventListener('pointermove', (e) => {
    if (startX === null) return;
    offset = startOffset - (e.clientX - startX) * perPixel();
    startOffset += follow();
    request();
  });
  const stop = () => { startX = null; };
  canvas.addEventListener('pointerup', stop);
  canvas.addEventListener('pointercancel', stop);
  canvas.addEventListener('keydown', (e) => {
    const step = { ArrowLeft: -1, ArrowRight: 1 }[e.key];
    if (!step) return;
    e.preventDefault();
    glideId++;
    offset += step;
    follow();
    request();
  });
  for (const button of root.querySelectorAll('[data-spin-step]')) {
    let timer = null;
    const turn = () => { glideId++; offset += Number(button.dataset.spinStep); follow(); request(); };
    const release = () => { clearInterval(timer); timer = null; };
    button.addEventListener('pointerdown', () => { turn(); timer = setInterval(turn, 60); });
    for (const ev of ['pointerup', 'pointerleave', 'pointercancel']) button.addEventListener(ev, release);
    button.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); turn(); } });
  }

  // Zoom seperti titik Masterplan
  const ZOOM_MS = 1300; // Sama dengan transisi CSS
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, reduced ? 0 : ms));
  const zoomCache = new Map();
  const preloadZoom = (src) => {
    if (!src) return Promise.resolve(false);
    if (!zoomCache.has(src)) {
      const img = new Image();
      img.decoding = 'async';
      img.src = src;
      zoomCache.set(src, img.decode().then(() => true, () => false));
    }
    return zoomCache.get(src);
  };
  let zoomed = null; // Tab terbuka
  let zoomRun = 0;
  let zoomY = 0;

  async function openZoom() {
    if (zoomed !== null || !tab().zoom) return;
    const id = ++zoomRun;
    const part = tab();
    zoomed = active;
    zoomY = window.scrollY;
    glideId++;
    const ready = preloadZoom(part.zoom);
    root.classList.remove('is-returning', 'is-unzoom');
    root.classList.add('is-zoom');
    // Silang pudar di paruh akhir zoom
    Promise.all([ready, wait(ZOOM_MS * 0.5)]).then(([ok]) => {
      if (!ok || id !== zoomRun) return;
      Object.assign(zoomLayer, { src: part.zoom, alt: part.name });
      zoomLayer.classList.add('is-on');
      root.classList.add('is-zoom-on');
    });
    // Detail muncul saat kamera hampir sampai
    await wait(ZOOM_MS * 0.75);
    if (id !== zoomRun) return;
    detail.querySelector('[data-spin-detail-title]').textContent = part.name;
    const text = detail.querySelector('[data-spin-detail-text]');
    text.textContent = part.text;
    text.hidden = !part.text;
    tabList.hidden = true;
    detail.classList.remove('is-leaving');
    detail.hidden = false;
    detail.querySelector('[data-spin-detail-title]').focus({ preventScroll: true });
  }

  async function closeZoom() {
    if (zoomed === null) return;
    const id = ++zoomRun;
    zoomed = null;
    const focused = root.contains(document.activeElement);
    // Gambar tab sudah tampil: 360° utuh di baliknya, lalu silang pudar
    const fade = root.classList.contains('is-zoom-on');
    root.classList.add('is-returning');
    if (fade) root.classList.replace('is-zoom', 'is-unzoom');
    // Detail dan gambar pudar, 360° kembali tampil
    detail.classList.add('is-leaving');
    zoomLayer.classList.remove('is-on');
    root.classList.remove('is-zoom-on');
    await wait(300);
    if (id !== zoomRun) return;
    detail.hidden = true;
    detail.classList.remove('is-leaving');
    tabList.hidden = false;
    if (focused) buttons[active]?.focus({ preventScroll: true });
    root.classList.remove('is-zoom');
    await wait(fade ? 300 : ZOOM_MS);
    if (id === zoomRun) root.classList.remove('is-returning', 'is-unzoom'); // Indikator tampil setelah kembali
  }

  if (spot) {
    spot.addEventListener('click', openZoom);
    for (const ev of ['pointerover', 'focus']) spot.addEventListener(ev, () => preloadZoom(tab().zoom));
    detail.querySelector('[data-spin-back]').addEventListener('click', closeZoom);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && zoomed !== null && !document.querySelector('dialog[open]')) closeZoom();
    });
    // Gulir menutup detail
    window.addEventListener('scroll', () => {
      if (zoomed !== null && Math.abs(window.scrollY - zoomY) > 120) closeZoom();
    }, { passive: true });
  }

  if (mode === 'scroll') {
    // Latar melebar saat naik
    const open = () => {
      const y = root.getBoundingClientRect().top;
      const span = Math.max(1, Math.min(y + window.scrollY, window.innerHeight * 0.5)); // Dari posisi awal
      root.style.setProperty('--spin-open', clamp(1 - y / span, 0, 1).toFixed(3));
    };
    open();
    window.addEventListener('scroll', () => { open(); request(); }, { passive: true });
    window.addEventListener('resize', open);
  }

  new IntersectionObserver(([entry]) => {
    inView = entry.isIntersecting;
    if (inView) request();
  }, { rootMargin: '200px 0px' }).observe(root);
  const observer = new ResizeObserver(measure);
  observer.observe(canvas);
  if (mode === 'scroll') observer.observe(document.body);
  set();

  return { show, get active() { return active; } };
}

for (const root of document.querySelectorAll('[data-spin]')) spin(root);
