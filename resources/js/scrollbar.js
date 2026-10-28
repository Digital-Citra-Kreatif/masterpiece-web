// Scrollbar kustom
import { lenis } from './smooth-scroll.js';

const MIN_THUMB = 24;

const bar = document.createElement('div');
bar.className = 'scrollbar';
bar.setAttribute('aria-hidden', 'true');
const thumb = document.createElement('div');
thumb.className = 'scrollbar__thumb';
bar.append(thumb);
document.body.append(bar);
document.documentElement.classList.add('has-scrollbar');

let thumbHeight = 0;
const scrollable = () => document.documentElement.scrollHeight - window.innerHeight;

const update = () => {
  const max = scrollable();
  bar.hidden = max <= 0;
  if (max <= 0) return;
  thumbHeight = Math.max(MIN_THUMB, (window.innerHeight * window.innerHeight) / document.documentElement.scrollHeight);
  thumb.style.height = `${thumbHeight}px`;
  thumb.style.transform = `translateY(${(window.scrollY / max) * (window.innerHeight - thumbHeight)}px)`;
};

window.addEventListener('scroll', update, { passive: true });
window.addEventListener('resize', update);
new ResizeObserver(update).observe(document.body);
update();

// Drag thumb
thumb.addEventListener('pointerdown', (down) => {
  down.preventDefault();
  thumb.setPointerCapture(down.pointerId);
  thumb.classList.add('is-dragging');
  const startY = down.clientY;
  const startScroll = window.scrollY;
  const perPixel = scrollable() / (window.innerHeight - thumbHeight);

  const move = (event) => lenis.scrollTo(startScroll + (event.clientY - startY) * perPixel, { immediate: true });
  const end = () => {
    thumb.classList.remove('is-dragging');
    thumb.removeEventListener('pointermove', move);
    thumb.removeEventListener('pointerup', end);
    thumb.removeEventListener('pointercancel', end);
  };
  thumb.addEventListener('pointermove', move);
  thumb.addEventListener('pointerup', end);
  thumb.addEventListener('pointercancel', end);
});
