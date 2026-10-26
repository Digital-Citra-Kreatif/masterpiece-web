// With "Lindungi gambar & video" on (CMS: Globals → Situs, <body data-protect-media>),
// the browser's own "Save image / Save video / Copy image" shortcuts are closed:
// no context menu and no dragging on media or on the full-screen media areas
// (hero, scroll scenes, 360° viewer, overlay). Long-press on phones is off in
// base.css, and the overlay video hides its download button (overlay.blade.php).
// Text keeps its normal menu, so copying words still works.
//
// Proteksi [data-protect]
const MEDIA = 'img, video, canvas, picture, .hero, .scene__stage, .tt__stage, .ov__body';
const all = document.body.hasAttribute('data-protect-media');

if (all || document.querySelector('[data-protect]')) {
  const block = (e) => {
    if (!(e.target instanceof Element)) return;
    // Teks di atas media
    if (e.target.closest('.hero__body')) return;
    const media = e.target.closest(MEDIA);
    if (media && (all || media.closest('[data-protect]'))) e.preventDefault();
  };
  document.addEventListener('contextmenu', block);
  document.addEventListener('dragstart', block);
}
