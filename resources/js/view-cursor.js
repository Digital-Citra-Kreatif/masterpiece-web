// Kursor View, hanya untuk mouse
if (matchMedia('(hover: hover) and (pointer: fine)').matches) {
  const hosts = [...document.querySelectorAll('[data-view-cursor]')]
    .map((host) => ({ host, cursor: host.querySelector('[data-view-cursor-el]') }))
    .filter(({ cursor }) => cursor !== null);
  let pointer = null;

  // Posisi dan status dari titik pointer, juga saat halaman bergulir di bawahnya
  const update = () => {
    const under = pointer && document.elementFromPoint(pointer.x, pointer.y);
    for (const { host, cursor } of hosts) {
      const box = host.getBoundingClientRect();
      const on = under !== null && host.contains(under) && under.closest('[data-view-target]') !== null;
      if (on) {
        cursor.style.translate = `${pointer.x - box.left}px ${pointer.y - box.top}px`;
      }
      cursor.classList.toggle('is-on', on);
    }
  };

  for (const { host } of hosts) {
    host.classList.add('has-view-cursor');
  }
  addEventListener('pointermove', (event) => {
    pointer = { x: event.clientX, y: event.clientY };
    update();
  }, { passive: true });
  addEventListener('scroll', update, { passive: true });
  document.documentElement.addEventListener('pointerleave', () => {
    pointer = null;
    update();
  });
}
