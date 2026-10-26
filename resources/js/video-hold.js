// A paused <video> keeps its download open, and two such downloads on a page
// held up every picture after them (seconds, on a project page). A video that
// stops being needed (off screen, or handed over to the frames) lets go of its
// file; it takes it back, at the same time, when it plays again. The file comes
// back from the browser's cache.

/** Pause and drop the file, remembering where it was. */
export function release(video) {
  if (!video.getAttribute('src')) return;
  video.dataset.time = String(video.currentTime);
  video.pause();
  video.removeAttribute('src');
  video.load();
}

/** Take the file back (from data-src), at the time it was released. */
export function resume(video) {
  if (video.getAttribute('src') || !video.dataset.src) return;
  video.src = video.dataset.src;
  if (video.dataset.time) video.currentTime = Number(video.dataset.time);
}
