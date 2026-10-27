// Halaman proyek

// Parallax hero
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

// Parallax tertutup section berikut
// target: yang turun; box: yang diukur (bawahnya)
function coverParallax(box, target) {
  const veil = document.createElement('span');
  veil.className = 'cover-veil';
  veil.setAttribute('aria-hidden', 'true');
  target.append(veil);
  let queued = false;
  const draw = () => {
    queued = false;
    const h = Math.min(target.offsetHeight, window.innerHeight);
    const d = Math.min(Math.max(window.innerHeight - box.getBoundingClientRect().bottom, 0), h);
    target.style.transform = d > 0 ? `translate3d(0, ${(d * 0.5).toFixed(1)}px, 0)` : '';
    veil.style.opacity = ((d / h) * 0.6).toFixed(3);
  };
  window.addEventListener('scroll', () => {
    if (!queued) {
      queued = true;
      requestAnimationFrame(draw);
    }
  }, { passive: true });
  window.addEventListener('resize', draw);
  draw();
}

if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
  // Masterplan → Location
  const masterplan = document.querySelector('.is-project .scene--masterplan');
  if (masterplan) coverParallax(masterplan, masterplan.querySelector('.scene__stage'));
  // Type → Unit Availability
  const types = document.querySelector('.is-project .types--project');
  if (types && types.nextElementSibling) coverParallax(types, types);
}

// Dialog tipe unit
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
