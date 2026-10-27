// Smooth page scroll (Lenis). Lenis eases the real window scroll position, so
// every scroll listener (scenes, reveal) keeps working unchanged.
//
//   - in-page links (#kawasan, #lantai-2, ...) glide to their target
//   - horizontal wheel / trackpad gestures are left to the page: the turntable
//     rotates and the facilities carousel scrolls as before
//   - nested scroll areas (the type page panel) scroll on their own
//   - paused while a dialog (overlay, hamburger menu) is open
//   - off for visitors who prefer reduced motion (Lenis default)
import Lenis from 'lenis';
import 'lenis/dist/lenis.css';

// Exported so scroll-driven animations (masterplan.js) can follow it.
export const lenis = new Lenis({
  autoRaf: true,
  anchors: true,
  allowNestedScroll: true,
  virtualScroll: ({ event, deltaX, deltaY }) => !(event.type === 'wheel' && Math.abs(deltaX) > Math.abs(deltaY)),
});

for (const dialog of document.querySelectorAll('dialog')) {
  new MutationObserver(() => (dialog.open ? lenis.stop() : lenis.start())).observe(dialog, {
    attributes: true,
    attributeFilter: ['open'],
  });
}
