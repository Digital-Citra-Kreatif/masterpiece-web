// WhatsApp mengambang
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
