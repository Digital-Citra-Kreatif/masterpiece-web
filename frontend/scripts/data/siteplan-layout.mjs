// Geometry of the placeholder siteplan (public/media/placeholder/siteplan.svg).
// Only used to draw that placeholder and to seed resources/data/units.json with dot
// positions that line up with it. Once the client supplies a real siteplan image,
// units.json holds the dot positions for that image and this file can go.

const blockRow = (y, h, ids) => [
  { id: ids[0], x: 120, w: 300 },
  { id: ids[1], x: 440, w: 300 },
  { id: ids[2], x: 860, w: 300 },
  { id: ids[3], x: 1180, w: 300 },
].map((b) => ({ ...b, y, h, units: 8, labelY: y < 300 ? y - 14 : y + h + 30 }));

export const siteplanLayout = {
  width: 1600,
  height: 1000,
  road: { y1: 150, y2: 560, h: 70, x: 760, w: 80 },
  blocks: [...blockRow(236, 150, ['A', 'B', 'C', 'D']), ...blockRow(394, 150, ['E', 'F', 'G', 'H'])],
};

// Dot centres as percentages of the siteplan image, one per unit.
export function unitDots() {
  const { width, height, blocks } = siteplanLayout;
  return blocks.flatMap((b) =>
    Array.from({ length: b.units }, (_, i) => {
      const w = b.w / b.units;
      const cy = b.y < 300 ? b.y + b.h * 0.35 : b.y + b.h * 0.65; // dot sits toward the unit's street front
      return {
        id: `${b.id}-${String(i + 1).padStart(2, '0')}`,
        x: +(((b.x + w * (i + 0.5)) / width) * 100).toFixed(2),
        y: +((cy / height) * 100).toFixed(2),
      };
    }),
  );
}
