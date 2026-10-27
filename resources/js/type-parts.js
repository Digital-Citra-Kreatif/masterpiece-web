// Type page parts as tabs: a tab turns the one 360° sequence to that part's
// keyframe (turntable.js, `tt:goto`) and swaps its text in above the tabs.
// ↑/↓ (and ←/→) move between tabs, as the tabs pattern expects.
const root = document.querySelector('[data-parts]');

if (root) {
  const parts = JSON.parse(root.dataset.parts);
  const tt = root.querySelector('[data-turntable]');
  const tabs = [...root.querySelectorAll('[data-part-tab]')];
  const panel = root.querySelector('[data-part-panel]');
  const text = root.querySelector('[data-part-text]');
  let active = 0;

  function setPart(i) {
    if (i === active) return;
    active = i;
    const p = parts[i];
    tt.dataset.goto = String(p.frame); // picked up if the viewer is still starting
    tt.dispatchEvent(new CustomEvent('tt:goto', { detail: { frame: p.frame } }));
    text.textContent = p.text;
    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
      text.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], { duration: 400, easing: 'ease-out' });
    }
    tabs.forEach((tab, k) => {
      tab.setAttribute('aria-selected', String(k === i));
      tab.tabIndex = k === i ? 0 : -1;
    });
    panel.setAttribute('aria-labelledby', tabs[i].id);
  }

  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => setPart(i));
    tab.addEventListener('keydown', (e) => {
      const step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[e.key];
      if (!step) return;
      e.preventDefault();
      const next = (active + step + tabs.length) % tabs.length;
      setPart(next);
      tabs[next].focus();
    });
  });
}
