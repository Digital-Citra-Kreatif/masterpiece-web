// Gulir halus ke jangkar
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

document.addEventListener('click', (event) => {
  if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  const link = event.target.closest?.('a[href*="#"]');
  if (!link || link.target === '_blank') return;

  const url = new URL(link.href, location.href);
  // Hanya halaman ini
  if (url.origin !== location.origin || url.pathname !== location.pathname || url.hash.length < 2) return;
  const target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
  if (!target) return;

  event.preventDefault();
  history.pushState(null, '', url.hash);
  window.scrollTo({ top: target.getBoundingClientRect().top + scrollY, behavior: reduced ? 'auto' : 'smooth' });
  // Fokus ikut pindah
  if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
  target.focus({ preventScroll: true });
});
