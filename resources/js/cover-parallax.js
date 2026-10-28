// Parallax tertutup
export function coverParallax(box, target) {
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
