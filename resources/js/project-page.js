// Project page (resources/views/pages/project.blade.php).

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
