// Type page parts: the step on screen picks the part. Changing part animates the
// one sequence to that part's keyframe (turntable.js, `tt:goto`) and swaps the
// text, denah and highlights.
const track = document.querySelector('[data-parts]');

if (track) {
  const parts = JSON.parse(track.dataset.parts);
  const tt = track.querySelector('[data-turntable]');
  const $ = (sel) => [...document.querySelectorAll(sel)];
  const name = track.querySelector('[data-part-name]');
  const text = track.querySelector('[data-part-text]');
  let active = 0;

  function setPart(i) {
    if (i === active) return;
    active = i;
    const p = parts[i];
    tt.dataset.goto = String(p.frame); // picked up if the viewer is still starting
    tt.dispatchEvent(new CustomEvent('tt:goto', { detail: { frame: p.frame } }));
    name.textContent = p.name;
    text.textContent = p.text;
    for (const el of [name, text]) {
      el.animate([{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'none' }], { duration: 450, easing: 'ease-out' });
    }
    $('[data-part-denah]').forEach((b) => (b.hidden = Number(b.dataset.partDenah) !== i));
    $('[data-part-dot]').forEach((d, k) => d.classList.toggle('is-active', k === i));
    $('[data-part-link]').forEach((a) => {
      const on = Number(a.dataset.partLink) === i;
      a.classList.toggle('is-active', on);
      on ? a.setAttribute('aria-current', 'step') : a.removeAttribute('aria-current');
    });
  }

  const io = new IntersectionObserver(
    (entries) => {
      for (const e of entries) if (e.isIntersecting) setPart(Number(e.target.dataset.partStep));
    },
    { rootMargin: '-50% 0px -50% 0px' },
  );
  $('[data-part-step]').forEach((s) => io.observe(s));
}
