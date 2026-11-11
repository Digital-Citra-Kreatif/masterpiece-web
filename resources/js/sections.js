// Transisi section
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

// Section berpin
const PINNED = '[data-scene], [data-spin], .hero';

/**
 * Pasang transisi section
 *
 * @returns {gsap.Context|null}
 */
export function initSections(scope = document) {
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return null;

  return gsap.context(() => {
    for (const section of scope.querySelectorAll('main > section')) {
      const next = section.nextElementSibling;
      if (section.matches(PINNED) || !next?.matches('section')) continue;
      // Isi saja, tanpa kursor
      gsap.to(section.querySelectorAll(':scope > :not([data-view-cursor-el])'), {
        opacity: 0.2,
        y: -48,
        ease: 'none',
        scrollTrigger: { trigger: next, start: 'top bottom', end: 'top top', scrub: 0.6 },
      });
    }
  }, scope);
}
