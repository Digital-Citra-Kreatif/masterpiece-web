// Tombol ke atas
const button = document.querySelector('[data-to-top]');
if (button) {
  // Muncul setelah selayar
  const update = () => button.classList.toggle('is-visible', scrollY > innerHeight);
  window.addEventListener('scroll', update, { passive: true });
  update();

  button.addEventListener('click', () => {
    window.scrollTo(0, 0);
    // Fokus ke awal
    document.querySelector('header a, main')?.focus({ preventScroll: true });
  });
}
