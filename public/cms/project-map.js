// Peta titik unit di form proyek
const EDITOR = /\/admin\/cms\/collections\/projects\/entries\/(\d+)\/edit/;
// Field Siteplan proyek
const FIELD = '[role="group"][aria-labelledby="data-availability-siteplan-label"]';
const STATUS = { available: 'Tersedia', reserved: 'Dipesan', sold: 'Terjual' };
const ZOOMS = [1, 1.5, 2, 3];

const css = `
.pm { margin-top: 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); overflow: hidden; scroll-margin-top: 96px; }
.pm-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid var(--border); }
.pm-title { display: grid; gap: 2px; }
.pm-title strong { font-size: 14px; }
.pm-title span, .pm-note { font-size: 12px; color: var(--muted-foreground); }
.pm-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.pm-zoom { display: inline-flex; gap: 2px; }
.pm-viewport { overflow: auto; max-height: 70vh; background: var(--muted); overscroll-behavior: contain; }
.pm-plan { position: relative; margin: 0 auto; user-select: none; touch-action: none; cursor: crosshair; }
.pm-plan.is-idle { cursor: default; }
.pm-plan img { display: block; width: 100%; height: auto; pointer-events: none; }
.pm-dot { position: absolute; width: 24px; height: 24px; margin: -12px 0 0 -12px; padding: 0; border: 0; background: none; cursor: grab; display: grid; place-items: center; }
.pm-dot::before { content: ''; width: 12px; height: 12px; border-radius: 50%; background: var(--pm-c); box-shadow: 0 0 0 2px #fff, 0 1px 3px rgb(0 0 0 / 0.45); transition: transform 150ms var(--ease-out-quart, ease-out); }
.pm-dot:hover::before { transform: scale(1.25); }
.pm-dot.is-on { z-index: 2; }
.pm-dot.is-on::before { transform: scale(1.5); box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--primary), 0 1px 3px rgb(0 0 0 / 0.45); }
.pm-dot.is-drag { cursor: grabbing; }
.pm-dot:focus-visible { outline: none; }
.pm-dot:focus-visible::before { box-shadow: 0 0 0 2px #fff, 0 0 0 5px var(--ring); }
.pm-tag { position: absolute; left: 50%; bottom: calc(100% + 2px); transform: translateX(-50%); padding: 1px 6px; border-radius: 4px; background: var(--foreground); color: var(--background); font-size: 11px; font-weight: 600; white-space: nowrap; pointer-events: none; }
.pm-available { --pm-c: #16a34a; }
.pm-reserved { --pm-c: #d97706; }
.pm-sold { --pm-c: #dc2626; }
.pm-orbit { --pm-c: var(--primary); }
.pm-legend { display: flex; flex-wrap: wrap; gap: 12px; padding: 8px 12px; font-size: 12px; color: var(--muted-foreground); border-top: 1px solid var(--border); }
.pm-legend span { display: inline-flex; align-items: center; gap: 6px; }
.pm-swatch { width: 10px; height: 10px; flex: none; border-radius: 50%; background: var(--pm-c); }
.pm-units { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 4px; max-height: 220px; overflow: auto; margin: 0; padding: 8px 12px; list-style: none; border-top: 1px solid var(--border); }
.pm-chip { display: flex; width: 100%; align-items: center; gap: 6px; min-height: 32px; padding: 4px 8px; border: 1px solid transparent; border-radius: calc(var(--radius) - 2px); background: none; color: inherit; font: inherit; font-size: 13px; font-weight: 600; font-variant-numeric: tabular-nums; text-align: left; cursor: pointer; }
.pm-chip:hover { background: color-mix(in oklab, var(--muted) 60%, transparent); }
.pm-chip[aria-pressed='true'] { background: var(--accent-tint, var(--accent)); border-color: var(--border); }
.pm-chip.is-missing { color: var(--muted-foreground); font-weight: 500; }
.pm-chip.is-missing .pm-swatch { background: none; box-shadow: inset 0 0 0 1.5px var(--pm-c); }
.pm-chip:focus-visible { outline: 2px solid var(--ring); outline-offset: -2px; }
.pm-detail { display: flex; flex-wrap: wrap; align-items: end; gap: 8px 12px; padding: 10px 12px 12px; border-top: 1px solid var(--border); }
.pm-detail strong { align-self: center; min-width: 48px; font-size: 14px; }
.pm-detail label { display: grid; gap: 4px; width: 110px; font-size: 12px; color: var(--muted-foreground); }
.pm-empty { display: grid; gap: 10px; justify-items: start; padding: 16px 12px; }
.pm-empty p { margin: 0; font-size: 13px; color: var(--muted-foreground); max-width: 56ch; }
@media (prefers-reduced-motion: reduce) { .pm-dot::before { transition: none; } }
`;

const round = (n) => Math.round(Math.min(100, Math.max(0, n)) * 100) / 100;
const placed = (u) => u.x !== null && u.y !== null;
const xsrf = () => decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

function setup(sunrice) {
  const React = sunrice.modules.react;
  const { createRoot } = sunrice.modules['react-dom/client'];
  const { router } = sunrice.modules['@inertiajs/react'];
  const ui = sunrice.modules['@imajiner/admin-sdk/ui'];
  const { useState, useRef, useMemo, useEffect, useCallback } = React;
  const h = React.createElement;
  const url = (project) => sunrice.adminUrl(`unit-points/${project}`);
  const notify = (text, error = false) => (error ? ui.toast?.error ?? ui.toast : ui.toast?.success ?? ui.toast)?.(text);
  const noEnter = (event) => event.key === 'Enter' && event.preventDefault();

  function ProjectMap({ project, host }) {
    const [data, setData] = useState(null);
    const [failed, setFailed] = useState(false);
    const [units, setUnits] = useState([]);
    const [selected, setSelected] = useState(null);
    const [zoom, setZoom] = useState(1);
    const [saving, setSaving] = useState(false);
    const [drag, setDrag] = useState(null);
    const planRef = useRef(null);
    const dirtyRef = useRef(false);

    const load = useCallback(async () => {
      try {
        const response = await fetch(url(project), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error(response.status);
        const json = await response.json();
        setData(json);
        setUnits(json.units);
        setSelected((id) => (json.units.some((u) => u.id === id) ? id : (json.units.find((u) => !placed(u)) ?? json.units[0])?.id ?? null));
        setFailed(false);
      } catch {
        setFailed(true);
      }
    }, [project]);

    useEffect(() => { load(); }, [load]);

    // Tampil hanya jika ada unit
    useEffect(() => { host.hidden = !failed && !data?.units.length; }, [host, data, failed]);

    const original = useMemo(() => new Map((data?.units ?? []).map((u) => [u.id, u])), [data]);
    const changed = units.filter((u) => { const o = original.get(u.id); return o && (o.x !== u.x || o.y !== u.y); });
    const dirty = changed.length > 0;
    dirtyRef.current = dirty;
    const current = units.find((u) => u.id === selected) ?? null;
    const missing = units.filter((u) => !placed(u)).length;
    const canUpdate = data?.canUpdate ?? false;

    // Jaga perubahan belum simpan
    useEffect(() => {
      const beforeUnload = (event) => { if (dirtyRef.current) event.preventDefault(); };
      const offBefore = router.on('before', (event) => {
        if (!dirtyRef.current || event.detail.visit.method !== 'get') return true;
        return window.confirm('Titik unit belum disimpan. Tinggalkan halaman ini?');
      });
      // Siteplan baru setelah proyek disimpan
      const offSuccess = router.on('success', () => { if (!dirtyRef.current) load(); });
      addEventListener('beforeunload', beforeUnload);
      return () => { offBefore(); offSuccess(); removeEventListener('beforeunload', beforeUnload); };
    }, [load]);

    const save = async () => {
      setSaving(true);
      try {
        const response = await fetch(url(project), {
          method: 'PUT',
          credentials: 'same-origin',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf() },
          body: JSON.stringify({ points: changed.map(({ id, x, y }) => ({ id, x, y })) }),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(json.message ?? 'Gagal menyimpan titik.');
        setData((d) => ({ ...d, units }));
        notify(json.message);
      } catch (error) {
        notify(error.message || 'Gagal menyimpan titik.', true);
      } finally {
        setSaving(false);
      }
    };

    const move = (id, x, y) => setUnits((list) => list.map((u) => (u.id === id ? { ...u, x: x === null ? null : round(x), y: y === null ? null : round(y) } : u)));
    const pointAt = (event) => {
      const rect = planRef.current.getBoundingClientRect();
      return [((event.clientX - rect.left) / rect.width) * 100, ((event.clientY - rect.top) / rect.height) * 100];
    };
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
      if (event.key === 'Enter') event.preventDefault();
      if (!delta || !canUpdate) return;
      event.preventDefault();
      move(unit.id, unit.x + delta[0], unit.y + delta[1]);
    };
    const setAxis = (axis, value) => {
      if (!current) return;
      if (value === '') return move(current.id, null, null);
      const n = Number(value);
      if (Number.isNaN(n)) return;
      move(current.id, axis === 'x' ? n : current.x ?? 50, axis === 'y' ? n : current.y ?? 50);
    };
    const button = (props, label) => h(ui.Button, { type: 'button', ...props }, label);

    if (failed) return h('div', { className: 'pm-empty' }, h('p', null, 'Peta titik unit gagal dimuat.'), button({ variant: 'outline', size: 'sm', onClick: load }, 'Coba lagi'));
    if (!data?.units.length) return null;

    const bar = h(
      'div',
      { className: 'pm-bar' },
      h('div', { className: 'pm-title' }, h('strong', null, 'Titik unit'), h('span', null, `${units.length} unit · ${missing ? `${missing} belum ditempatkan` : 'semua sudah ditempatkan'}`)),
      data.siteplan && h(
        'div',
        { className: 'pm-tools' },
        h('div', { className: 'pm-zoom', role: 'group', 'aria-label': 'Perbesar siteplan' },
          ZOOMS.map((z) => button({ key: z, size: 'sm', variant: z === zoom ? 'secondary' : 'ghost', 'aria-pressed': z === zoom, onClick: () => setZoom(z) }, `${z * 100}%`))),
        canUpdate && button({ size: 'sm', onClick: save, disabled: !dirty || saving }, saving ? 'Menyimpan…' : dirty ? `Simpan ${changed.length} titik` : 'Simpan titik'),
      ),
    );

    if (!data.siteplan) {
      return h('div', null, bar, h('div', { className: 'pm-empty' }, h('p', null, 'Pilih gambar Siteplan lalu simpan proyek; petanya muncul di sini.')));
    }

    const plan = h(
      'div',
      { className: 'pm-viewport' },
      h(
        'div',
        { ref: planRef, className: `pm-plan${current && canUpdate ? '' : ' is-idle'}`, style: { width: `${zoom * 100}%` }, onClick: onPlanClick },
        h('img', { src: data.siteplan.src, alt: 'Siteplan', width: data.siteplan.width, height: data.siteplan.height, draggable: false }),
        units.filter(placed).map((u) => h(
          'button',
          {
            key: u.id,
            type: 'button',
            className: `pm-dot pm-${u.status}${u.id === selected ? ' is-on' : ''}${drag === u.id ? ' is-drag' : ''}`,
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
          (u.id === selected || drag === u.id) && h('span', { className: 'pm-tag' }, u.code),
        )),
      ),
    );

    const legend = h('div', { className: 'pm-legend' },
      Object.entries(STATUS).map(([key, label]) => h('span', { key, className: `pm-${key}` }, h('i', { className: 'pm-swatch' }), label)));

    const list = h('ul', { className: 'pm-units', 'aria-label': 'Unit' },
      units.map((u) => h('li', { key: u.id }, h(
        'button',
        { type: 'button', className: `pm-chip pm-${u.status}${placed(u) ? '' : ' is-missing'}`, 'aria-pressed': u.id === selected, title: placed(u) ? `${u.x}, ${u.y}` : 'Belum ada titik', onClick: () => setSelected(u.id) },
        h('i', { className: 'pm-swatch', 'aria-hidden': true }),
        u.code,
      ))));

    const detail = current && h(
      'div',
      { className: 'pm-detail' },
      h('strong', null, current.code),
      ['x', 'y'].map((axis) => h('label', { key: axis }, `Posisi ${axis.toUpperCase()} (%)`, h(ui.Input, {
        type: 'number', min: 0, max: 100, step: 0.01, value: current[axis] ?? '', disabled: !canUpdate,
        onChange: (e) => setAxis(axis, e.target.value), onKeyDown: noEnter,
      }))),
      canUpdate && placed(current) && button({ variant: 'outline', size: 'sm', onClick: () => move(current.id, null, null) }, 'Hapus titik'),
      !placed(current) && h('span', { className: 'pm-note' }, canUpdate ? 'Klik peta untuk menempatkan unit ini.' : 'Belum ada titik.'),
    );

    return h('div', null, bar, plan, legend, list, detail);
  }

  // Titik orbit Masterplan dan Cluster: isi X/Y repeater lewat klik gambar
  const rowsPattern = (section) => new RegExp(`^data-${section}-points-(\\d+)-fields$`);
  const inputOf = (section, index, key) => document.getElementById(`data-${section}-points-${index}-${key}`);
  const setInput = (el, value) => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
  };
  const readRows = (group, section) => [...group.querySelectorAll(`[id^="data-${section}-points-"][id$="-fields"]`)]
    .map((el) => {
      const index = Number(el.id.match(rowsPattern(section))?.[1] ?? NaN);
      if (Number.isNaN(index)) return null;
      const num = (key) => { const v = inputOf(section, index, key)?.value; return v === undefined || v === '' ? null : Number(v); };
      const toggle = group.querySelector(`button[aria-controls="${el.id}"]`);
      const name = inputOf(section, index, 'name')?.value || toggle?.textContent.trim() || `Titik ${index + 1}`;
      return { index, name, x: num('x'), y: num('y'), open: !el.hidden };
    })
    .filter(Boolean)
    .sort((a, b) => a.index - b.index);

  function OrbitPicker({ section, title, frame, group }) {
    const [, setTick] = useState(0);
    const [selected, setSelected] = useState(null);
    const [drag, setDrag] = useState(null);
    const planRef = useRef(null);
    const rows = readRows(group, section);
    const current = rows.find((r) => r.index === selected) ?? null;
    const missing = rows.filter((r) => !placed(r)).length;

    // Baca ulang saat form berubah
    useEffect(() => {
      const bump = () => setTick((t) => t + 1);
      const observer = new MutationObserver(bump);
      observer.observe(group, { subtree: true, childList: true, attributes: true, attributeFilter: ['hidden'] });
      group.addEventListener('input', bump);
      // Baris tertutup dibuka agar X/Y terbaca
      for (const button of group.querySelectorAll(`button[aria-controls^="data-${section}-points-"][aria-expanded="false"]`)) button.click();
      return () => { observer.disconnect(); group.removeEventListener('input', bump); };
    }, [group, section]);

    useEffect(() => {
      if (selected === null && rows.length) setSelected((rows.find((r) => !placed(r)) ?? rows[0]).index);
    }, [rows.length, selected]);

    const write = (index, x, y) => {
      const ix = inputOf(section, index, 'x');
      const iy = inputOf(section, index, 'y');
      if (!ix || !iy) return;
      setInput(ix, x === null ? '' : String(round(x)));
      setInput(iy, y === null ? '' : String(round(y)));
    };
    const pointAt = (event) => {
      const rect = planRef.current.getBoundingClientRect();
      return [((event.clientX - rect.left) / rect.width) * 100, ((event.clientY - rect.top) / rect.height) * 100];
    };
    const onPlanClick = (event) => {
      if (!current || event.target !== event.currentTarget) return;
      const [x, y] = pointAt(event);
      write(current.index, x, y);
      // Lanjut titik kosong
      const next = rows.find((r) => r.index !== current.index && !placed(r));
      if (!placed(current) && next) setSelected(next.index);
    };
    const onDotKey = (event, row) => {
      const step = event.shiftKey ? 1 : 0.1;
      const delta = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[event.key];
      if (event.key === 'Enter') event.preventDefault();
      if (!delta) return;
      event.preventDefault();
      write(row.index, row.x + delta[0], row.y + delta[1]);
    };

    const bar = h('div', { className: 'pm-bar' },
      h('div', { className: 'pm-title' },
        h('strong', null, `Penempatan ${title.toLowerCase()}`),
        h('span', null, rows.length ? `${rows.length} titik · ${missing ? `${missing} belum ditempatkan` : 'semua sudah ditempatkan'} · simpan lewat tombol Simpan proyek` : 'Tambah titik dulu, lalu tempatkan di gambar.')));
    if (!frame) return h('div', null, bar, h('div', { className: 'pm-empty' }, h('p', null, 'Gambar orbit belum tersedia.')));
    if (!rows.length) return h('div', null, bar);

    const plan = h('div', { className: 'pm-viewport' },
      h('div', { ref: planRef, className: `pm-plan${current ? '' : ' is-idle'}`, onClick: onPlanClick },
        h('img', { src: frame.src, alt: `Gambar pertama orbit ${section}`, width: frame.width, height: frame.height, draggable: false }),
        rows.filter(placed).map((r) => h('button', {
          key: r.index,
          type: 'button',
          className: `pm-dot pm-orbit${r.index === selected ? ' is-on' : ''}${drag === r.index ? ' is-drag' : ''}`,
          style: { left: `${r.x}%`, top: `${r.y}%` },
          'aria-label': r.name,
          'aria-pressed': r.index === selected,
          onPointerDown: (e) => { setSelected(r.index); if (e.button !== 0) return; e.currentTarget.setPointerCapture(e.pointerId); setDrag(r.index); },
          onPointerMove: (e) => { if (drag !== r.index) return; const [x, y] = pointAt(e); write(r.index, x, y); },
          onPointerUp: () => setDrag(null),
          onPointerCancel: () => setDrag(null),
          onKeyDown: (e) => onDotKey(e, r),
          onFocus: () => setSelected(r.index),
        }, (r.index === selected || drag === r.index) && h('span', { className: 'pm-tag' }, r.name)))));

    const list = h('ul', { className: 'pm-units', 'aria-label': title },
      rows.map((r) => h('li', { key: r.index }, h('button', {
        type: 'button',
        className: `pm-chip pm-orbit${placed(r) ? '' : ' is-missing'}`,
        'aria-pressed': r.index === selected,
        title: placed(r) ? `${r.x}, ${r.y}` : 'Belum ada titik',
        onClick: () => setSelected(r.index),
      }, h('i', { className: 'pm-swatch', 'aria-hidden': true }), r.name))));

    const detail = current && h('div', { className: 'pm-detail' },
      h('strong', null, current.name),
      placed(current)
        ? button({ variant: 'outline', size: 'sm', onClick: () => write(current.index, null, null) }, 'Hapus posisi')
        : h('span', { className: 'pm-note' }, 'Klik gambar untuk menempatkan titik ini.'));

    return h('div', null, bar, plan, list, detail);
  }
  const button = (props, label) => h(ui.Button, { type: 'button', ...props }, label);

  const ORBITS = [{ section: 'masterplan', title: 'Titik kawasan' }, { section: 'cluster', title: 'Titik cluster' }];
  let frames = null;
  let framesFor = null; // Proyek pemilik frame
  const orbitMounts = new Map();
  // Frame per proyek: gambar CMS atau frame orbit
  const loadFrames = (project) => {
    framesFor = project;
    fetch(sunrice.adminUrl(`orbit-frames?project=${project}`), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : {}))
      .catch(() => ({}))
      .then((json) => {
        if (framesFor !== project) return;
        frames = json;
        orbitMounts.forEach((m) => m.render());
      });
  };
  // Muat ulang setelah simpan
  router.on('success', () => {
    const project = location.pathname.match(EDITOR)?.[1];
    if (project && orbitMounts.size) loadFrames(project);
  });
  const syncOrbits = () => {
    const editing = EDITOR.test(location.pathname);
    const project = location.pathname.match(EDITOR)?.[1] ?? null;
    if (project !== framesFor) {
      frames = null;
      framesFor = null;
    }
    for (const { section, title } of ORBITS) {
      const label = editing && document.getElementById(`data-${section}-points-label`);
      const group = label && (label.closest('[role="group"]') ?? label.parentElement?.parentElement);
      const mount = orbitMounts.get(section);
      if (!group) {
        if (mount && !mount.el.isConnected) { mount.root.unmount(); orbitMounts.delete(section); }
        continue;
      }
      if (mount?.el.isConnected && mount.group === group) continue;
      if (mount) { mount.root.unmount(); mount.el.remove(); }
      const el = document.createElement('div');
      el.className = 'pm';
      group.append(el);
      const root = createRoot(el);
      const render = () => root.render(h(OrbitPicker, { section, title, frame: frames?.[section] ?? null, group }));
      orbitMounts.set(section, { el, root, group, render });
      if (frames) {
        render();
      } else if (framesFor !== project) {
        loadFrames(project);
      }
    }
  };

  let mounted = null;

  // Pasang di bawah Siteplan
  const sync = () => {
    const match = location.pathname.match(EDITOR);
    const field = match && document.querySelector(FIELD);
    if (!field) {
      if (mounted && !mounted.el.isConnected) { mounted.root.unmount(); mounted = null; }
      return;
    }
    if (mounted?.el.isConnected && mounted.project === match[1]) return;
    if (mounted) { mounted.root.unmount(); mounted.el.remove(); }

    const el = document.createElement('div');
    el.id = 'peta-titik-unit';
    el.className = 'pm';
    el.hidden = true;
    field.append(el);
    const root = createRoot(el);
    root.render(h(ProjectMap, { project: Number(match[1]), host: el }));
    mounted = { el, root, project: match[1] };
  };

  const style = document.createElement('style');
  style.textContent = css;
  document.head.append(style);

  let frame = 0;
  const syncAll = () => { sync(); syncOrbits(); };
  new MutationObserver(() => { cancelAnimationFrame(frame); frame = requestAnimationFrame(syncAll); }).observe(document.body, { childList: true, subtree: true });
  syncAll();
}

// Tunggu runtime panel
(function wait(tries = 0) {
  if (globalThis.Sunrice) return setup(globalThis.Sunrice);
  if (tries < 200) setTimeout(() => wait(tries + 1), 50);
})();
