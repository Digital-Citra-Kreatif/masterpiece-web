// Scroll scenes: a pinned canvas whose image sequence follows page scroll.
//
// Each scene starts at data-start and scrubs one full orbit over its range.
//
// Masterplan scene (has [data-intro-end]): its first stretch of scroll belongs to
// the Masterplan opening (masterplan.js), which grows the frame holding the orbit
// video to the full stage. The video loops (it is the same orbit as the frames)
// until the orbit's range starts at [data-intro-end]. On the first scroll into the
// range the canvas takes over at the frame the video is showing, so there is no
// cut, the Masterplan layer hides (.is-scrub) and the section panel and markers
// fade in. Back at the start of the range the video resumes from the frame the
// scrub stopped on.
//
// Hotspots: opening one (marker or list) fast-forwards the orbit to the
// hotspot's default angle while zooming in on it, with its info in place of the
// panel. Back, Esc, or scrolling on turns and zooms back out to the orbit.
//
// Scenes that share a frame folder share one download (see frameSet).
//
// Still scenes ([data-still]): until a project has its sequence, one picture
// stands in for it (stillSet). It counts as STILL_FRAMES frames: over the scroll
// range it turns one way and back by up to [data-sweep] degrees, scaled up just
// enough to keep covering the stage, and the hotspots (x/y % of the picture)
// turn with it, so the scrub, the markers and the hotspot moves work unchanged.

const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const size = Math.min(window.innerWidth, 1600) * Math.min(devicePixelRatio || 1, 2) > 1100 ? 'lg' : 'sm';
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
const ramp = (v, a, b) => clamp((v - a) / (b - a), 0, 1);
// The same ramp with eased ends (smoothstep), for fades that shouldn't start or stop abruptly.
const smooth = (v, a, b) => {
  const t = ramp(v, a, b);
  return t * t * (3 - 2 * t);
};

// ---- frame sets, loaded coarse-to-fine so any scroll position has something close
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
          img.onload = () => {
            set.frames[i] = img;
            listeners.forEach((fn) => fn());
            loadOne();
          };
          img.onerror = loadOne;
          img.src = base + m.pattern.replace('{size}', size).replace('{n}', String(i).padStart(3, '0'));
        };
        for (let k = 0; k < 6; k++) loadOne();
        // Sharp versions of the frames the camera rests on when zoomed in (optional).
        set.detail = new Map();
        set.loadDetail = () => {
          if (set.detailRequested || !m.detail) return;
          set.detailRequested = true;
          for (const f of m.detail.frames) {
            const img = new Image();
            img.decoding = 'async';
            img.onload = () => {
              set.detail.set(f, img);
              listeners.forEach((fn) => fn());
            };
            img.src = base + m.detail.pattern.replace('{n}', String(f).padStart(3, '0'));
          }
        };
        return set;
      }),
    nearest(i) {
      const { frames } = set;
      const N = frames.length;
      for (let d = 0; d < N; d++) {
        const a = frames[(i + d) % N];
        if (a) return a;
        const b = frames[(i - d + N) % N];
        if (b) return b;
      }
      return null;
    },
  };
  sets.set(base, set);
  return set;
}

// ---- placed points on a sequence
// A hotspot may give its spot as `point` (x, y % of the scene's first frame)
// instead of a tracked `track`. Its path through the other frames follows the
// ground: per frame, the frame-0 positions of the sequence's tracked points are
// mapped onto that frame's by a homography (four tracks in view), an affine
// transform (three) or a similarity (two), and the point goes through the same
// mapping. Aerial views are near enough to a plane for this to stay on its spot.

// Solve A·x = b (n×n) by Gaussian elimination with partial pivoting; null if singular.
function solve(A, b) {
  const n = b.length;
  const M = A.map((row, i) => [...row, b[i]]);
  for (let c = 0; c < n; c++) {
    let p = c;
    for (let r = c + 1; r < n; r++) if (Math.abs(M[r][c]) > Math.abs(M[p][c])) p = r;
    if (Math.abs(M[p][c]) < 1e-9) return null;
    [M[c], M[p]] = [M[p], M[c]];
    for (let r = 0; r < n; r++) {
      if (r === c) continue;
      const f = M[r][c] / M[c][c];
      for (let k = c; k <= n; k++) M[r][k] -= f * M[c][k];
    }
  }
  return M.map((row, i) => row[n] / row[i]);
}

// A mapping (x, y) => [x, y] taking each `from` point onto its `to` point.
function fitMapping(from, to) {
  if (from.length >= 4) {
    const A = [];
    const b = [];
    for (let i = 0; i < 4; i++) {
      const [x, y] = from[i];
      const [u, v] = to[i];
      A.push([x, y, 1, 0, 0, 0, -u * x, -u * y]);
      b.push(u);
      A.push([0, 0, 0, x, y, 1, -v * x, -v * y]);
      b.push(v);
    }
    const h = solve(A, b);
    if (h) return (x, y) => {
      const w = h[6] * x + h[7] * y + 1;
      return [(h[0] * x + h[1] * y + h[2]) / w, (h[3] * x + h[4] * y + h[5]) / w];
    };
  }
  if (from.length >= 3) {
    const A = [];
    const b = [];
    for (let i = 0; i < 3; i++) {
      const [x, y] = from[i];
      A.push([x, y, 1, 0, 0, 0], [0, 0, 0, x, y, 1]);
      b.push(to[i][0], to[i][1]);
    }
    const a = solve(A, b);
    if (a) return (x, y) => [a[0] * x + a[1] * y + a[2], a[3] * x + a[4] * y + a[5]];
  }
  if (from.length >= 2) {
    // Similarity: rotation, scale and shift from the first two pairs.
    const [[x1, y1], [x2, y2]] = from;
    const [[u1, v1], [u2, v2]] = to;
    const d = [x2 - x1, y2 - y1];
    const e = [u2 - u1, v2 - v1];
    const len = d[0] * d[0] + d[1] * d[1];
    if (len > 0) {
      const ca = (d[0] * e[0] + d[1] * e[1]) / len;
      const sa = (d[0] * e[1] - d[1] * e[0]) / len;
      return (x, y) => [u1 + ca * (x - x1) - sa * (y - y1), v1 + sa * (x - x1) + ca * (y - y1)];
    }
  }
  return null;
}

// Each placed point's path through the frames, added to m.hotspots under its track key.
function placePoints(m, spots) {
  const placed = Object.values(spots).filter((s) => s.point && !m.hotspots[s.track]);
  if (!placed.length) return;
  const tracks = Object.values(m.hotspots).filter((t) => t[0]);
  for (const s of placed) m.hotspots[s.track] = [];
  for (let f = 0; f < m.count; f++) {
    const pairs = tracks.filter((t) => t[f]);
    const map = fitMapping(pairs.map((t) => t[0]), pairs.map((t) => t[f]));
    for (const s of placed) {
      const p = [(s.point[0] / 100) * m.width, (s.point[1] / 100) * m.height];
      m.hotspots[s.track].push(map ? map(p[0], p[1]) : null);
    }
  }
}

// ---- a still picture standing in for a sequence
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
    nearest: () => set.still,
  };
  return set;
}

// ---- one scene
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

  // Media time of the video frame actually on screen (currentTime runs slightly ahead).
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
  if (!set.still) placePoints(m, spots);

  // Still scenes: the picture's turn at frame position fw (radians), the scale
  // that keeps it covering the stage at that angle, and the hotspots in picture px.
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

  // Video time <-> scene frame. Video frame k = render frame k*video.step;
  // scene frame f = render frame f*step.
  const perSecond = hasVideo ? (m.video.fps * m.video.step) / m.step : 0;
  // Not rounded: the scrub carries on from exactly where the (interpolated) video
  // is, the canvas showing the nearest frame, so the hand-over doesn't jump.
  const videoToFrame = (t) => (Math.floor(t * m.video.fps + 0.01) * (m.video.step / m.step)) % N;
  const frameToVideo = (f) => (((f % N) + N) % N) / perSecond;

  // ---- state
  let mode = hasVideo ? 'video' : 'scrub';
  let offset = Number(root.dataset.start) || 0; // frame at the start of the scroll range
  let current = offset; // smoothed frame position (float, unwrapped)
  let drawn = NaN; // frame position (float, wrapped) on the canvas; NaN = redraw
  let shownF = 0; // frame position currently shown (float, wrapped)
  let shownIndex = 0; // nearest whole frame to it
  let lastTarget = NaN; // scroll target at the previous tick, to tell when scrolling stops
  let override = null; // frame (float) set by a hotspot move instead of the scroll
  let ticking = false;

  // ---- layout
  let W = 0, H = 0, dpr = 1, scale = 1, ox = 0, oy = 0;
  let avoid = []; // text boxes markers must not cover (layout boxes, transforms ignored)
  const measureAvoid = () => {
    const area = root.querySelector('.scene__area');
    avoid = [...area.children].map((el) => ({
      x: area.offsetLeft + el.offsetLeft,
      y: area.offsetTop + el.offsetTop,
      w: el.offsetWidth,
      h: el.offsetHeight,
    }));
    // Other boxes on the stage the markers must not cover (a project scene's card).
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
    // cover-fit the frame (hotspot coordinates are in m.width x m.height space)
    scale = Math.max(W / m.width, H / m.height);
    ox = (W - m.width * scale) / 2;
    oy = (H - m.height * scale) / 2;
    measureAvoid();
    drawn = NaN;
    if (placeKey) applyCam(spotCam(placeKey));
    requestTick();
  };

  // Scroll into the scene in px, and the length of the Masterplan opening before the orbit's range.
  const scrolled = () => -root.getBoundingClientRect().top;
  const introLength = () => (hasIntro ? introEnd.offsetTop : 0);
  // [data-panel="zoom"]: the panel and shade come in as soon as the frame has
  // filled the screen, over the looping video, instead of where the orbit starts.
  const zoomEnd = root.querySelector('[data-zoom-end]');
  const panelFrom = () => (root.dataset.panel === 'zoom' && zoomEnd ? zoomEnd.offsetTop : introLength());
  const progress = () => {
    const intro = introLength();
    const range = root.offsetHeight - stage.clientHeight - intro;
    return range > 0 ? clamp((scrolled() - intro) / range, 0, 1) : 0;
  };
  const onScreen = () => {
    const r = root.getBoundingClientRect();
    return r.bottom > -50 && r.top < window.innerHeight + 50;
  };

  // Put frame position f (float, unwrapped) on the canvas; returns it wrapped to 0..N.
  function showFrame(f) {
    const fw = ((f % N) + N) % N;
    shownF = fw;
    shownIndex = Math.round(fw) % N;
    // A sequence shows whole frames, so it only redraws when the nearest one changes; a still turns smoothly.
    const key = set.still ? fw : shownIndex;
    if (!(Math.abs(key - drawn) < 0.002) && draw(fw)) {
      drawn = key;
      // Without a video the Masterplan opening shows its picture until the orbit's range (see tick).
      if (!(hasIntro && !hasVideo)) root.classList.add('is-scrub');
    }
    return fw;
  }

  // Draw frame position fw through the camera: always the nearest whole frame,
  // never two laid over each other. The frames are 3° apart, so a blend of two
  // showed buildings near the camera twice (a ghost) while scrolling; at 120
  // frames a turn the orbit still flows. The zoom happens here, from the source
  // image, so zoomed views keep the source's resolution (a CSS zoom would enlarge
  // the screen-sized canvas instead). Resting on a frame while zoomed, its detail
  // frame is used if there is one.
  function draw(fw) {
    const t = fw - Math.floor(fw);
    const near = Math.round(fw) % N;
    const whole = t < 0.01 || t > 0.99;
    const img = (cam && whole && set.detail?.get(near)) || set.frames[near] || set.nearest(near);
    if (!img) return false;
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

  // A tracked point at frame position fw, between the two nearest frames' points.
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
      const p = visible && trackAt(spots[el.dataset.spot].track, fw);
      const x = p && ox + p[0] * scale;
      const y = p && oy + p[1] * scale;
      const show = p && x > 24 && y > 80 && x < W - 24 && y < H - 24 && !covered(x, y);
      el.hidden = !show;
      if (!show) continue;
      // Near the right edge the label goes on the left of the dot. When flipped the
      // dot sits at the element's right edge, so shift left by the element's width.
      const flip = x + Number((el.dataset.w ||= el.offsetWidth)) > W - 16;
      el.classList.toggle('spot--flip', flip);
      const left = flip ? x - el.offsetWidth : x;
      el.style.transform = `translate3d(${left.toFixed(1)}px, ${y.toFixed(1)}px, 0)`;
    }
  }

  // ---- go to a location
  // Each hotspot has a default angle (`frame` in the scene). Opening it
  // fast-forwards the orbit to that angle, the real camera path turning, while a
  // camera over the canvas moves in and zooms onto the hotspot, following it
  // frame by frame as it moves across the turning view. Moving to another
  // hotspot turns the orbit to its angle while the camera pulls back a little and
  // follows across; closing turns back to the scroll position while zooming out.
  // The camera is [cx, cy, w]: the point at the screen centre and the visible
  // width, in stage pixels ([W/2, H/2, W] = the plain orbit).
  const ZOOM = 2.2; // how close the camera gets on a location
  let placeKey = null; // open hotspot, or null while orbiting
  let placeY = 0; // scroll position when it opened
  let cam = null; // null = untouched orbit
  let run = 0; // id of the running move; starting a new one cancels it
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
  // A hotspot's position on the stage at a frame position (null if not in it).
  const posAt = (key, fw) => {
    const p = trackAt(spots[key].track, fw);
    return p ? [ox + p[0] * scale, oy + p[1] * scale] : null;
  };
  const spotCam = (key) => {
    const p = posAt(key, shownF) || [W / 2, H / 2];
    return clampCam([p[0], p[1], W / ZOOM]);
  };

  // Set the camera and redraw the frame on screen through it.
  const applyCam = (c) => {
    cam = c;
    if (draw(shownF)) drawn = shownF;
  };

  const ease = (k) => (k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2);
  const lerp = (a, b, t) => a + (b - a) * t;
  // Run fn(0..1) over ms; resolves false if another move took over.
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

  // One move: turn the orbit from where it is to `toFrame` while the camera goes
  // from wherever it is (following `fromKey` if leaving a hotspot) to `toKey`
  // (or back to the plain orbit when there is none).
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
      override = f0 + d * e; // the scroll no longer decides the angle until closed
      const fw = wrap(override);
      const a = fromKey ? (lastA = posAt(fromKey, fw) || lastA) : null;
      const b = toKey ? (lastB = posAt(toKey, fw) || lastB) : null;
      const start = a || [c0[0], c0[1]];
      const end = b || [W / 2, H / 2];
      // Going in: travel leads, zoom follows. Between hotspots: zoom dips half way.
      const pan = toKey && !fromKey ? ease(clamp(t / 0.8, 0, 1)) : e;
      let ls;
      if (fromKey && toKey) ls = lerp(l0, l1, e) - 0.55 * Math.log(ZOOM) * Math.sin(Math.PI * t);
      else if (toKey) ls = lerp(l0, l1, ease(clamp((t - 0.15) / 0.85, 0, 1)));
      else ls = lerp(l0, l1, e);
      cam = clampCam([lerp(start[0], end[0], pan), lerp(start[1], end[1], pan), W / Math.exp(Math.max(0, ls))]);
      drawn = NaN; // camera moved: redraw even if the frame is the same
      showFrame(override);
    });
    return { done, ms };
  }

  // A project scene shows the open hotspot's picture: on its card (the last one
  // opened stays there), or in the location panel.
  const picture = root.querySelector('[data-scene-card]');
  const panelImg = root.querySelector('.scene__loc-img');
  const figure = root.querySelector('[data-place-figure]');
  // Its pictures are fetched up front, so an open point's shows at once instead
  // of waiting behind the frames and video still coming in.
  if (figure) {
    for (const { image } of Object.values(spots)) {
      if (image) Object.assign(new Image(), { fetchPriority: 'high', src: image });
    }
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
      // Ahead of the frames still coming in, so it doesn't wait behind them.
      if (image) Object.assign(figure.querySelector('img'), { fetchPriority: 'high', src: image, alt: title });
    }
    place.title.focus({ preventScroll: true });
  };

  // From the looping video to the frames, at the frame the video is showing.
  const handOver = () => {
    offset = videoToFrame(shownTime ?? video.currentTime);
    current = offset;
    drawn = NaN;
    mode = 'scrub';
    video.pause();
  };

  async function openPlace(key) {
    // With the panel in over the video, a point opened there takes over from it first.
    if (mode === 'video' && hasVideo && root.dataset.panel === 'zoom') {
      handOver();
      showFrame(current);
    }
    if (mode !== 'scrub' || !spots[key] || key === placeKey) return;
    set.loadDetail?.();
    const id = ++run;
    const from = placeKey;
    placeKey = key;
    placeY = window.scrollY;
    place.panel.hidden = true;
    if (figure) figure.hidden = true;
    flying();
    root.classList.add('is-place');
    const toFrame = spots[key].frame ?? shownIndex;
    const { done, ms } = travel(id, { fromKey: from, toKey: key, toFrame });
    const panelAt = setTimeout(() => id === run && showPanel(key), ms * 0.75);
    if (!(await done)) clearTimeout(panelAt);
  }

  async function closePlace() {
    if (!placeKey) return;
    const id = ++run;
    const key = placeKey;
    placeKey = null;
    place.panel.hidden = true;
    if (figure) figure.hidden = true;
    flying();
    if (root.contains(document.activeElement) || document.activeElement === document.body) {
      root.querySelector(`[data-spot-open="${key}"]`)?.focus({ preventScroll: true });
    }
    // Turn back to the angle the scroll position calls for.
    const { done } = travel(id, { fromKey: key, toFrame: Math.round(current) });
    setTimeout(() => id === run && root.classList.remove('is-place'), 500);
    if (!(await done)) return;
    override = null;
    root.classList.remove('is-place');
    applyCam(null);
  }

  const stepPlace = (d) => {
    const at = placeKey ?? keys[0];
    openPlace(keys[(keys.indexOf(at) + d + keys.length) % keys.length]);
  };

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && placeKey && !document.querySelector('dialog[open]')) closePlace();
  });

  function tick() {
    ticking = false;
    if (placeKey && Math.abs(window.scrollY - placeY) > 120) closePlace(); // scrolling on returns to the orbit
    if (!onScreen()) return;
    const p = progress();

    // Phases. Masterplan scene: once the frame has filled the stage, the panel comes in,
    // eased, over the next third of a screen.
    const intro = introLength();
    const from = panelFrom();
    const areaIn = hasIntro ? smooth(scrolled(), from, from + H * 0.3) : 1;
    root.style.setProperty('--p-area', areaIn.toFixed(3));
    root.style.setProperty('--v-area', areaIn > 0 ? 'visible' : 'hidden');
    root.style.setProperty('--progress', p.toFixed(4));
    if (hasIntro && !hasVideo) root.classList.toggle('is-scrub', p > 0);

    if (hasVideo && p <= 0 && mode === 'scrub' && !placeKey && override === null && Math.abs(current - offset) < 0.05) {
      // Back at the start of the range and settled: hand the orbit back to the video at the same frame.
      mode = 'video';
      shownTime = null;
      video.currentTime = frameToVideo(current);
      root.classList.remove('is-scrub');
      placeMarkers(0, false);
    }

    if (mode === 'video' && video.paused && !reduced) video.play().catch(() => {});

    if (hasVideo && p > 0 && mode === 'video') handOver();

    if (mode !== 'scrub') return;

    // One full orbit over the scroll range. Lenis already eases the scroll, so the
    // frame follows it closely (a light 0.25 catch-up, not a second heavy lag).
    // Once the scroll stops, the orbit glides on to the nearest whole frame so the
    // picture at rest is one sharp frame, not a blend of two.
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

  // The canvas under the video holds a frame from the start (the Masterplan layer
  // covers it), so taking over only changes its picture: showing it for the first
  // time at the hand-over made the browser set it up on screen right then, and the
  // page stalled for a moment.
  let warm = !hasVideo;
  const warmUp = () => {
    if (!warm && draw(offset)) warm = true;
  };
  // While the video plays, the canvas follows it a frame behind the scenes, so the
  // frames the hand-over draws are already decoded instead of decoded right then.
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

  // Redraw as sharper frames arrive.
  set.listeners.add(() => {
    warmUp();
    if (mode === 'scrub') {
      drawn = NaN;
      requestTick();
    }
  });
  // Detail frames are only needed once someone heads for a hotspot.
  root.addEventListener('pointerover', (e) => e.target.closest('[data-spot], [data-spot-open]') && set.loadDetail?.(), { passive: true });
  root.addEventListener('focusin', (e) => e.target.closest('[data-spot-open]') && set.loadDetail?.());
  window.addEventListener('scroll', requestTick, { passive: true });
  window.addEventListener('resize', () => {
    resize();
    if (mode === 'video') draw(offset);
  });
  resize();
  warmUp();

  // Pause the video when the scene is off screen.
  if (hasVideo) {
    new IntersectionObserver(([e]) => {
      if (mode === 'video' && !e.isIntersecting) video.pause();
      else requestTick();
    }).observe(root);
  }
}

// Scenes start when they come within a screen or so.
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
