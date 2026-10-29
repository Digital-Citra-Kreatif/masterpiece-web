// Proteksi media
const MEDIA = 'img, video, canvas, picture, .hero, .scene__stage, .tps__stage, .ov__body';
const all = document.body.hasAttribute('data-protect-media');

if (all || document.querySelector('[data-protect]')) {
  const block = (e) => {
    if (!(e.target instanceof Element)) return;
    // Teks atas media
    if (e.target.closest('.hero__body')) return;
    const media = e.target.closest(MEDIA);
    if (media && (all || media.closest('[data-protect]'))) e.preventDefault();
  };
  document.addEventListener('contextmenu', block);
  document.addEventListener('dragstart', block);
}
