// Header menempel dan bertema
const DARK = 'theme_on-dark';
const COLOR = 'theme_on-color';

/**
 * Pasang perilaku header
 *
 * @returns {() => void} Lepas listener
 */
export function initHeader() {
  const header = document.querySelector('.hd');
  if (!header) return () => {};

  let lastY = scrollY;
  let queued = false;

  /** Tema section tepat di bawah header */
  const themeAt = (y) => {
    for (const el of document.elementsFromPoint(innerWidth / 2, y)) {
      if (header.contains(el)) continue;
      const section = el.closest(`.${DARK}, .${COLOR}`);
      if (!section) return 'light';
      return section.classList.contains(DARK) ? 'dark' : 'color';
    }
    return 'light';
  };

  const update = () => {
    queued = false;
    const y = Math.max(scrollY, 0);
    const height = header.offsetHeight;
    header.classList.toggle('is-stuck', y > 4);
    // Abaikan getar kecil
    if (Math.abs(y - lastY) > 6) {
      header.classList.toggle('is-hidden', y > lastY && y > height);
      lastY = y;
    }
    header.dataset.theme = themeAt(height / 2);
  };

  const onScroll = () => {
    if (!queued) {
      queued = true;
      requestAnimationFrame(update);
    }
  };

  addEventListener('scroll', onScroll, { passive: true });
  addEventListener('resize', onScroll, { passive: true });
  update();

  return () => {
    removeEventListener('scroll', onScroll);
    removeEventListener('resize', onScroll);
  };
}
