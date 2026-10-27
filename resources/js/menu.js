// Hamburger menu (tablet and mobile): the header links in a full-screen dialog.
// showModal() keeps focus inside and Esc closes it; smooth-scroll.js pauses
// Lenis while it is open. A link to a section on this page closes the menu
// first, then glides there.
import { lenis } from './smooth-scroll.js';

const menu = document.getElementById('hd-menu');
const opener = document.querySelector('[data-menu-open]');

if (menu && opener) {
  const close = () => menu.open && menu.close();

  opener.addEventListener('click', () => {
    menu.showModal();
    opener.setAttribute('aria-expanded', 'true');
  });
  menu.addEventListener('close', () => opener.setAttribute('aria-expanded', 'false'));
  menu.querySelector('[data-menu-close]').addEventListener('click', close);

  for (const link of menu.querySelectorAll('.hd__menu-nav a')) {
    link.addEventListener('click', (event) => {
      const url = new URL(link.href);
      const target = url.hash && url.pathname === location.pathname ? document.querySelector(url.hash) : null;
      close();
      if (target) {
        event.preventDefault();
        // The observer in smooth-scroll.js restarts Lenis only after this handler.
        lenis.start();
        lenis.scrollTo(target);
        history.pushState(null, '', url.hash);
      }
    });
  }
}
