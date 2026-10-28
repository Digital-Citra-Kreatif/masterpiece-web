// Smooth scroll Lenis
import Lenis from 'lenis';
import 'lenis/dist/lenis.css';

// Ekspor Lenis
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
