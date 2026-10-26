// Project page (resources/views/pages/project.blade.php).

// Parallax: the hero, and the Masterplan scene's end, sink behind the section
// after them as it scrolls up over them.
//
// Hero: while the page scrolls past the hero, it moves down at half
// the scroll and darkens, so the section after it slides up over it and closes
// it. Still for visitors who prefer reduced motion.
const parallax = document.querySelector('[data-hero-parallax]');
if (parallax && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const hero = parallax.querySelector('.hero');
  let queued = false;
  const draw = () => {
    queued = false;
    const h = hero.offsetHeight;
    const y = Math.min(Math.max(window.scrollY, 0), h);
    hero.style.transform = y > 0 ? `translate3d(0, ${(y * 0.5).toFixed(1)}px, 0)` : '';
    hero.style.setProperty('--cover', (y / h).toFixed(3));
  };
  window.addEventListener('scroll', () => {
    if (!queued) {
      queued = true;
      requestAnimationFrame(draw);
    }
  }, { passive: true });
  draw();
}

// The same as the section after the Masterplan scene comes up: once the
// scene's end reaches the bottom of the screen, its pinned stage sinks at half
// the scroll and darkens while Location slides up over it.
const covered = document.querySelector('.is-project .scene--masterplan');
if (covered && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const stage = covered.querySelector('.scene__stage');
  const veil = document.createElement('span');
  veil.className = 'cover-veil';
  veil.setAttribute('aria-hidden', 'true');
  stage.append(veil);
  let queued = false;
  const draw = () => {
    queued = false;
    const h = stage.offsetHeight;
    const d = Math.min(Math.max(window.innerHeight - covered.getBoundingClientRect().bottom, 0), h);
    stage.style.transform = d > 0 ? `translate3d(0, ${(d * 0.5).toFixed(1)}px, 0)` : '';
    veil.style.opacity = ((d / h) * 0.6).toFixed(3);
  };
  window.addEventListener('scroll', () => {
    if (!queued) {
      queued = true;
      requestAnimationFrame(draw);
    }
  }, { passive: true });
  draw();
}

// Unit type sheets: <dialog>s opened by [data-sheet="{id}"]; Escape, the close button or the backdrop closes them.
let opener = null;
for (const trigger of document.querySelectorAll('[data-sheet]')) {
  const sheet = document.getElementById(trigger.dataset.sheet);
  if (!sheet) continue;
  trigger.addEventListener('click', () => {
    opener = trigger;
    sheet.showModal();
    sheet.querySelector('[data-sheet-close]').focus();
  });
}

for (const sheet of document.querySelectorAll('.pj-sheet')) {
  sheet.querySelector('[data-sheet-close]').addEventListener('click', () => sheet.close());
  sheet.addEventListener('click', (e) => {
    if (e.target === sheet) sheet.close();
  });
  sheet.addEventListener('close', () => opener?.focus());
}

// WhatsApp floating panel.
const wa = document.querySelector('[data-wa]');
if (wa) {
  const toggle = wa.querySelector('[data-wa-toggle]');
  const panel = wa.querySelector('.wa__panel');
  const setOpen = (open) => {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
  };
  toggle.addEventListener('click', () => setOpen(panel.hidden));
  wa.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !panel.hidden) {
      setOpen(false);
      toggle.focus();
    }
  });
  document.addEventListener('click', (e) => {
    if (!wa.contains(e.target)) setOpen(false);
  });
}
