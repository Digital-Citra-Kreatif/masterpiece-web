// Warna header
const DARK = '.hero, .scene, .room, .tps--spin';

const header = document.querySelector('.hd');
if (header) {
  let queued = false;

  /** Latar bawah header */
  const toneAt = (y) => {
    for (const el of document.elementsFromPoint(innerWidth / 2, y)) {
      // Lewati overlay
      if (header.contains(el) || !el.closest('main, footer')) continue;
      return el.closest(DARK) ? 'dark' : 'light';
    }
    return 'light';
  };

  const update = () => {
    queued = false;
    header.dataset.tone = toneAt(header.offsetHeight / 2);
  };

  const request = () => {
    if (!queued) {
      queued = true;
      requestAnimationFrame(update);
    }
  };

  addEventListener('scroll', request, { passive: true });
  addEventListener('resize', request, { passive: true });
  update();
}
