// Parallax hero, ditutup section berikutnya
const parallax = document.querySelector('[data-hero-parallax]');
if (parallax && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const hero = parallax.querySelector('.hero, [data-hero]');
  let h = hero.offsetHeight;
  let last = NaN;
  let queued = false;
  const draw = () => {
    queued = false;
    const y = Math.min(Math.max(window.scrollY, 0), h);
    // Lewat hero: tidak ada yang berubah
    if (y === last) return;
    last = y;
    hero.style.transform = y > 0 ? `translate3d(0, ${(y * 0.5).toFixed(1)}px, 0)` : '';
    hero.style.setProperty('--cover', (y / h).toFixed(3));
  };
  window.addEventListener('scroll', () => {
    if (!queued) {
      queued = true;
      requestAnimationFrame(draw);
    }
  }, { passive: true });
  new ResizeObserver(() => {
    h = hero.offsetHeight;
    last = NaN;
    draw();
  }).observe(hero);
  draw();
}
