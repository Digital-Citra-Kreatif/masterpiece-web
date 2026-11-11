// Tombol ke atas
const button = document.querySelector('[data-to-top]');
if (button) {
  // Muncul setelah selayar
  const update = () => button.classList.toggle('is-visible', scrollY > innerHeight);
  window.addEventListener('scroll', update, { passive: true });
  update();

  button.addEventListener('click', () => {
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
    // Fokus ke awal
    document.querySelector('header a, main')?.focus({ preventScroll: true });
  });
}
