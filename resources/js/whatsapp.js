// WhatsApp mengambang
const wa = document.querySelector('[data-wa]');
if (wa) {
  const toggle = wa.querySelector('[data-wa-toggle]');
  const panel = wa.querySelector('.wa__panel');
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const isOpen = () => !panel.hidden && !panel.classList.contains('is-closing');

  const finishClose = () => {
    panel.classList.remove('is-closing');
    panel.hidden = true;
  };

  const setOpen = (open) => {
    toggle.setAttribute('aria-expanded', String(open));
    if (open) {
      panel.classList.remove('is-closing');
      panel.hidden = false;
      return;
    }
    if (panel.hidden) return;
    // Reduced motion: tutup
    if (reduced.matches) return finishClose();
    panel.classList.add('is-closing');
  };

  // Hanya animasi tutup
  panel.addEventListener('animationend', (e) => {
    if (e.target === panel && e.animationName === 'wa-out' && panel.classList.contains('is-closing')) finishClose();
  });

  toggle.addEventListener('click', () => setOpen(!isOpen()));
  wa.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen()) {
      setOpen(false);
      toggle.focus();
    }
  });
  document.addEventListener('click', (e) => {
    if (!wa.contains(e.target) && isOpen()) setOpen(false);
  });
}
