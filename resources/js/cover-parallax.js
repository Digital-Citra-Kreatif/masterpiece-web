// Parallax tertutup
export function coverParallax(box, target) {
  const veil = document.createElement('span');
  veil.className = 'cover-veil';
  veil.setAttribute('aria-hidden', 'true');
  target.append(veil);
  let bottom = 0; // Bawah box di dokumen
  let height = 0;
  let shift = 0;
  let last = NaN;
  let queued = false;
  // Ukur sekali, bukan tiap frame
  const measure = () => {
    bottom = box.getBoundingClientRect().bottom + window.scrollY - (box === target ? shift : 0);
    height = target.offsetHeight;
    last = NaN;
  };
  const draw = () => {
    queued = false;
    const vh = window.innerHeight;
    const h = Math.min(height, vh);
    const d = Math.min(Math.max(vh - (bottom - window.scrollY), 0), h);
    if (d === last) return;
    last = d;
    shift = d * 0.5;
    target.style.transform = d > 0 ? `translate3d(0, ${shift.toFixed(1)}px, 0)` : '';
    veil.style.opacity = h > 0 ? ((d / h) * 0.6).toFixed(3) : '0';
  };
  const refresh = () => {
    measure();
    draw();
  };
  window.addEventListener('scroll', () => {
    if (!queued) {
      queued = true;
      requestAnimationFrame(draw);
    }
  }, { passive: true });
  // Posisi berubah bila konten di atasnya berubah
  const observer = new ResizeObserver(refresh);
  observer.observe(box);
  observer.observe(document.body);
  window.addEventListener('resize', refresh);
  refresh();
}
