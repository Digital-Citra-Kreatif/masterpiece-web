// Halaman proyek
import { coverParallax } from './cover-parallax.js';
import './hero-parallax.js';

if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
  // Masterplan → Lokasi
  const masterplan = document.querySelector('.is-project .scene--masterplan');
  if (masterplan) coverParallax(masterplan, masterplan.querySelector('.scene__stage'));
  // Tipe → Ketersediaan
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
