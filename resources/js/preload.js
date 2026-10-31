// Preload belah layar
const root = document.querySelector('[data-preload]');
const html = document.documentElement;

// Selesai duluan terbaca
const line = root.querySelector('.pl__line--right').getAnimations()[0];
const drawn = line ? line.finished.catch(() => {}) : Promise.resolve();
const loaded = document.readyState === 'complete' ? Promise.resolve() : new Promise((resolve) => addEventListener('load', resolve, { once: true }));
const late = new Promise((resolve) => setTimeout(resolve, 6000)); // Jangan tahan lama

Promise.all([drawn, Promise.race([loaded, late])]).then(() => {
  root.classList.add('is-open');
  root.querySelector('.pl__half--top').addEventListener('transitionend', () => html.classList.remove('is-preload'), { once: true });
});
