// Lepas unduhan video

/** Jeda dan lepas */
export function release(video) {
  if (!video.getAttribute('src')) return;
  video.dataset.time = String(video.currentTime);
  video.pause();
  video.removeAttribute('src');
  video.load();
}

/** Muat ulang video */
export function resume(video) {
  if (video.getAttribute('src') || !video.dataset.src) return;
  video.src = video.dataset.src;
  if (video.dataset.time) video.currentTime = Number(video.dataset.time);
}
