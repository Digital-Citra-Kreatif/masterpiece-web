// Placeholder hotspot positions for the area/cluster turntables.
//
// The client (or whoever builds the WebRotate package in SpotEditor) will supply
// real hotspot positions. Until then this script follows a few landmarks through
// the raw 600-frame aerial sequence with simple block matching, so the demo
// hotspots stay pinned to real buildings while the sequence rotates.
//
// Usage: node scripts/track-hotspots.mjs
// Output: scripts/data/kawasan-tracks.json  ({ frameCount, width, height, points: { id: [[x,y]|null, ...] } })

import sharp from 'sharp';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const SRC_DIR = 'assets/images_bev_kawasan';
const PREFIX = 'BEV Kawasan Ruko_';
const FRAME_COUNT = 600;
const OUT = 'scripts/data/kawasan-tracks.json';

// Landmark positions on frame 0 (1280x720).
const LANDMARKS = {
  plaza: [640, 415], // gazebo between the shophouse blocks
  commercial: [760, 155], // round commercial building
  offices: [570, 88], // glass office towers
  lakeside: [855, 432], // lakeside pavilion
};

const PATCH = 18; // half-size of the matched block
const SEARCH = 18; // half-size of the per-frame search window
const EDGE = 40; // treat a point this close to the frame edge as lost

const frameFile = (i) => path.join(SRC_DIR, `${PREFIX}${String(i).padStart(5, '0')}.jpg`);

async function loadGray(i) {
  const { data, info } = await sharp(frameFile(i)).greyscale().raw().toBuffer({ resolveWithObject: true });
  return { data, w: info.width, h: info.height };
}

function extract(frame, cx, cy) {
  const n = PATCH * 2 + 1;
  const out = new Float32Array(n * n);
  let k = 0;
  for (let y = cy - PATCH; y <= cy + PATCH; y++)
    for (let x = cx - PATCH; x <= cx + PATCH; x++) out[k++] = frame.data[y * frame.w + x];
  return out;
}

function match(frame, tpl, px, py) {
  let best = Infinity, bx = px, by = py;
  for (let dy = -SEARCH; dy <= SEARCH; dy++) {
    for (let dx = -SEARCH; dx <= SEARCH; dx++) {
      const cx = px + dx, cy = py + dy;
      let ssd = 0, k = 0;
      for (let y = cy - PATCH; y <= cy + PATCH && ssd < best; y++) {
        const row = y * frame.w;
        for (let x = cx - PATCH; x <= cx + PATCH; x++) {
          const d = frame.data[row + x] - tpl[k++];
          ssd += d * d;
        }
      }
      if (ssd < best) { best = ssd; bx = cx; by = cy; }
    }
  }
  return [bx, by];
}

const inside = (f, x, y) => x >= EDGE && y >= EDGE && x < f.w - EDGE && y < f.h - EDGE;

// Track one direction through `order` (list of frame indices, starting at frame 0).
function track(frames, order, start) {
  const out = new Array(FRAME_COUNT).fill(null);
  let [x, y] = start;
  let tpl = extract(frames[order[0]], x, y);
  out[order[0]] = [x, y];
  for (let s = 1; s < order.length; s++) {
    const f = frames[order[s]];
    [x, y] = match(f, tpl, x, y);
    if (!inside(f, x, y)) break;
    out[order[s]] = [x, y];
    tpl = extract(f, x, y);
  }
  return out;
}

const frames = [];
for (let i = 0; i < FRAME_COUNT; i++) frames.push(await loadGray(i));
const { w, h } = frames[0];

const forward = [...Array(FRAME_COUNT).keys()];
const backward = [0, ...forward.slice(1).reverse()];

const points = {};
for (const [id, start] of Object.entries(LANDMARKS)) {
  const fw = track(frames, forward, start);
  const bw = track(frames, backward, start);
  // Blend: trust each direction more the closer the frame is to frame 0 along it.
  points[id] = fw.map((a, i) => {
    const b = bw[i];
    if (a && b) {
      const t = i / FRAME_COUNT; // 0 near forward start, 1 near backward start
      return [Math.round(a[0] * (1 - t) + b[0] * t), Math.round(a[1] * (1 - t) + b[1] * t)];
    }
    return a || b;
  });
  const seen = points[id].filter(Boolean).length;
  console.log(`${id}: visible on ${seen}/${FRAME_COUNT} frames`);
}

await mkdir(path.dirname(OUT), { recursive: true });
await writeFile(OUT, JSON.stringify({ frameCount: FRAME_COUNT, width: w, height: h, points }));
console.log(`wrote ${OUT}`);
