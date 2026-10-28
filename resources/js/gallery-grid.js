// Popup grid galeri
const dlg = document.querySelector('[data-gallery-grid]');

if (dlg) {
  document.querySelector('[data-grid-open]')?.addEventListener('click', () => dlg.showModal());
  dlg.querySelector('[data-grid-close]').addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', (e) => e.target === dlg && dlg.close()); // Klik latar

  // PhotoSwipe di bawah dialog modal
  let pswp = 'idle';
  window.addEventListener(
    'click',
    (e) => {
      if (!dlg.open || !dlg.contains(e.target) || !e.target.closest('[data-open="gallery"]')) return;
      if (!document.body.classList.contains('is-project')) return;
      pswp = 'waiting';
      dlg.close();
    },
    true,
  );
  new MutationObserver(() => {
    const shown = document.querySelector('.pswp') !== null;
    if (pswp === 'waiting' && shown) pswp = 'open';
    else if (pswp === 'open' && !shown) {
      pswp = 'idle';
      dlg.showModal();
    }
  }).observe(document.body, { childList: true });
}
