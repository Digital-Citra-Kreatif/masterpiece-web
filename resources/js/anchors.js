// Gulir halus ke jangkar
import { ScrollTrigger } from 'gsap/ScrollTrigger';

const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

document.addEventListener('click', (event) => {
  if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  const link = event.target.closest?.('a[href*="#"]');
  if (!link || link.target === '_blank') return;

  const url = new URL(link.href, location.href);
  // Hanya halaman ini
  if (url.origin !== location.origin || url.pathname !== location.pathname || url.hash.length < 2) return;
  const target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
  if (!target) return;

  event.preventDefault();
  history.pushState(null, '', url.hash);
  window.scrollTo({ top: target.getBoundingClientRect().top + scrollY, behavior: reduced ? 'auto' : 'smooth' });
  // Fokus ikut pindah
  if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
  target.focus({ preventScroll: true });
});

// Jangkar dari halaman lain
const landing = location.hash.length > 1 ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
if (landing) {
  let held = true;
  // Posisi tanpa transform
  const top = () => {
    let y = 0;
    for (let el = landing; el; el = el.offsetParent) y += el.offsetTop;
    return y - (parseFloat(getComputedStyle(landing).scrollMarginTop) || 0);
  };
  const jump = () => {
    if (held) window.scrollTo({ top: top(), behavior: 'instant' });
  };
  // Lepas saat pengunjung menggulir
  const release = () => {
    held = false;
    ScrollTrigger.removeEventListener('refresh', jump);
  };
  for (const type of ['wheel', 'touchstart', 'keydown', 'pointerdown']) {
    addEventListener(type, release, { once: true, passive: true });
  }
  // Pin menambah tinggi halaman
  ScrollTrigger.addEventListener('refresh', jump);
  addEventListener('load', () => requestAnimationFrame(() => requestAnimationFrame(jump)), { once: true });
}
