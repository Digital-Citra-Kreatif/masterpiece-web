// Tab bagian
const root = document.querySelector('[data-parts]');

if (root) {
  const parts = JSON.parse(root.dataset.parts);
  const tt = root.querySelector('[data-turntable]');
  const spin = root.querySelector('[data-part-spin]');
  const still = root.querySelector('[data-part-still]');
  const items = [...root.querySelectorAll('[data-part]')];
  const tabs = items.map((item) => item.querySelector('[data-part-tab]'));
  let active = 0;
  let loaded = tt?.querySelector('.tt__stage')?.dataset.config ?? null;

  // Gambar sendiri per tab
  function showFrames(p) {
    if (p.frames.length < 2) {
      still.src = p.frames[0];
      still.alt = p.name;
      still.hidden = false;
      if (spin) spin.hidden = true;
      return;
    }
    still.hidden = true;
    if (p.config !== loaded) {
      loaded = p.config;
      // Dipakai juga saat viewer belum jalan
      Object.assign(tt.querySelector('.tt__stage').dataset, { config: p.config, root: p.root, width: p.width, height: p.height });
      tt.dispatchEvent(new CustomEvent('tt:load', { detail: p }));
    }
    if (spin.hidden) {
      spin.hidden = false;
      tt.dispatchEvent(new CustomEvent('tt:resize'));
    }
  }

  function setPart(i) {
    if (i === active) return;
    active = i;
    showFrames(parts[i]);
    items.forEach((item, k) => {
      item.classList.toggle('is-open', k === i);
      tabs[k].setAttribute('aria-expanded', String(k === i));
    });
  }

  // Chevron gambar
  const move = (d) => setPart((active + d + tabs.length) % tabs.length);
  root.querySelector('[data-part-prev]')?.addEventListener('click', () => move(-1));
  root.querySelector('[data-part-next]')?.addEventListener('click', () => move(1));

  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => setPart(i));
    tab.addEventListener('keydown', (e) => {
      const step = { ArrowDown: 1, ArrowUp: -1 }[e.key];
      if (!step) return;
      e.preventDefault();
      const next = (active + step + tabs.length) % tabs.length;
      setPart(next);
      tabs[next].focus();
    });
  });
}
