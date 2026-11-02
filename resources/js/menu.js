// Menu hamburger
import { lockScroll } from './scroll-lock.js';

const menu = document.getElementById('hd-menu');
const toggle = document.querySelector('[data-menu-toggle]');

if (menu && toggle) {
  const header = toggle.closest('.hd');
  const setOpen = (open) => {
    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    // Header putih
    header.classList.toggle('is-menu-open', open);
    lockScroll(menu, open);
  };
  const nav = menu.querySelector('nav');

  toggle.addEventListener('click', () => setOpen(menu.hidden));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !menu.hidden) {
      setOpen(false);
      toggle.focus();
    }
  });

  document.addEventListener('click', (event) => {
    // Klik di overlay
    if (!menu.hidden && !nav.contains(event.target) && !toggle.contains(event.target)) setOpen(false);
  });

  for (const link of menu.querySelectorAll('a')) {
    link.addEventListener('click', () => setOpen(false));
  }
}
