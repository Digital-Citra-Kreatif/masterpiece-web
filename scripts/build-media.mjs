// Builds web-ready media in public/media from the raw client renders in assets/.
//
//   node scripts/build-media.mjs            build everything that is missing
//   node scripts/build-media.mjs --force    rebuild everything
//
// Produces:
//   public/media/scroll/      scroll-scene frames (2 sizes) + manifest per scene (kawasan)
//   public/media/tipe-1/      facade turntable for Tipe 1 (Ruko Hook 01 renders)
//   public/media/hero/        teaser loop, full film, cover still
//   public/media/stills/      stills cropped from the renders (facilities, type cards, gallery)
//   public/media/placeholder/ drawn stand-ins for the area map, siteplan and floor plans
//
// Hotspot positions come from scripts/data/kawasan-tracks.json (see track-hotspots.mjs).
// When the client delivers WebRotate packages from SpotEditor, drop them into
// public/media/<name>/ and stop generating that folder here.

import sharp from 'sharp';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { siteplanLayout, unitDots } from './data/siteplan-layout.mjs';

const FORCE = process.argv.includes('--force');
const OUT = 'public/media';

const KAWASAN = { dir: 'assets/images_bev_kawasan', prefix: 'BEV Kawasan Ruko_', count: 600 };
// Optional AI-upscaled renders (same file names). x2: every 5th frame at 2560x1440,
// used for the scroll frames; x4: a few frames at 5120x2880, used for the zoomed
// "detail" frames at each hotspot's default angle. Missing files fall back to KAWASAN.
const KAWASAN_X2 = { ...KAWASAN, dir: 'assets/hires/x2' };
const KAWASAN_X4 = { ...KAWASAN, dir: 'assets/hires/x4' };
const RUKO = { dir: 'assets/images_ruko', prefix: 'Ruko Hook 01_', count: 600 }; // frame 600 repeats frame 0

const src = (seq, i) => path.join(seq.dir, `${seq.prefix}${String(i).padStart(5, '0')}.jpg`);
const pad3 = (n) => String(n).padStart(3, '0');
const jpeg = (img, quality = 68) => img.jpeg({ quality, mozjpeg: true });

async function fresh(dir) {
  if (existsSync(dir) && !FORCE) {
    console.log(`skip ${dir} (exists)`);
    return false;
  }
  await rm(dir, { recursive: true, force: true });
  await mkdir(dir, { recursive: true });
  console.log(`build ${dir}`);
  return true;
}

// ---------------------------------------------------------------------------
// WebRotate 360 config. Mirrors the XML that SpotEditor publishes, trimmed to
// what the site uses. Mouse-wheel rotation stays off: the site maps horizontal
// wheel/trackpad movement to rotation itself so vertical scroll keeps moving the page.
function wr360Config({ frames, width, height, firstImage = 0, hotspots = {} }) {
  const hotspotDefs = Object.keys(hotspots)
    .map(
      (id) => `    <hotspot id="${id}" renderMode="0" indicatorImage="dr-spot.svg" minIndicatorScale="100">
      <spotinfo clickAction="11" clickData="drHotspot" txt="" />
    </hotspot>`,
    )
    .join('\n');

  const images = frames
    .map((file, i) => {
      const spots = Object.entries(hotspots)
        .map(([id, pts]) => (pts[i] ? `\n      <hotspot source="${id}" offsetX="${pts[i][0]}" offsetY="${pts[i][1]}" />` : ''))
        .join('');
      return `    <image src="images/${file}">${spots}\n    </image>`;
    })
    .join('\n');

  return `<?xml version="1.0" encoding="utf-8"?>
<config>
  <settings>
    <preloader image="images/${frames[firstImage]}" />
    <userInterface showZoomButtons="false" showToolTips="false" showHotspotsButton="false" showFullScreenButton="false" showTogglePlayButton="false" showArrows="false" showProgressNumbers="false" toolbarAutohide="false" skin="empty" />
    <control dragSpeed="0.12" doubleClickZooms="false" singleClickZooms="false" disableMouseControl="false" mouseHoverDrag="false" mouseWheelDrag="false" hideHotspotsOnLoad="false" hideHotspotsOnZoom="true" dragSensitivity="10" inBrowserFullScreen="false" doubleClickFullscreen="false" pauseOnPreload="false" />
    <rotation firstImage="${firstImage}" rotate="false" rotatePeriod="24" bounce="false" rotateDirection="1" useInertia="true" inertiaRelToDragSpeed="true" inertiaTimeToStop="700" inertiaMaxInterval="120" />
  </settings>
  <hotspots>
${hotspotDefs}
  </hotspots>
  <images rows="1" highresWidth="${width}" highresHeight="${height}">
${images}
  </images>
</config>
`;
}

async function writePackage(dir, config) {
  await writeFile(path.join(dir, 'config.xml'), wr360Config(config));
}

// ---------------------------------------------------------------------------
const tracks = JSON.parse(await readFile('scripts/data/kawasan-tracks.json', 'utf8'));

// Scroll scenes (resources/views/components/scroll-scene.blade.php): an image sequence scrubbed
// by page scroll, in a desktop and a phone size, plus a manifest with per-frame
// hotspot positions keyed by landmark (from scripts/data/kawasan-tracks.json).
// Kawasan: every 5th render frame -> 120 frames (3° per frame). Its hero video is
// the same orbit, so the manifest also records how video frames map to scene frames.
// Quality 72/70: at 58 the compression showed as softness once the frames were
// scaled up to cover wide screens (lg is 16.7 MB for 120 frames, sm 6.8 MB).
const SCROLL = { step: 5, sizes: { lg: [1920, 72], sm: [800, 70] } };
const DETAIL = { width: 3840, quality: 62 };
// teaser.mp4: the orbit slowed 4x with motion-interpolated in-between frames, so
// video frame k = render frame k * 0.25; at 30 fps one orbit takes 80 s.
const HERO_VIDEO = { step: 0.25, fps: 30 };

async function buildScrollScene(name, seq, { tracks: trackPoints = {}, video, hires = {}, detailFrames = [] } = {}) {
  const dir = `${OUT}/scroll/${name}`;
  if (!(await fresh(dir))) return;
  const count = seq.count / SCROLL.step;
  // Best available source per frame: the upscaled one if present, else the render.
  const best = (i) => (hires.x2 && existsSync(src(hires.x2, i)) ? src(hires.x2, i) : src(seq, i));
  const sizes = {};
  for (const [size, [width, quality]] of Object.entries(SCROLL.sizes)) {
    // Never enlarge the plain renders: without the x2 set, lg stays at their width.
    const probe = await sharp(best(0)).metadata();
    const w = Math.min(width, probe.width);
    sizes[size] = w;
    await mkdir(`${dir}/${size}`);
    for (let k = 0; k < count; k++) {
      await jpeg(sharp(best(k * SCROLL.step)).resize(w, null, { kernel: 'lanczos3' }), quality).toFile(`${dir}/${size}/f_${pad3(k)}.jpg`);
    }
  }
  // Detail frames: sharp versions of the frames the camera rests on when zoomed in.
  const detail = [];
  if (hires.x4) {
    await mkdir(`${dir}/detail`);
    for (const f of [...new Set(detailFrames)]) {
      const file = src(hires.x4, f * SCROLL.step);
      if (!existsSync(file)) continue;
      // The upscaler shifts tone a little (darker, more contrast), which would show as
      // a jump when the view switches to the detail frame. Match each channel's mean
      // and spread to the original frame, measured on blurred copies so the new fine
      // detail doesn't count as contrast.
      const tone = async (img) => (await sharp(img).resize(640).blur(3).stats()).channels.slice(0, 3);
      const [want, have] = await Promise.all([tone(best(f * SCROLL.step)), tone(file)]);
      const a = have.map((c, i) => want[i].stdev / c.stdev);
      const b = have.map((c, i) => want[i].mean - c.mean * a[i]);
      await jpeg(sharp(file).resize(DETAIL.width, null, { kernel: 'lanczos3' }).linear(a, b), DETAIL.quality).toFile(
        `${dir}/detail/f_${pad3(f)}.jpg`,
      );
      detail.push(f);
    }
  }
  const manifest = {
    count,
    width: 1280, // hotspot coordinates are in this space
    height: 720,
    sizes,
    pattern: '{size}/f_{n}.jpg', // n = zero-padded to 3
    ...(detail.length && { detail: { pattern: 'detail/f_{n}.jpg', frames: detail } }),
    step: SCROLL.step, // scene frame f = render frame f * step
    ...(video && { video }), // video frame k = render frame k * video.step
    hotspots: Object.fromEntries(
      Object.entries(trackPoints).map(([track, pts]) => [track, Array.from({ length: count }, (_, k) => pts[k * SCROLL.step])]),
    ),
  };
  await writeFile(`${dir}/manifest.json`, JSON.stringify(manifest));
  console.log(`  ${name}: lg ${sizes.lg}px, detail frames ${detail.join(', ') || 'none'}`);
}

// Facade turntable for Tipe 1: every 5th frame -> 120 frames, cropped to the building.
async function buildFacade() {
  const dir = `${OUT}/tipe-1/facade`;
  if (!(await fresh(dir))) return;
  await mkdir(`${dir}/images`);
  const crop = { left: 260, top: 93, width: 760, height: 570 };
  const frames = [];
  for (let i = 0, k = 0; i < RUKO.count; i += 5, k++) {
    const file = `facade_${pad3(k)}.jpg`;
    await jpeg(sharp(src(RUKO, i)).extract(crop), 72).toFile(`${dir}/images/${file}`);
    frames.push(file);
  }
  await writePackage(dir, { frames, width: crop.width, height: crop.height });
}

// Hero: the aerial orbit loops seamlessly (frame 599 -> 0), so it doubles as the
// teaser loop (every 2nd frame, 10 s) and a stand-in for the full film (20 s).
async function buildHero() {
  const dir = `${OUT}/hero`;
  if (!(await fresh(dir))) return;
  const pattern = path.join(KAWASAN.dir, `${KAWASAN.prefix}%05d.jpg`);
  const h264 = ['-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-an'];
  const ff = (args) => execFileSync('ffmpeg', ['-loglevel', 'error', '-y', ...args], { stdio: 'inherit' });
  // Teaser loop: interpolate to 1/step frames per render frame. The input loops
  // once so the frames between the last render and the first are interpolated
  // too, then the output is cut to exactly one orbit: a seamless loop. Slow
  // (several minutes).
  const factor = 1 / HERO_VIDEO.step;
  const frames = KAWASAN.count * factor;
  ff([
    '-stream_loop', '1', '-framerate', String(HERO_VIDEO.fps), '-i', pattern,
    '-vf', `minterpolate=fps=${HERO_VIDEO.fps * factor}:mi_mode=mci:mc_mode=aobmc:me_mode=bidir,setpts=${factor}*PTS`,
    '-r', String(HERO_VIDEO.fps), '-frames:v', String(frames),
    ...h264, '-crf', '29', '-preset', 'slow', `${dir}/teaser.mp4`,
  ]);
  // Full film placeholder: the render orbit at 30 fps (20 s).
  ff(['-framerate', '30', '-i', pattern, ...h264, '-crf', '25', '-preset', 'slow', `${dir}/full.mp4`]);
  await jpeg(sharp(src(KAWASAN, 0)), 80).toFile(`${dir}/cover.jpg`);
}

// Stills cropped from the renders, standing in for photography the client will supply.
const STILLS = {
  // facilities (area renders), 16:9
  'fac-lake': { seq: KAWASAN, frame: 0, crop: [100, 180, 960, 540] },
  'fac-sport': { seq: KAWASAN, frame: 150, crop: [320, 140, 960, 540] },
  'fac-commercial': { seq: KAWASAN, frame: 375, crop: [0, 200, 800, 450] },
  'fac-boulevard': { seq: KAWASAN, frame: 300, crop: [160, 120, 960, 540] },
  'fac-plaza': { seq: KAWASAN, frame: 525, crop: [300, 160, 960, 540] },
  'fac-green': { seq: KAWASAN, frame: 450, crop: [480, 100, 800, 450] },
  // type cards and gallery (Ruko Hook 01 renders)
  'ruko-front': { seq: RUKO, frame: 0, crop: [340, 120, 600, 520] },
  'ruko-34': { seq: RUKO, frame: 60, crop: [340, 120, 600, 520] },
  'ruko-side': { seq: RUKO, frame: 150, crop: [340, 120, 600, 520] },
  'ruko-back': { seq: RUKO, frame: 300, crop: [340, 120, 600, 520] },
  'ruko-back-34': { seq: RUKO, frame: 380, crop: [340, 120, 600, 520] },
  'ruko-side-2': { seq: RUKO, frame: 450, crop: [340, 120, 600, 520] },
  'ruko-front-34': { seq: RUKO, frame: 540, crop: [340, 120, 600, 520] },
};

async function buildStills() {
  const dir = `${OUT}/stills`;
  if (!(await fresh(dir))) return;
  for (const [name, { seq, frame, crop }] of Object.entries(STILLS)) {
    const [left, top, width, height] = crop;
    await jpeg(sharp(src(seq, frame)).extract({ left, top, width, height }), 78).toFile(`${dir}/${name}.jpg`);
  }
}

// ---------------------------------------------------------------------------
// Drawn placeholders (SVG). Replace with the client's map, siteplan and denah.
const PAPER = '#f3f4f2', LINE = '#1b1f23', MUTED = '#c3c7c9', WATER = '#b3c6d3', GREEN = '#cdd6c6', ACCENT = '#34425e';

function areaMapSvg() {
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 900" width="1600" height="900" font-family="Helvetica, Arial, sans-serif">
  <rect width="1600" height="900" fill="${PAPER}"/>
  <g fill="${GREEN}" opacity=".8">
    <path d="M0 620 C200 560 380 700 560 660 S900 560 1080 640 1400 760 1600 700 V900 H0Z"/>
    <path d="M1180 0 C1250 120 1420 150 1600 110 V0Z"/>
    <path d="M0 0 H320 C280 90 140 140 0 150Z"/>
  </g>
  <path d="M470 560 C560 520 700 610 840 575 S1010 540 1060 600 C1020 700 900 690 800 660 S560 700 470 560Z" fill="${WATER}"/>
  <path d="M1120 560 C1170 600 1180 700 1260 760 L1240 780 C1160 720 1140 620 1100 580Z" fill="${WATER}"/>
  <g fill="none" stroke="${MUTED}" stroke-linecap="round">
    <path d="M-20 250 C300 220 600 170 900 150 S1400 120 1620 90" stroke-width="34"/>
    <path d="M-20 250 C300 220 600 170 900 150 S1400 120 1620 90" stroke="${PAPER}" stroke-width="4" stroke-dasharray="26 22"/>
    <path d="M760 -20 C770 200 760 330 740 520" stroke-width="16"/>
    <path d="M200 900 C260 700 380 520 520 420 S760 330 900 330 1300 360 1620 420" stroke-width="12"/>
    <path d="M1350 -20 C1320 200 1300 300 1320 440" stroke-width="10"/>
  </g>
  <g fill="#dfe2e0">
    ${Array.from({ length: 9 }, (_, i) => `<rect x="${1060 + (i % 3) * 110}" y="${190 + Math.floor(i / 3) * 70}" width="90" height="48"/>`).join('')}
    ${Array.from({ length: 6 }, (_, i) => `<rect x="${130 + (i % 3) * 110}" y="${320 + Math.floor(i / 3) * 70}" width="90" height="48"/>`).join('')}
  </g>
  <path d="M600 330 L940 330 L960 520 L590 540Z" fill="${ACCENT}" fill-opacity=".08" stroke="${ACCENT}" stroke-width="4" stroke-dasharray="14 10"/>
  <g font-size="22" fill="${LINE}" letter-spacing="3">
    <text x="775" y="440" text-anchor="middle" font-size="26" font-weight="700" fill="${ACCENT}">LOKASI PROYEK</text>
    <text x="300" y="205" transform="rotate(-6 300 205)">JALAN TOL</text>
    <text x="1180" y="455" text-anchor="middle" fill="#646b72">JALAN UTAMA</text>
    <text x="760" y="620" text-anchor="middle" fill="#4f6878">DANAU</text>
  </g>
  <g transform="translate(1500 800)" fill="${LINE}">
    <path d="M0 -44 L14 0 L0 -10 L-14 0Z"/><text y="28" text-anchor="middle" font-size="20" font-weight="700">U</text>
  </g>
</svg>`;
}

function siteplanSvg() {
  const { width, height, blocks, road } = siteplanLayout;
  const blockRects = blocks
    .map((b) => {
      const units = Array.from({ length: b.units }, (_, i) => {
        const w = b.w / b.units;
        return `<rect x="${(b.x + i * w).toFixed(1)}" y="${b.y}" width="${w.toFixed(1)}" height="${b.h}" fill="#fbfbfa" stroke="${LINE}" stroke-width="1.5"/>`;
      }).join('');
      return `<g>${units}<text x="${b.x + b.w / 2}" y="${b.labelY}" text-anchor="middle" font-size="18" font-weight="700" letter-spacing="3" fill="${LINE}">BLOK ${b.id}</text></g>`;
    })
    .join('');
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" width="${width}" height="${height}" font-family="Helvetica, Arial, sans-serif">
  <rect width="${width}" height="${height}" fill="${PAPER}"/>
  <path d="M0 ${height - 150} C300 ${height - 200} 600 ${height - 110} 900 ${height - 140} S1400 ${height - 190} ${width} ${height - 130} V${height} H0Z" fill="${WATER}"/>
  <rect x="0" y="${road.y1}" width="${width}" height="${road.h}" fill="#e1e4e2"/>
  <rect x="0" y="${road.y2}" width="${width}" height="${road.h}" fill="#e1e4e2"/>
  <rect x="${road.x}" y="${road.y1}" width="${road.w}" height="${road.y2 - road.y1 + road.h}" fill="#e1e4e2"/>
  <circle cx="${road.x + road.w / 2}" cy="${(road.y1 + road.y2 + road.h) / 2}" r="34" fill="${GREEN}" stroke="${LINE}" stroke-width="1.5"/>
  ${blockRects}
  <text x="${width / 2}" y="${height - 60}" text-anchor="middle" font-size="18" letter-spacing="3" fill="#4f6878">DANAU</text>
</svg>`;
}

// Simple denah drawing: outer wall, a few rooms, labels.
function denahSvg(title, rooms) {
  const cells = rooms
    .map(([x, y, w, h, label]) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="none" stroke="${LINE}" stroke-width="4"/>
    <text x="${x + w / 2}" y="${y + h / 2 + 7}" text-anchor="middle" font-size="20" fill="${LINE}">${label}</text>`)
    .join('');
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 800" width="600" height="800" font-family="Helvetica, Arial, sans-serif">
  <rect width="600" height="800" fill="#fbfbfa"/>
  <rect x="60" y="80" width="480" height="620" fill="none" stroke="${LINE}" stroke-width="10"/>
  ${cells}
  <text x="300" y="50" text-anchor="middle" font-size="24" font-weight="700" letter-spacing="4" fill="${LINE}">${title}</text>
</svg>`;
}

async function buildPlaceholders() {
  const dir = `${OUT}/placeholder`;
  if (!(await fresh(dir))) return;
  await writeFile(`${dir}/area-map.svg`, areaMapSvg());
  await writeFile(`${dir}/siteplan.svg`, siteplanSvg());
  await writeFile(`${dir}/denah-lt1.svg`, denahSvg('LANTAI 1', [[60, 80, 480, 360, 'AREA USAHA'], [60, 440, 240, 260, 'DAPUR'], [300, 440, 120, 130, 'KM'], [300, 570, 240, 130, 'TANGGA'], [420, 440, 120, 130, 'GUDANG']]));
  await writeFile(`${dir}/denah-lt2.svg`, denahSvg('LANTAI 2', [[60, 80, 280, 300, 'K. TIDUR 1'], [340, 80, 200, 300, 'K. TIDUR 2'], [60, 380, 280, 320, 'RUANG KELUARGA'], [340, 380, 200, 150, 'KM'], [340, 530, 200, 170, 'TANGGA']]));
  await writeFile(`${dir}/denah-atap.svg`, denahSvg('ATAP', [[60, 80, 480, 440, 'ROOF DECK'], [60, 520, 240, 180, 'TANGGA'], [300, 520, 240, 180, 'TOREN']]));
}

// WebRotate hotspot indicator in the site's style.
async function buildGraphics() {
  const dir = 'public/wr360/graphics';
  await mkdir(dir, { recursive: true });
  await writeFile(`${dir}/dr-spot.svg`, `<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36">
  <circle cx="18" cy="18" r="16" fill="#0b0d0f" fill-opacity=".5" stroke="#fff" stroke-width="2"/>
  <path d="M18 11v14M11 18h14" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
</svg>`);
}

// Seed the unit status list once. After that it is edited by hand (or by whoever
// the client names to keep sold / reserved / available current).
async function seedUnits() {
  const file = 'resources/data/units.json';
  if (existsSync(file)) return;
  let seed = 7;
  const rand = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
  const units = unitDots().map((u) => {
    const r = rand();
    return { ...u, status: r < 0.34 ? 'sold' : r < 0.5 ? 'reserved' : 'available' };
  });
  await writeFile(file, JSON.stringify(units, null, 2) + '\n');
  console.log(`seeded ${file}`);
}

await seedUnits();
await buildGraphics();
{
  // Detail frames = every hotspot's default angle (Kawasan and Cluster share these frames).
  const { area, cluster } = JSON.parse(await readFile('resources/data/area.json', 'utf8'));
  const detailFrames = [area, cluster].flatMap((a) => Object.values(a.hotspots).map((h) => h.frame)).filter((f) => f != null);
  await buildScrollScene('kawasan', KAWASAN, {
    tracks: tracks.points,
    video: HERO_VIDEO,
    hires: { x2: KAWASAN_X2, x4: KAWASAN_X4 },
    detailFrames,
  });
}
// Cluster: no sequence yet; the cluster scene reuses the kawasan frames
// (resources/data/area.json). When the cluster renders arrive:
//   await buildScrollScene('cluster', CLUSTER, { tracks: clusterTracks });
await buildFacade();
await buildHero();
await buildStills();
await buildPlaceholders();
console.log('media done');
