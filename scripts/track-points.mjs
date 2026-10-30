// Lacak titik orbit

import sharp from 'sharp';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const LEVELS = 4; // Tingkat piramida
const WIN = 10; // Setengah jendela aliran
const ITER = 12; // Iterasi per tingkat
const GRID = [24, 14]; // Sel sudut
const FB = 1; // Batas maju-mundur (px)
const RANSAC = 600; // Percobaan
const INLIER = 2; // Batas inlier (px)
const PATCH = 12; // Setengah ukuran pola
const SEARCH = 8; // Koreksi maks per frame (px)
const PULL = 0.003; // Tarikan ke prediksi
const MIN_MATCH = 0.6; // Batas korelasi
const TEXTURE = 150; // Pola terlalu polos
const OUTLIER = 6; // Lonjakan salah cocok (px)
const SIGMA = 1.2; // Penghalusan (frame)

const dir = process.argv[2];
const manifest = JSON.parse(readFileSync(path.join(dir, 'manifest.json'), 'utf8'));
const { points } = JSON.parse(readFileSync(0, 'utf8'));
const N = manifest.count;
const file = (size, i) => path.join(dir, manifest.pattern.replace('{size}', size).replace('{n}', String(i).padStart(3, '0')));

// Piramida abu-abu
function pyramid(data, w, h) {
  const levels = [{ data: Float32Array.from(data), w, h }];
  for (let l = 1; l < LEVELS; l++) {
    const prev = levels[l - 1];
    const nw = prev.w >> 1;
    const nh = prev.h >> 1;
    const out = new Float32Array(nw * nh);
    for (let y = 0; y < nh; y++) {
      for (let x = 0; x < nw; x++) {
        const i = 2 * y * prev.w + 2 * x;
        out[y * nw + x] = (prev.data[i] + prev.data[i + 1] + prev.data[i + prev.w] + prev.data[i + prev.w + 1]) / 4;
      }
    }
    levels.push({ data: out, w: nw, h: nh });
  }
  for (const level of levels) {
    const { data: d, w: lw, h: lh } = level;
    level.gx = new Float32Array(lw * lh);
    level.gy = new Float32Array(lw * lh);
    for (let y = 1; y < lh - 1; y++) {
      for (let x = 1; x < lw - 1; x++) {
        const i = y * lw + x;
        level.gx[i] = (d[i + 1] - d[i - 1]) / 2;
        level.gy[i] = (d[i + lw] - d[i - lw]) / 2;
      }
    }
  }
  return levels;
}

const sample = (arr, w, h, x, y) => {
  if (x < 0 || y < 0 || x > w - 2 || y > h - 2) return NaN;
  const x0 = x | 0;
  const y0 = y | 0;
  const fx = x - x0;
  const fy = y - y0;
  const i = y0 * w + x0;
  return (arr[i] * (1 - fx) + arr[i + 1] * fx) * (1 - fy) + (arr[i + w] * (1 - fx) + arr[i + w + 1] * fx) * fy;
};

// Sudut Shi-Tomasi per sel
function corners(level) {
  const { gx, gy, w, h } = level;
  const [cols, rows] = GRID;
  const out = [];
  const R = 3;
  for (let cy = 0; cy < rows; cy++) {
    for (let cx = 0; cx < cols; cx++) {
      let best = 0;
      let bp = null;
      const x0 = Math.max(WIN + 4, Math.floor((cx * w) / cols));
      const x1 = Math.min(w - WIN - 4, Math.floor(((cx + 1) * w) / cols));
      const y0 = Math.max(WIN + 4, Math.floor((cy * h) / rows));
      const y1 = Math.min(h - WIN - 4, Math.floor(((cy + 1) * h) / rows));
      for (let y = y0; y < y1; y += 2) {
        for (let x = x0; x < x1; x += 2) {
          let a = 0, b = 0, c = 0;
          for (let dy = -R; dy <= R; dy++) {
            for (let dx = -R; dx <= R; dx++) {
              const i = (y + dy) * w + x + dx;
              a += gx[i] * gx[i];
              b += gx[i] * gy[i];
              c += gy[i] * gy[i];
            }
          }
          const score = (a + c) / 2 - Math.sqrt(((a - c) / 2) ** 2 + b * b);
          if (score > best) [best, bp] = [score, [x, y]];
        }
      }
      if (bp && best > 50) out.push(bp);
    }
  }
  return out;
}

// Aliran Lucas-Kanade piramida
function flow(A, B, p) {
  let gx = 0;
  let gy = 0;
  for (let l = LEVELS - 1; l >= 0; l--) {
    const a = A[l];
    const b = B[l];
    const s = 1 / 2 ** l;
    const px = p[0] * s;
    const py = p[1] * s;
    let sxx = 0, sxy = 0, syy = 0;
    const tpl = [];
    for (let dy = -WIN; dy <= WIN; dy++) {
      for (let dx = -WIN; dx <= WIN; dx++) {
        const ix = sample(a.gx, a.w, a.h, px + dx, py + dy);
        const iy = sample(a.gy, a.w, a.h, px + dx, py + dy);
        const v = sample(a.data, a.w, a.h, px + dx, py + dy);
        if (Number.isNaN(ix) || Number.isNaN(v)) return null;
        tpl.push([dx, dy, ix, iy, v]);
        sxx += ix * ix;
        sxy += ix * iy;
        syy += iy * iy;
      }
    }
    const det = sxx * syy - sxy * sxy;
    if (det < 1e-3) return null;
    let vx = 0;
    let vy = 0;
    for (let k = 0; k < ITER; k++) {
      let bx = 0;
      let by = 0;
      for (const [dx, dy, ix, iy, v] of tpl) {
        const w = sample(b.data, b.w, b.h, px + gx + vx + dx, py + gy + vy + dy);
        if (Number.isNaN(w)) return null;
        const e = v - w;
        bx += e * ix;
        by += e * iy;
      }
      const ux = (syy * bx - sxy * by) / det;
      const uy = (sxx * by - sxy * bx) / det;
      vx += ux;
      vy += uy;
      if (ux * ux + uy * uy < 1e-4) break;
    }
    if (l > 0) {
      gx = 2 * (gx + vx);
      gy = 2 * (gy + vy);
    } else {
      gx += vx;
      gy += vy;
    }
  }
  return [p[0] + gx, p[1] + gy];
}

// Homografi: h33 = 1
function solve(M, v) {
  const n = v.length;
  const a = M.map((row, i) => [...row, v[i]]);
  for (let c = 0; c < n; c++) {
    let p = c;
    for (let r = c + 1; r < n; r++) if (Math.abs(a[r][c]) > Math.abs(a[p][c])) p = r;
    if (Math.abs(a[p][c]) < 1e-12) return null;
    [a[c], a[p]] = [a[p], a[c]];
    for (let r = 0; r < n; r++) {
      if (r === c) continue;
      const f = a[r][c] / a[c][c];
      for (let k = c; k <= n; k++) a[r][k] -= f * a[c][k];
    }
  }
  return a.map((row, i) => row[n] / row[i]);
}

function fit(pairs) {
  const M = Array.from({ length: 8 }, () => new Array(8).fill(0));
  const v = new Array(8).fill(0);
  for (const [[x, y], [u, w]] of pairs) {
    const rows = [
      [[x, y, 1, 0, 0, 0, -u * x, -u * y], u],
      [[0, 0, 0, x, y, 1, -w * x, -w * y], w],
    ];
    for (const [r, t] of rows) {
      for (let i = 0; i < 8; i++) {
        v[i] += r[i] * t;
        for (let j = 0; j < 8; j++) M[i][j] += r[i] * r[j];
      }
    }
  }
  const h = solve(M, v);
  return h && [...h, 1];
}

const apply = (H, [x, y]) => {
  const z = H[6] * x + H[7] * y + H[8];
  return [(H[0] * x + H[1] * y + H[2]) / z, (H[3] * x + H[4] * y + H[5]) / z];
};
const mul = (A, B) => {
  const C = new Array(9);
  for (let r = 0; r < 3; r++) for (let c = 0; c < 3; c++) C[r * 3 + c] = A[r * 3] * B[c] + A[r * 3 + 1] * B[3 + c] + A[r * 3 + 2] * B[6 + c];
  return C.map((x) => x / C[8]);
};
const inv = (m) => {
  const [a, b, c, d, e, f, g, h, i] = m;
  const A = e * i - f * h, B = -(d * i - f * g), C = d * h - e * g;
  const det = a * A + b * B + c * C;
  const out = [A, -(b * i - c * h), b * f - c * e, B, a * i - c * g, -(a * f - c * d), C, -(a * h - b * g), a * e - b * d].map((x) => x / det);
  return out.map((x) => x / out[8]);
};

// Normalisasi koordinat
function homography(pairs) {
  const norm = (pts) => {
    const mx = pts.reduce((s, p) => s + p[0], 0) / pts.length;
    const my = pts.reduce((s, p) => s + p[1], 0) / pts.length;
    const d = pts.reduce((s, p) => s + Math.hypot(p[0] - mx, p[1] - my), 0) / pts.length || 1;
    const k = Math.SQRT2 / d;
    return [k, 0, -k * mx, 0, k, -k * my, 0, 0, 1];
  };
  const T1 = norm(pairs.map((p) => p[0]));
  const T2 = norm(pairs.map((p) => p[1]));
  const H = fit(pairs.map(([a, b]) => [apply(T1, a), apply(T2, b)]));
  return H && mul(inv(T2), mul(H, T1));
}

function ransac(pairs) {
  let best = [];
  let seed = 7;
  const rand = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
  for (let k = 0; k < RANSAC; k++) {
    const pick = new Set();
    while (pick.size < 4) pick.add(Math.floor(rand() * pairs.length));
    const H = homography([...pick].map((i) => pairs[i]));
    if (!H) continue;
    const inliers = pairs.filter(([a, b]) => {
      const p = apply(H, a);
      return Math.hypot(p[0] - b[0], p[1] - b[1]) < INLIER;
    });
    if (inliers.length > best.length) best = inliers;
  }
  return { H: homography(best), inliers: best.length, total: pairs.length };
}

// Bidang tanah antarframe
function between(A, B) {
  const pairs = [];
  for (const p of corners(A[0])) {
    const q = flow(A, B, p);
    if (!q) continue;
    const back = flow(B, A, q);
    if (!back || Math.hypot(back[0] - p[0], back[1] - p[1]) > FB) continue;
    pairs.push([p, q]);
  }
  return ransac(pairs);
}

const frames = [];
let W = 0;
let Hh = 0;
for (let i = 0; i < N; i++) {
  const { data, info } = await sharp(file('sm', i)).greyscale().raw().toBuffer({ resolveWithObject: true });
  [W, Hh] = [info.width, info.height];
  frames.push(pyramid(data, W, Hh));
}
const k = W / manifest.width; // lg ke sm

// Langkah antarframe
const steps = [];
for (let i = 0; i < N; i++) {
  const step = between(frames[i], frames[(i + 1) % N]);
  if (process.env.TRACK_DEBUG) process.stderr.write(`${i}->${(i + 1) % N} ${step.inliers}/${step.total}\n`);
  steps.push(step.H ?? [1, 0, 0, 0, 1, 0, 0, 0, 1]);
}
// Frame besar koreksi
const big = [];
for (let i = 0; i < N; i++) {
  const { data, info } = await sharp(file('lg', i)).greyscale().raw().toBuffer({ resolveWithObject: true });
  big.push({ data, w: info.width, h: info.height });
}
const BW = big[0].w;
const BH = big[0].h;

// Blok tanpa rerata
function block(frame, cx, cy) {
  const n = PATCH * 2 + 1;
  const out = new Float32Array(n * n);
  let k2 = 0;
  let mean = 0;
  for (let y = cy - PATCH; y <= cy + PATCH; y++) {
    for (let x = cx - PATCH; x <= cx + PATCH; x++) {
      const v = frame.data[y * frame.w + x];
      out[k2++] = v;
      mean += v;
    }
  }
  mean /= out.length;
  let norm = 0;
  for (let i = 0; i < out.length; i++) {
    out[i] -= mean;
    norm += out[i] * out[i];
  }
  out.norm = Math.sqrt(norm) + 1e-6;
  return out;
}

const ncc = (frame, tpl, cx, cy) => {
  const b = block(frame, cx, cy);
  let s = 0;
  for (let i = 0; i < b.length; i++) s += b[i] * tpl[i];
  return s / (b.norm * tpl.norm);
};

const usable = ([x, y]) => x > PATCH + SEARCH + 1 && y > PATCH + SEARCH + 1 && x < BW - PATCH - SEARCH - 2 && y < BH - PATCH - SEARCH - 2;
// Homografi sm di lg
const move = (H, p) => {
  const q = apply(H, [p[0] * k, p[1] * k]);
  return [q[0] / k, q[1] / k];
};

// Prediksi lalu koreksi
function pass(start, order, hop) {
  const out = new Array(N);
  let x = start;
  out[order[0]] = x;
  let tpl = usable(x) ? block(big[order[0]], Math.round(x[0]), Math.round(x[1])) : null;
  for (let s = 1; s < order.length; s++) {
    const cur = order[s];
    const pred = move(hop(order[s - 1], cur), x);
    let next = pred;
    if (tpl && tpl.norm > TEXTURE && usable(pred)) {
      const frame = big[cur];
      const px = Math.round(pred[0]);
      const py = Math.round(pred[1]);
      const score = (cx, cy) => ncc(frame, tpl, cx, cy) - PULL * ((cx - pred[0]) ** 2 + (cy - pred[1]) ** 2);
      let best = -Infinity;
      let bx = px;
      let by = py;
      let bc = 0;
      for (let dy = -SEARCH; dy <= SEARCH; dy++) {
        for (let dx = -SEARCH; dx <= SEARCH; dx++) {
          const sc = score(px + dx, py + dy);
          if (sc > best) {
            best = sc;
            bx = px + dx;
            by = py + dy;
          }
        }
      }
      bc = ncc(frame, tpl, bx, by);
      if (bc >= MIN_MATCH) {
        // Puncak sub-piksel
        const peak = (l, r) => {
          const d = l - 2 * best + r;
          return d < 0 ? Math.max(-0.5, Math.min(0.5, (l - r) / (2 * d))) : 0;
        };
        next = [bx + peak(score(bx - 1, by), score(bx + 1, by)), by + peak(score(bx, by - 1), score(bx, by + 1))];
      }
    }
    x = next;
    out[cur] = x;
    tpl = usable(x) ? block(big[cur], Math.round(x[0]), Math.round(x[1])) : null;
  }
  return out;
}

const median = (values) => values.sort((a, b) => a - b)[values.length >> 1];
const wrap = (i) => ((i % N) + N) % N;

// Buang lonjakan, lalu haluskan (orbit melingkar)
function clean(track) {
  const near = (i, reach, list) => {
    const out = [];
    for (let d = -reach; d <= reach; d++) out.push([list[wrap(i + d)], d]);
    return out;
  };
  const steady = track.map((p, i) => {
    const around = near(i, 3, track).map(([q]) => q);
    const mid = [median(around.map((q) => q[0])), median(around.map((q) => q[1]))];
    return Math.hypot(p[0] - mid[0], p[1] - mid[1]) > OUTLIER ? mid : p;
  });
  return steady.map((p, i) => {
    let [sx, sy, sw] = [0, 0, 0];
    for (const [q, d] of near(i, 3, steady)) {
      const w = Math.exp(-(d * d) / (2 * SIGMA * SIGMA));
      [sx, sy, sw] = [sx + w * q[0], sy + w * q[1], sw + w];
    }
    return [sx / sw, sy / sw];
  });
}

const order = [...Array(N).keys()];
const back = [0, ...order.slice(1).reverse()];
const inside = ([x, y]) => x >= 0 && y >= 0 && x <= manifest.width && y <= manifest.height;
const tracks = {};
for (const [key, start] of Object.entries(points)) {
  const fw = pass(start, order, (a) => steps[a]);
  const bw = pass(start, back, (a, b) => inv(steps[b]));
  // Gabung maju-mundur
  const merged = fw.map((a, i) => {
    const t = i / N;
    return [a[0] * (1 - t) + bw[i][0] * t, a[1] * (1 - t) + bw[i][1] * t];
  });
  tracks[key] = clean(merged).map((q) => (inside(q) ? [Math.round(q[0] * 10) / 10, Math.round(q[1] * 10) / 10] : null));
}

process.stdout.write(JSON.stringify(tracks));
