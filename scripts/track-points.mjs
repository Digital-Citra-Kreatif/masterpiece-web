// Lacak titik scene

import sharp from 'sharp';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const PATCH = 16; // Setengah ukuran blok
const SEARCH = 40; // Jendela pencarian
const MIN_MATCH = 0.3; // Batas korelasi
const OUTLIER = 6; // Lonjakan salah cocok (px)
const SIGMA = 1.2; // Penghalusan (frame)

const dir = process.argv[2];
const manifest = JSON.parse(readFileSync(path.join(dir, 'manifest.json'), 'utf8'));
const { points } = JSON.parse(readFileSync(0, 'utf8'));
const N = manifest.count;

const frames = [];
for (let i = 0; i < N; i++) {
  const file = path.join(dir, manifest.pattern.replace('{size}', 'lg').replace('{n}', String(i).padStart(3, '0')));
  const { data, info } = await sharp(file).greyscale().raw().toBuffer({ resolveWithObject: true });
  frames.push({ data, w: info.width, h: info.height });
}
const W = frames[0].w;
const H = frames[0].h;

// Blok tanpa rerata
function block(frame, cx, cy) {
  const n = PATCH * 2 + 1;
  const out = new Float32Array(n * n);
  let k = 0;
  let mean = 0;
  for (let y = cy - PATCH; y <= cy + PATCH; y++) {
    for (let x = cx - PATCH; x <= cx + PATCH; x++) {
      const v = frame.data[Math.min(H - 1, Math.max(0, y)) * frame.w + Math.min(W - 1, Math.max(0, x))];
      out[k++] = v;
      mean += v;
    }
  }
  mean /= out.length;
  for (let i = 0; i < out.length; i++) out[i] -= mean;
  return out;
}

function correlation(frame, tpl, cx, cy) {
  const b = block(frame, cx, cy);
  let s = 0;
  let a2 = 0;
  let b2 = 0;
  for (let i = 0; i < b.length; i++) {
    s += b[i] * tpl[i];
    a2 += b[i] * b[i];
    b2 += tpl[i] * tpl[i];
  }
  return s / Math.sqrt(a2 * b2 + 1e-6);
}

// Cari kecocokan terbaik
function match(frame, tpl, px, py) {
  let best = -Infinity;
  let bx = px;
  let by = py;
  for (let dy = -SEARCH; dy <= SEARCH; dy += 2) {
    for (let dx = -SEARCH; dx <= SEARCH; dx += 2) {
      const c = correlation(frame, tpl, px + dx, py + dy);
      if (c > best) [best, bx, by] = [c, px + dx, py + dy];
    }
  }
  let [rx, ry] = [bx, by];
  for (let dy = -2; dy <= 2; dy++) {
    for (let dx = -2; dx <= 2; dx++) {
      const c = correlation(frame, tpl, bx + dx, by + dy);
      if (c > best) [best, rx, ry] = [c, bx + dx, by + dy];
    }
  }
  // Puncak sub-piksel
  const peak = (l, r) => {
    const d = l - 2 * best + r;
    return d < 0 ? Math.max(-0.5, Math.min(0.5, (l - r) / (2 * d))) : 0;
  };
  const sx = peak(correlation(frame, tpl, rx - 1, ry), correlation(frame, tpl, rx + 1, ry));
  const sy = peak(correlation(frame, tpl, rx, ry - 1), correlation(frame, tpl, rx, ry + 1));
  return [rx, ry, best, rx + sx, ry + sy];
}

const inside = (x, y) => x > PATCH && y > PATCH && x < W - PATCH && y < H - PATCH;

function run(start, order) {
  const out = new Array(N).fill(null);
  let [x, y] = start;
  let tpl = block(frames[order[0]], x, y);
  out[order[0]] = [x, y];
  for (let s = 1; s < order.length; s++) {
    const frame = frames[order[s]];
    const [nx, ny, c, fx, fy] = match(frame, tpl, x, y);
    if (!inside(nx, ny) || c < MIN_MATCH) break;
    [x, y] = [nx, ny];
    out[order[s]] = [fx, fy];
    tpl = block(frame, x, y);
  }
  return out;
}

const median = (values) => values.sort((a, b) => a - b)[values.length >> 1];
const wrap = (i) => ((i % N) + N) % N;

// Buang lonjakan, lalu haluskan (orbit melingkar)
function clean(track) {
  const near = (list, i, reach) => {
    const out = [];
    for (let d = -reach; d <= reach; d++) {
      const q = list[wrap(i + d)];
      if (q) out.push([q, d]);
    }
    return out;
  };
  const steady = track.map((p, i) => {
    if (!p) return null;
    const around = near(track, i, 3).map(([q]) => q);
    const mid = [median(around.map((q) => q[0])), median(around.map((q) => q[1]))];
    return Math.hypot(p[0] - mid[0], p[1] - mid[1]) > OUTLIER ? mid : p;
  });
  return steady.map((p, i) => {
    if (!p) return null;
    let [sx, sy, sw] = [0, 0, 0];
    for (const [q, d] of near(steady, i, 3)) {
      const w = Math.exp(-(d * d) / (2 * SIGMA * SIGMA));
      [sx, sy, sw] = [sx + w * q[0], sy + w * q[1], sw + w];
    }
    return [Math.round((sx / sw) * 10) / 10, Math.round((sy / sw) * 10) / 10];
  });
}

const forward = [...Array(N).keys()];
const backward = [0, ...forward.slice(1).reverse()];
const tracks = {};
for (const [key, start] of Object.entries(points)) {
  const p = [Math.round(start[0]), Math.round(start[1])];
  const fw = run(p, forward);
  const bw = run(p, backward);
  // Rentang tumpang tindih
  let lastForward = 0;
  while (lastForward + 1 < N && fw[lastForward + 1]) lastForward++;
  let firstBackward = N;
  while (firstBackward - 1 > 0 && bw[firstBackward - 1]) firstBackward--;
  const span = lastForward - firstBackward + 2;
  tracks[key] = clean(fw.map((a, i) => {
    const b = bw[i];
    if (i > 0 && a && b) {
      // Pindah bertahap, tanpa lompatan
      const t = (i - firstBackward + 1) / span;
      return [a[0] * (1 - t) + b[0] * t, a[1] * (1 - t) + b[1] * t];
    }
    return a || b;
  }));
}

process.stdout.write(JSON.stringify(tracks));
