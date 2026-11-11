// Parallax gambar
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

/**
 * Gambar, yPercent awal dan akhir, skala
 *
 * @type {Array<[string, number, number, number]>}
 */
const TARGETS = [
  ['.prj__frame img', -7, 7, 1.16],
];

/**
 * Pasang parallax
 *
 * @returns {gsap.Context|null}
 */
export function initParallax(scope = document) {
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return null;

  return gsap.context(() => {
    for (const [selector, from, to, scale] of TARGETS) {
      for (const img of scope.querySelectorAll(selector)) {
        gsap.fromTo(
          img,
          { yPercent: from, scale, force3D: true },
          {
            yPercent: to,
            ease: 'none',
            scrollTrigger: { trigger: img.parentElement, start: 'top bottom', end: 'bottom top', scrub: 0.6 },
          },
        );
      }
    }
  }, scope);
}
