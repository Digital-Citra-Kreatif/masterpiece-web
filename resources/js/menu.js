// Hamburger menu (tablet and mobile): the header links in a dropdown under the
// header, as tall as its links. The button toggles it; Esc, a click outside or
// following a link closes it. The page under the overlay stays put while it is
// open; Lenis restarts before its own click handler (on window) reads a link, so
// section links still glide there.
import { lenis } from './smooth-scroll.js';

const menu = document.getElementById('hd-menu');
const toggle = document.querySelector('[data-menu-toggle]');

if (menu && toggle) {
  const header = toggle.closest('.hd');
  const setOpen = (open) => {
    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    // White header while open
    header.classList.toggle('is-menu-open', open);
    open ? lenis.stop() : lenis.start();
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
    // The overlay is the menu's ::after, so a click on it lands outside the nav.
    if (!menu.hidden && !nav.contains(event.target) && !toggle.contains(event.target)) setOpen(false);
  });

  for (const link of menu.querySelectorAll('a')) {
    link.addEventListener('click', () => setOpen(false));
  }
}
