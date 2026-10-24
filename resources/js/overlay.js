// Shared overlay: full video, gallery lightbox, VR iframe, hotspot info.
//
// Declarative:  <button data-open="gallery" data-payload='{"title":"…","items":[{"src":"…"}],"index":0}'>
// Programmatic: window.drOverlay.open('info', { kicker, title, text, image })

const dlg = document.getElementById('overlay');
const $ = (sel) => dlg.querySelector(sel);

const els = {
  title: $('.ov__title'),
  count: $('.ov__count'),
  ext: $('.ov__ext'),
  video: $('.ov__video'),
  gallery: $('.ov__gallery'),
  img: $('.ov__img'),
  caption: $('.ov__caption'),
  prev: $('.ov__prev'),
  next: $('.ov__next'),
  frame: $('.ov__frame'),
  info: $('.ov__info'),
  infoImg: $('.ov__info-img'),
  infoTitle: $('.ov__info-title'),
  infoBody: $('.ov__info-body'),
};

let gallery = { items: [], index: 0 };

function reset() {
  for (const el of [els.video, els.gallery, els.frame, els.info, els.count, els.ext]) el.hidden = true;
  els.video.pause();
  els.video.removeAttribute('src');
  els.video.load();
  els.frame.removeAttribute('src');
  dlg.classList.remove('is-info');
}

function showGalleryItem(i) {
  const { items } = gallery;
  gallery.index = (i + items.length) % items.length;
  const item = items[gallery.index];
  els.img.src = item.src;
  els.img.alt = item.alt || item.caption || '';
  els.caption.textContent = item.caption || '';
  els.count.textContent = `${gallery.index + 1} / ${items.length}`;
  els.count.hidden = items.length < 2;
  els.prev.hidden = els.next.hidden = items.length < 2;
}

const openers = {
  video({ src, title }) {
    els.title.textContent = title || '';
    els.video.hidden = false;
    els.video.src = src;
    els.video.play().catch(() => {});
  },
  gallery({ items, index = 0, title }) {
    els.title.textContent = title || '';
    gallery = { items, index };
    els.gallery.hidden = false;
    showGalleryItem(index);
  },
  iframe({ src, title }) {
    els.title.textContent = title || '';
    els.frame.title = title || '';
    els.frame.hidden = false;
    els.frame.src = src;
    els.ext.href = src;
    els.ext.hidden = false;
  },
  info({ kicker, title, text, image }) {
    dlg.classList.add('is-info');
    els.title.textContent = kicker || '';
    els.info.hidden = false;
    els.infoTitle.textContent = title || '';
    els.infoBody.textContent = text || '';
    els.infoImg.hidden = !image;
    if (image) {
      els.infoImg.src = image;
      els.infoImg.alt = title || '';
    }
  },
};

function open(kind, payload = {}) {
  reset();
  openers[kind](payload);
  if (!dlg.open) dlg.showModal();
  $('.ov__close').focus();
}

function close() {
  if (dlg.open) dlg.close();
}

dlg.addEventListener('close', reset);
$('.ov__close').addEventListener('click', close);
els.prev.addEventListener('click', () => showGalleryItem(gallery.index - 1));
els.next.addEventListener('click', () => showGalleryItem(gallery.index + 1));

// Backdrop click (the dialog itself, outside the content) closes.
dlg.addEventListener('click', (e) => {
  if (e.target === dlg || e.target === $('.ov__body')) close();
});

dlg.addEventListener('keydown', (e) => {
  if (els.gallery.hidden) return;
  if (e.key === 'ArrowLeft') showGalleryItem(gallery.index - 1);
  if (e.key === 'ArrowRight') showGalleryItem(gallery.index + 1);
});

// Swipe between gallery images on touch screens.
let swipeX = null;
els.gallery.addEventListener('pointerdown', (e) => (swipeX = e.clientX));
els.gallery.addEventListener('pointerup', (e) => {
  if (swipeX === null) return;
  const dx = e.clientX - swipeX;
  swipeX = null;
  if (Math.abs(dx) > 50) showGalleryItem(gallery.index + (dx < 0 ? 1 : -1));
});

document.addEventListener('click', (e) => {
  const trigger = e.target.closest('[data-open]');
  if (!trigger) return;
  e.preventDefault();
  open(trigger.dataset.open, JSON.parse(trigger.dataset.payload || '{}'));
});

window.drOverlay = { open, close };
