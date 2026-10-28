// Karusel fasilitas
for (const root of document.querySelectorAll('[data-carousel]')) {
  const track = root.querySelector('[data-track]');
  const bar = root.querySelector('[data-progress]');
  const slideWidth = () => track.firstElementChild.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap);

  for (const btn of root.querySelectorAll('[data-dir]')) {
    btn.addEventListener('click', () => track.scrollBy({ left: Number(btn.dataset.dir) * slideWidth(), behavior: 'smooth' }));
  }

  const update = () => {
    if (!bar) return; // Hanya tombol
    const max = track.scrollWidth - track.clientWidth;
    const visible = track.clientWidth / track.scrollWidth;
    bar.style.width = `${(visible + (max ? track.scrollLeft / max : 0) * (1 - visible)) * 100}%`;
  };
  track.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update);
  update();
}
