// Pembuka Masterplan
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

  // Kotak dan skala
  const start = () => {
    const W = stage.clientWidth;
    const H = stage.clientHeight;
    const w = box.offsetWidth;
    const h = box.offsetHeight;
    const cover = (bw, bh) => Math.max(bw / img.dataset.w, bh / img.dataset.h);
    // Batas minimal kotak
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
