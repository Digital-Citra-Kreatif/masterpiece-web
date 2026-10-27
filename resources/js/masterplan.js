// Masterplan opening of the Kawasan scene (GSAP ScrollTrigger, scrubbed by scroll).
//
// Over the scene's first stretch of scroll (up to [data-zoom-end]) the title
// moves out to the left and the text to the right while the portrait frame grows
// to fill the stage. The frame holds the looping orbit video (scroll-scene.js plays
// it and hands over from it to the canvas), cover-fit as the canvas draws the
// frames, so at full size the video is exactly the view the canvas continues.
//
// The zoom only animates clip-path and transform: the picture always covers the
// stage, cut down to the box and scaled so it covers the box the way a
// cover-fit image the box's size would, opening to the full stage at scale 1.
// The scale stays a 2D transform (force3D: false, no will-change): as a GPU layer
// the browser would keep the picture rasterised at its small starting size and
// stretch that, so it looked blurred while growing. `scrub` eases the timeline
// after the scroll a little instead of locking it to every pixel.
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { lenis } from './smooth-scroll.js';

gsap.registerPlugin(ScrollTrigger);
lenis.on('scroll', ScrollTrigger.update);

for (const root of document.querySelectorAll('.scene--masterplan')) {
  const stage = root.querySelector('.scene__stage');
  const frame = root.querySelector('[data-mp-frame]');
  const img = root.querySelector('[data-mp-img]');
  const box = root.querySelector('[data-mp-box]');
  const title = root.querySelector('[data-mp-title]');
  const text = root.querySelector('[data-mp-text]');
  const end = root.querySelector('[data-zoom-end]');

  // The box on the stage, and the picture's scale that cover-fits it.
  const start = () => {
    const W = stage.clientWidth;
    const H = stage.clientHeight;
    const w = box.offsetWidth;
    const h = box.offsetHeight;
    const cover = (bw, bh) => Math.max(bw / img.dataset.w, bh / img.dataset.h);
    // The picture is cut to the stage, so it never shrinks below the box: a box
    // wider than the portrait stage (phones) would otherwise show a narrow strip.
    return { x: (W - w) / 2, y: (H - h) / 2, scale: Math.max(cover(w, h) / cover(W, H), w / W, h / H) };
  };

  const timeline = gsap.timeline({
    defaults: { ease: 'none' },
    scrollTrigger: {
      trigger: root,
      start: 'top top',
      end: () => `+=${end.offsetTop}`,
      scrub: 0.6,
      invalidateOnRefresh: true,
    },
  });

  timeline
    .fromTo(
      frame,
      { clipPath: () => `inset(${start().y}px ${start().x}px ${start().y}px ${start().x}px)` },
      { clipPath: 'inset(0px 0px 0px 0px)', ease: 'power2.inOut', duration: 1 },
      0,
    )
    .fromTo(img, { scale: () => start().scale }, { scale: 1, force3D: false, ease: 'power2.inOut', duration: 1 }, 0)
    .to(title, { x: () => -window.innerWidth * 0.6, autoAlpha: 0, ease: 'power2.in', duration: 0.8 }, 0);
  if (text) timeline.to(text, { x: () => window.innerWidth * 0.6, autoAlpha: 0, ease: 'power2.in', duration: 0.8 }, 0);
}
