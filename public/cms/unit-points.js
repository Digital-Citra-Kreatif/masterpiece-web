// Halaman Titik unit
const sunrice = globalThis.Sunrice;
if (!sunrice) throw new Error('[unit-points] Butuh panel admin Sunrice.');

const React = sunrice.modules.react;
const { router } = sunrice.modules['@inertiajs/react'];
const ui = sunrice.modules['@imajiner/admin-sdk/ui'];
const { useState, useRef, useMemo, useEffect, useCallback } = React;
const h = React.createElement;

const STATUS = { available: 'Tersedia', reserved: 'Dipesan', sold: 'Terjual' };
const ZOOMS = [1, 1.5, 2, 3];

const css = `
.up-layout { display: grid; gap: 16px; align-items: start; margin-top: 20px; }
.up-gap { margin-top: 20px; }
@media (min-width: 1100px) { .up-layout { grid-template-columns: minmax(0, 1fr) 300px; } }
.up-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border); }
.up-hint { margin: 0; font-size: 13px; color: var(--muted-foreground); }
.up-zoom { display: inline-flex; gap: 4px; }
.up-viewport { overflow: auto; max-height: min(78vh, 900px); background: var(--muted); border-radius: 0 0 var(--radius) var(--radius); overscroll-behavior: contain; }
.up-plan { position: relative; margin: 0 auto; user-select: none; touch-action: none; cursor: crosshair; }
.up-plan.is-idle { cursor: default; }
.up-plan img { display: block; width: 100%; height: auto; pointer-events: none; }
.up-dot { position: absolute; width: 24px; height: 24px; margin: -12px 0 0 -12px; padding: 0; border: 0; background: none; cursor: grab; display: grid; place-items: center; }
.up-dot::before { content: ''; width: 12px; height: 12px; border-radius: 50%; background: var(--up-c); box-shadow: 0 0 0 2px #fff, 0 1px 3px rgb(0 0 0 / 0.45); transition: transform 150ms var(--ease-out-quart, ease-out); }
.up-dot:hover::before { transform: scale(1.25); }
.up-dot.is-on { z-index: 2; }
.up-dot.is-on::before { transform: scale(1.5); box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--primary), 0 1px 3px rgb(0 0 0 / 0.45); }
.up-dot.is-drag { cursor: grabbing; }
.up-dot:focus-visible { outline: none; }
.up-dot:focus-visible::before { box-shadow: 0 0 0 2px #fff, 0 0 0 5px var(--ring); }
.up-tag { position: absolute; left: 50%; bottom: calc(100% + 2px); transform: translateX(-50%); padding: 1px 6px; border-radius: 4px; background: var(--foreground); color: var(--background); font-size: 11px; font-weight: 600; white-space: nowrap; pointer-events: none; }
.up-available { --up-c: #16a34a; }
.up-reserved { --up-c: #d97706; }
.up-sold { --up-c: #dc2626; }
.up-list { list-style: none; margin: 0; padding: 4px; max-height: min(52vh, 560px); overflow: auto; }
.up-row { display: flex; width: 100%; align-items: center; gap: 10px; min-height: 36px; padding: 6px 10px; border: 0; border-radius: calc(var(--radius) - 2px); background: none; color: inherit; font: inherit; font-size: 14px; text-align: left; cursor: pointer; }
.up-row:hover { background: color-mix(in oklab, var(--muted) 60%, transparent); }
.up-row[aria-pressed='true'] { background: var(--accent-tint, var(--accent)); }
.up-row:focus-visible { outline: 2px solid var(--ring); outline-offset: -2px; }
.up-swatch { width: 10px; height: 10px; flex: none; border-radius: 50%; background: var(--up-c); }
.up-code { font-weight: 600; font-variant-numeric: tabular-nums; }
.up-meta { margin-left: auto; font-size: 12px; color: var(--muted-foreground); font-variant-numeric: tabular-nums; }
.up-meta.is-missing { color: var(--destructive, #b91c1c); }
.up-side-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; padding: 14px 16px 10px; border-bottom: 1px solid var(--border); }
.up-side-head h2 { margin: 0; font-size: 15px; font-weight: 600; }
.up-side-head p { margin: 0; font-size: 12px; color: var(--muted-foreground); }
.up-detail { display: grid; gap: 12px; padding: 14px 16px 16px; border-top: 1px solid var(--border); }
.up-detail-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.up-detail-head strong { font-size: 15px; }
.up-detail-head a { font-size: 13px; color: var(--primary); text-underline-offset: 3px; }
.up-xy { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.up-xy label { display: grid; gap: 4px; font-size: 12px; color: var(--muted-foreground); }
.up-empty { display: grid; gap: 10px; justify-items: start; padding: 32px 24px; }
.up-empty p { margin: 0; color: var(--muted-foreground); max-width: 48ch; }
.up-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--muted-foreground); }
.up-legend span { display: inline-flex; align-items: center; gap: 6px; }
@media (prefers-reduced-motion: reduce) { .up-dot::before { transition: none; } }
`;

function useStyles() {
  useEffect(() => {
    if (document.getElementById('up-styles')) return;
    const style = document.createElement('style');
    style.id = 'up-styles';
    style.textContent = css;
    document.head.append(style);
  }, []);
}

const round = (n) => Math.round(Math.min(100, Math.max(0, n)) * 100) / 100;
const placed = (u) => u.x !== null && u.y !== null;

function UnitPoints({ projects, project, units: initial, createUrl, canUpdate }) {
  useStyles();
  ui.useBreadcrumbs([{ label: 'Titik unit' }]);

  const [units, setUnits] = useState(initial);
  const [selected, setSelected] = useState(() => (initial.find((u) => !placed(u)) ?? initial[0])?.id ?? null);
  const [zoom, setZoom] = useState(1);
  const [saving, setSaving] = useState(false);
  const [drag, setDrag] = useState(null);
  const planRef = useRef(null);

  useEffect(() => setUnits(initial), [initial]);

  const original = useMemo(() => new Map(initial.map((u) => [u.id, u])), [initial]);
  const changed = units.filter((u) => {
    const o = original.get(u.id);
    return o && (o.x !== u.x || o.y !== u.y);
  });
  const dirty = changed.length > 0;
  const current = units.find((u) => u.id === selected) ?? null;
  const missing = units.filter((u) => !placed(u)).length;

  const save = useCallback(() => {
    if (!project || !dirty) return;
    setSaving(true);
    router.put(
      sunrice.adminUrl(`unit-points/${project.id}`),
      { points: changed.map(({ id, x, y }) => ({ id, x, y })) },
      { preserveScroll: true, preserveState: true, onFinish: () => setSaving(false) },
    );
  }, [project, dirty, changed]);

  ui.useUnsavedChanges(dirty, save);

  const move = (id, x, y) => setUnits((list) => list.map((u) => (u.id === id ? { ...u, x: x === null ? null : round(x), y: y === null ? null : round(y) } : u)));

  const pointAt = (event) => {
    const rect = planRef.current.getBoundingClientRect();
    return [((event.clientX - rect.left) / rect.width) * 100, ((event.clientY - rect.top) / rect.height) * 100];
  };

  // Klik siteplan
  const onPlanClick = (event) => {
    if (!canUpdate || !current || event.target !== event.currentTarget) return;
    const [x, y] = pointAt(event);
    move(current.id, x, y);
    // Lanjut unit kosong
    const next = units.find((u) => u.id !== current.id && !placed(u));
    if (!placed(current) && next) setSelected(next.id);
  };

  const onDotDown = (event, unit) => {
    setSelected(unit.id);
    if (!canUpdate || event.button !== 0) return;
    event.currentTarget.setPointerCapture(event.pointerId);
    setDrag(unit.id);
  };
  const onDotMove = (event, unit) => {
    if (drag !== unit.id) return;
    const [x, y] = pointAt(event);
    move(unit.id, x, y);
  };
  const onDotKey = (event, unit) => {
    const step = event.shiftKey ? 1 : 0.1;
    const delta = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[event.key];
    if (!delta || !canUpdate) return;
    event.preventDefault();
    move(unit.id, unit.x + delta[0], unit.y + delta[1]);
  };

  const setAxis = (axis, value) => {
    if (!current) return;
    const n = value === '' ? null : Number(value);
    if (n !== null && Number.isNaN(n)) return;
    const other = axis === 'x' ? current.y : current.x;
    if (n === null) return move(current.id, null, null);
    move(current.id, axis === 'x' ? n : other ?? 50, axis === 'y' ? n : other ?? 50);
  };

  const header = h(ui.PageHeader, {
    title: 'Titik unit',
    description: 'Posisi setiap unit di siteplan proyek. Pilih unit lalu klik siteplan, atau geser titiknya.',
    menu: [
      ...(project ? [{ label: 'Buka proyek', href: project.editUrl }] : []),
      { label: 'Tambah unit', href: createUrl },
    ],
    actions: h(
      'div',
      { style: { display: 'flex', gap: 8, alignItems: 'center' } },
      projects.length > 1 &&
        h(
          ui.Select,
          {
            value: project ? String(project.id) : undefined,
            onValueChange: (id) => router.get(sunrice.adminUrl('unit-points'), { project: id }),
          },
          h(ui.SelectTrigger, { 'aria-label': 'Proyek', style: { minWidth: 200 } }, h(ui.SelectValue, null)),
          h(ui.SelectContent, null, projects.map((p) => h(ui.SelectItem, { key: p.id, value: String(p.id) }, p.title))),
        ),
      canUpdate && h(ui.Button, { onClick: save, disabled: !dirty || saving }, saving ? 'Menyimpan…' : dirty ? `Simpan ${changed.length} titik` : 'Simpan'),
    ),
  });

  if (!project) {
    return h('div', null, header, h(ui.Card, { className: 'up-gap' }, empty('Belum ada proyek. Buat proyek dulu di menu Projects.')));
  }
  if (!project.siteplan) {
    return h('div', null, header, h(ui.Card, { className: 'up-gap' }, empty('Proyek ini belum punya siteplan. Unggah gambarnya di Ketersediaan unit → Siteplan.', { href: project.editUrl, label: 'Buka proyek' })));
  }

  const plan = h(
    ui.Card,
    { style: { padding: 0, gap: 0, overflow: 'hidden' } },
    h(
      'div',
      { className: 'up-bar' },
      h(
        'div',
        { className: 'up-legend' },
        Object.entries(STATUS).map(([key, label]) => h('span', { key, className: `up-${key}` }, h('i', { className: 'up-swatch' }), label)),
      ),
      h(
        'div',
        { className: 'up-zoom', role: 'group', 'aria-label': 'Perbesar siteplan' },
        ZOOMS.map((z) => h(ui.Button, { key: z, size: 'sm', variant: z === zoom ? 'secondary' : 'ghost', 'aria-pressed': z === zoom, onClick: () => setZoom(z) }, `${z * 100}%`)),
      ),
    ),
    h(
      'div',
      { className: 'up-viewport' },
      h(
        'div',
        {
          ref: planRef,
          className: `up-plan${current && canUpdate ? '' : ' is-idle'}`,
          style: { width: `${zoom * 100}%`, maxWidth: zoom === 1 ? project.siteplan.width : 'none' },
          onClick: onPlanClick,
        },
        h('img', { src: project.siteplan.src, alt: `Siteplan ${project.title}`, width: project.siteplan.width, height: project.siteplan.height, draggable: false }),
        units.filter(placed).map((u) =>
          h(
            'button',
            {
              key: u.id,
              type: 'button',
              className: `up-dot up-${u.status}${u.id === selected ? ' is-on' : ''}${drag === u.id ? ' is-drag' : ''}`,
              style: { left: `${u.x}%`, top: `${u.y}%` },
              'aria-label': `${u.code}, ${STATUS[u.status] ?? u.status}`,
              'aria-pressed': u.id === selected,
              onPointerDown: (e) => onDotDown(e, u),
              onPointerMove: (e) => onDotMove(e, u),
              onPointerUp: () => setDrag(null),
              onPointerCancel: () => setDrag(null),
              onKeyDown: (e) => onDotKey(e, u),
              onFocus: () => setSelected(u.id),
            },
            (u.id === selected || drag === u.id) && h('span', { className: 'up-tag' }, u.code),
          ),
        ),
      ),
    ),
  );

  const side = h(
    ui.Card,
    { style: { padding: 0, gap: 0, overflow: 'hidden' } },
    h(
      'div',
      { className: 'up-side-head' },
      h('h2', null, `${units.length} unit`),
      h('p', null, missing ? `${missing} belum ditempatkan` : 'Semua sudah ditempatkan'),
    ),
    units.length === 0
      ? empty('Belum ada unit untuk proyek ini.', { href: createUrl, label: 'Tambah unit' })
      : h(
          'ul',
          { className: 'up-list', 'aria-label': 'Unit' },
          units.map((u) =>
            h(
              'li',
              { key: u.id },
              h(
                'button',
                { type: 'button', className: `up-row up-${u.status}`, 'aria-pressed': u.id === selected, onClick: () => setSelected(u.id) },
                h('i', { className: 'up-swatch', 'aria-hidden': true }),
                h('span', { className: 'up-code' }, u.code),
                h('span', { className: `up-meta${placed(u) ? '' : ' is-missing'}` }, placed(u) ? `${u.x}, ${u.y}` : 'Belum ada titik'),
              ),
            ),
          ),
        ),
    current &&
      h(
        'div',
        { className: 'up-detail' },
        h('div', { className: 'up-detail-head' }, h('strong', null, current.code), h('a', { href: current.editUrl }, 'Edit unit')),
        h(
          'div',
          { className: 'up-xy' },
          ['x', 'y'].map((axis) =>
            h(
              'label',
              { key: axis },
              `Posisi ${axis.toUpperCase()} (%)`,
              h(ui.Input, {
                type: 'number',
                min: 0,
                max: 100,
                step: 0.01,
                value: current[axis] ?? '',
                disabled: !canUpdate,
                onChange: (e) => setAxis(axis, e.target.value),
              }),
            ),
          ),
        ),
        canUpdate && placed(current) && h(ui.Button, { variant: 'outline', size: 'sm', onClick: () => move(current.id, null, null) }, 'Hapus titik'),
        !placed(current) && h('p', { className: 'up-hint' }, canUpdate ? 'Klik siteplan untuk menempatkan unit ini.' : 'Unit ini belum punya titik.'),
      ),
  );

  return h('div', null, header, h('div', { className: 'up-layout' }, plan, side));
}

function empty(text, action) {
  return h('div', { className: 'up-empty' }, h('p', null, text), action && h(ui.Button, { asChild: true, variant: 'outline' }, h('a', { href: action.href }, action.label)));
}

if (sunrice.version !== 3) console.warn(`[unit-points] Dibuat untuk runtime admin 3, panel memakai ${sunrice.version}.`);
sunrice.registerPage('App/UnitPoints', UnitPoints);
