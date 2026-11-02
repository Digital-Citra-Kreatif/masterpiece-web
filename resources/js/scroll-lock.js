// Kunci gulir halaman
const owners = new Set();

/**
 * Kunci saat dibuka
 *
 * @param {object} owner
 * @param {boolean} locked
 */
export const lockScroll = (owner, locked) => {
  locked ? owners.add(owner) : owners.delete(owner);
  document.documentElement.classList.toggle('is-scroll-locked', owners.size > 0);
};

for (const dialog of document.querySelectorAll('dialog')) {
  new MutationObserver(() => lockScroll(dialog, dialog.open)).observe(dialog, {
    attributes: true,
    attributeFilter: ['open'],
  });
}
