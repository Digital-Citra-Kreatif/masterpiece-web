// Lacak titik scene

import sharp from 'sharp';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const PATCH = 16; // Setengah ukuran blok
const SEARCH = 40; // Jendela pencarian
const MIN_MATCH = 0.3; // Batas korelasi

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
  return [rx, ry, best];
}

const inside = (x, y) => x > PATCH && y > PATCH && x < W - PATCH && y < H - PATCH;

function run(start, order) {
  const out = new Array(N).fill(null);
  let [x, y] = start;
  let tpl = block(frames[order[0]], x, y);
  out[order[0]] = [x, y];
  for (let s = 1; s < order.length; s++) {
    const frame = frames[order[s]];
    const [nx, ny, c] = match(frame, tpl, x, y);
    if (!inside(nx, ny) || c < MIN_MATCH) break;
    [x, y] = [nx, ny];
    out[order[s]] = [x, y];
    tpl = block(frame, x, y);
  }
  return out;
}

const forward = [...Array(N).keys()];
const backward = [0, ...forward.slice(1).reverse()];
const tracks = {};
for (const [key, start] of Object.entries(points)) {
  const p = [Math.round(start[0]), Math.round(start[1])];
  const fw = run(p, forward);
  const bw = run(p, backward);
  tracks[key] = fw.map((a, i) => {
    const b = bw[i];
    if (a && b) {
      const t = i / N;
      return [Math.round(a[0] * (1 - t) + b[0] * t), Math.round(a[1] * (1 - t) + b[1] * t)];
    }
    return a || b;
  });
}

process.stdout.write(JSON.stringify(tracks));
