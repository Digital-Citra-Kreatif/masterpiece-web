// Tab bagian
const root = document.querySelector('[data-parts]');

if (root) {
  const parts = JSON.parse(root.dataset.parts);
  const tt = root.querySelector('[data-turntable]');
  const items = [...root.querySelectorAll('[data-part]')];
  const tabs = items.map((item) => item.querySelector('[data-part-tab]'));
  let active = 0;

  function setPart(i) {
    if (i === active) return;
    active = i;
    const p = parts[i];
    tt.dataset.goto = String(p.frame); // Diambil saat siap
    tt.dispatchEvent(new CustomEvent('tt:goto', { detail: { frame: p.frame } }));
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
