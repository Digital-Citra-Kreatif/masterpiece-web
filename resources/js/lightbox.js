// Lightbox galeri
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';

// Ukuran gambar
const sizes = new Map();
const sizeOf = (src) => {
  if (!sizes.has(src)) {
    sizes.set(src, new Promise((resolve) => {
      const img = new Image();
      img.onload = () => resolve({ w: img.naturalWidth, h: img.naturalHeight });
      img.onerror = () => resolve({ w: 1600, h: 1000 });
      img.src = src;
    }));
  }
  return sizes.get(src);
};

const lightbox = new PhotoSwipeLightbox({
  pswpModule: () => import('photoswipe'),
  bgOpacity: 0.92,
  showHideAnimationType: 'fade',
  closeTitle: 'Tutup',
  zoomTitle: 'Perbesar',
  arrowPrevTitle: 'Sebelumnya',
  arrowNextTitle: 'Berikutnya',
  errorMsg: 'Gambar tidak bisa dimuat.',
});

// Caption gambar
lightbox.on('uiRegister', () => {
  lightbox.pswp.ui.registerElement({
    name: 'caption',
    order: 9,
    isButton: false,
    appendTo: 'root',
    onInit: (el, pswp) => {
      pswp.on('change', () => {
        el.textContent = pswp.currSlide.data.caption || '';
      });
    },
  });
});
lightbox.init();

document.addEventListener(
  'click',
  async (e) => {
    const trigger = e.target.closest('[data-open="gallery"]');
    if (!trigger || !document.body.classList.contains('is-project')) return;
    e.preventDefault();
    e.stopPropagation();
    const { items = [], index = 0 } = JSON.parse(trigger.dataset.payload || '{}');
    const slides = await Promise.all(
      items.map(async (item) => ({ src: item.src, alt: item.alt || '', caption: item.caption || '', ...(await sizeOf(item.src)) })),
    );
    if (slides.length) lightbox.loadAndOpen(index, slides);
  },
  true,
);
