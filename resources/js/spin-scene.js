// Mesin 360°: frame kanvas, diputar lewat scroll atau drag
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
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
  const ctx = canvas.getContext('2d');
  let active = 0;
  let offset = 0; // Putaran drag, dalam frame
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
      range = Math.max(1, root.offsetHeight - window.innerHeight);
    }
    drawn = '';
    request();
  };

  const frameIndex = () => {
    const n = tab().frames.length;
    const turn = mode === 'scroll' ? clamp((window.scrollY - top) / range, 0, 1) * n : offset;
    return ((Math.floor(tab().frame + turn) % n) + n) % n;
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

  const show = (index) => {
    if (index === active) return;
    active = index;
    offset = 0;
    buttons.forEach((b, k) => b.setAttribute('aria-pressed', String(k === index)));
    if (text) {
      text.textContent = tab().text;
      text.hidden = !tab().text;
    }
    if (name) name.textContent = tab().name;
    canvas.setAttribute('aria-label', canvas.getAttribute('aria-label').replace(/, [^:]+:/, `, ${tab().name}:`));
    still();
    measure();
  };
  buttons.forEach((b, k) => b.addEventListener('click', () => show(k)));

  // Gambar diam tanpa putar
  const still = () => {
    const fixed = tab().frames.length < 2;
    steps.forEach((b) => { b.hidden = fixed; });
    canvas.classList.toggle('is-still', fixed);
  };
  still();

  // Drag dan tombol putar
  if (mode === 'drag') {
    let startX = null;
    let startOffset = 0;
    const perPixel = () => tab().frames.length / Math.max(W, 1);
    canvas.addEventListener('pointerdown', (e) => {
      startX = e.clientX;
      startOffset = offset;
      canvas.setPointerCapture(e.pointerId);
    });
    canvas.addEventListener('pointermove', (e) => {
      if (startX === null) return;
      offset = startOffset - (e.clientX - startX) * perPixel();
      request();
    });
    const stop = () => { startX = null; };
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointercancel', stop);
    canvas.addEventListener('keydown', (e) => {
      const step = { ArrowLeft: -1, ArrowRight: 1 }[e.key];
      if (!step) return;
      e.preventDefault();
      offset += step;
      request();
    });
    for (const button of root.querySelectorAll('[data-spin-step]')) {
      let timer = null;
      const turn = () => { offset += Number(button.dataset.spinStep); request(); };
      const release = () => { clearInterval(timer); timer = null; };
      button.addEventListener('pointerdown', () => { turn(); timer = setInterval(turn, 60); });
      for (const ev of ['pointerup', 'pointerleave', 'pointercancel']) button.addEventListener(ev, release);
      button.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); turn(); } });
    }
  } else {
    window.addEventListener('scroll', request, { passive: true });
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
