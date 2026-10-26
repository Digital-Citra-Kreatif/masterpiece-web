<?php

namespace App\Support;

use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Models\EntryTranslation;

/**
 * Turns a Projects entry (blueprint `project`) into the array its page,
 * resources/views/pages/project.blade.php, reads.
 *
 * Each section is read by one method below (masterplan(), facilities(), …),
 * so a field that changes shape in the blueprint is remapped in one place.
 * A section with nothing to show is null: the page and the header nav skip it.
 *
 * The Global Style (applied only while its "Aktif" toggle is on) becomes a
 * small stylesheet, style(), that overrides the homepage's own tokens
 * (resources/css/base.css); every empty value keeps the homepage's.
 */
final class ProjectPage
{
    /**
     * Global Style colours → the homepage tokens they replace.
     *
     *   color_1 → --accent        brand colour: rules under headings, solid buttons' hover, progress bars, markers
     *   color_2 → --accent-ink    the brand colour's darker step (solid buttons)
     *   color_3 → --accent-light  the brand colour on dark bands (focus ring, labels)
     *   color_4 → --paper         page background
     *   color_5 → --paper-2       secondary surface (image frames, cards)
     *   color_6 → --ink           text and solid buttons
     *   color_7 → --room          dark bands: hero, masterplan, footer
     *   color_8 → --room-ink      text on the dark bands
     *
     * @var array<string, string>
     */
    public const PALETTE = [
        'color_1' => '--accent',
        'color_2' => '--accent-ink',
        'color_3' => '--accent-light',
        'color_4' => '--paper',
        'color_5' => '--paper-2',
        'color_6' => '--ink',
        'color_7' => '--room',
        'color_8' => '--room-ink',
    ];

    /**
     * Global Style font fields → the font token each one sets: headings,
     * body text, buttons. The homepage font stays as the fallback.
     *
     * @var array<string, string>
     */
    public const FONTS = [
        'heading' => '--display',
        'description' => '--sans',
        'button' => '--button',
    ];

    /** The sections in page order, with their anchor and the header link's label (the client's nav). */
    public const SECTIONS = [
        'masterplan' => ['id' => 'masterplan', 'label' => 'Masterplan'],
        'location' => ['id' => 'location', 'label' => 'Location'],
        'cluster' => ['id' => 'cluster', 'label' => 'Cluster'],
        'facilities' => ['id' => 'facility', 'label' => 'Facility'],
        'types' => ['id' => 'type', 'label' => 'Type'],
        'availability' => ['id' => 'unit', 'label' => 'Unit'],
    ];

    /** @var array<string, string> */
    public const VIEWS = ['streetview' => 'Street View', 'lakeview' => 'Lake View'];

    private const FALLBACK_FONT = "'DM Sans Variable', 'Helvetica Neue', Arial, sans-serif";

    /**
     * @param  array<string, mixed>  $values
     * @return array{title: string, style: array{css: string, logo: ?array<string, mixed>, favicon: ?array{href: string, type: string}, marker: ?array{shape: ?string, image: ?string}}, hero: array<string, mixed>, sections: array<string, array<string, mixed>>, nav: list<array{href: string, label: string, active: bool, newTab: bool}>, whatsapp: ?array<string, mixed>}
     */
    public static function page(EntryTranslation $entry, array $values): array
    {
        $masterplan = self::masterplan(self::group($values, 'masterplan'));
        $sections = array_filter([
            'masterplan' => $masterplan,
            'location' => self::location(self::group($values, 'location')),
            'cluster' => self::cluster(self::group($values, 'cluster')),
            'facilities' => self::facilities(self::group($values, 'facilities')),
            'types' => self::types(self::group($values, 'types')),
            'availability' => self::availability(self::group($values, 'availability')),
        ]);

        // The points' paths through the orbit, once made for their current places (ProjectTracks).
        foreach (array_keys(ProjectTracks::sections()) as $handle) {
            if (isset($sections[$handle]['scene'])) {
                $placed = ProjectTracks::placed($sections[$handle]['points'], $handle);
                $sections[$handle]['scene']['tracks'] = ProjectTracks::for((string) $entry->slug, $handle, $placed);
            }
        }

        return [
            'title' => (string) $entry->title,
            'style' => self::style(self::group($values, 'global_style')),
            'hero' => self::hero(self::group($values, 'hero'), (string) $entry->title),
            'sections' => $sections,
            'nav' => collect(self::SECTIONS)
                ->filter(fn (array $section, string $handle): bool => isset($sections[$handle]))
                ->map(fn (array $section): array => ['href' => '#'.$section['id'], 'label' => $section['label'], 'active' => false, 'newTab' => false])
                ->values()
                ->all(),
            'whatsapp' => self::whatsapp(self::group($values, 'whatsapp')),
        ];
    }

    /**
     * The Global Style as CSS (fonts and token overrides), the header logo and
     * the marker drawn beside numbered points. Inactive: nothing of it.
     *
     * @param  array<string, mixed>  $style
     * @return array{css: string, logo: ?array<string, mixed>, marker: ?array{shape: ?string, image: ?string}}
     */
    public static function style(array $style): array
    {
        if (($style['active'] ?? false) !== true) {
            return ['css' => '', 'logo' => null, 'favicon' => null, 'marker' => null];
        }

        $faces = [];
        $tokens = [];
        $fonts = is_array($style['fonts'] ?? null) ? $style['fonts'] : [];
        foreach (self::FONTS as $field => $token) {
            $font = $fonts[$field] ?? null;
            $format = $font instanceof Media ? self::fontFormat($font) : null;
            if ($format === null) {
                continue;
            }
            $family = "project-{$field}";
            $faces[] = "@font-face{font-family:'{$family}';src:url(".self::cssString($font->url()).") format('{$format}');font-display:swap;}";
            $tokens[$token] = "'{$family}', ".self::FALLBACK_FONT;
        }

        $palette = is_array($style['palette'] ?? null) ? $style['palette'] : [];
        foreach (self::PALETTE as $field => $token) {
            if (self::isHex($palette[$field] ?? null)) {
                $tokens[$token] = strtolower($palette[$field]);
            }
        }

        $marker = is_array($style['marker'] ?? null) ? $style['marker'] : [];
        if (self::isHex($marker['color'] ?? null)) {
            $tokens['--marker'] = strtolower($marker['color']);
        }

        $corner = is_array($style['corner'] ?? null) ? $style['corner'] : [];
        $size = is_numeric($corner['size'] ?? null) ? max(0, (int) $corner['size']) : 0;
        if ($size > 0) {
            if (($corner['style'] ?? 'rounded') === 'chamfer') {
                $cut = "{$size}px";
                $tokens['--frame-clip'] = "polygon({$cut} 0, calc(100% - {$cut}) 0, 100% {$cut}, 100% calc(100% - {$cut}), calc(100% - {$cut}) 100%, {$cut} 100%, 0 calc(100% - {$cut}), 0 {$cut})";
            } else {
                $tokens['--radius'] = "{$size}px";
            }
        }

        $background = $style['background'] ?? null;
        if ($background instanceof Media && $background->isImage()) {
            $tokens['--theme-bg'] = 'url('.self::cssString($background->url('large')).')';
        }

        $css = implode('', $faces);
        if ($tokens !== []) {
            $css .= ':root{'.collect($tokens)->map(fn (string $value, string $token): string => "{$token}:{$value};")->implode('').'}';
        }

        $markerImage = ($marker['type'] ?? 'shape') === 'image' ? ($marker['image'] ?? null) : null;

        return [
            'css' => $css,
            'logo' => self::image($style['logo'] ?? null, 'medium'),
            // The project's logo is also its pages' browser icon (the original: the sized copies crop or are larger).
            'favicon' => ($style['logo'] ?? null) instanceof Media && $style['logo']->isImage()
                ? ['href' => $style['logo']->url(), 'type' => $style['logo']->mime_type]
                : null,
            'marker' => match (true) {
                $markerImage instanceof Media => ['shape' => null, 'image' => $markerImage->url('thumbnail')],
                in_array($marker['shape'] ?? null, ['circle', 'square', 'diamond', 'pin'], true) => ['shape' => $marker['shape'], 'image' => null],
                default => null,
            },
        ];
    }

    /**
     * The hero: the homepage's banner component, as a slider of the media
     * (one image: still; none: the dark band alone).
     *
     * @param  array<string, mixed>  $hero
     * @return array{title: string, subtitle: string, background: string, slides: list<array{type: string, src: string, alt: string}>}
     */
    private static function hero(array $hero, string $fallbackTitle): array
    {
        $slides = collect($hero['media'] ?? [])
            ->filter(fn (mixed $media): bool => $media instanceof Media)
            ->map(fn (Media $media): array => [
                'type' => $media->isImage() ? 'image' : 'video',
                'src' => $media->isImage() ? $media->url('large') : $media->url(),
                'alt' => $media->altIn() ?? '',
            ])
            ->values()
            ->all();

        return [
            'title' => filled($hero['heading'] ?? null) ? (string) $hero['heading'] : $fallbackTitle,
            'subtitle' => (string) ($hero['description'] ?? ''),
            'background' => $slides === [] ? 'none' : 'slider',
            'slides' => $slides,
        ];
    }

    /**
     * Numbered points over the aerial map; those with a picture open it, with
     * their gallery. A point is placed on the map only with both X and Y (% of
     * the map); the others stay in the list.
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, map: ?array<string, mixed>, points: list<array{number: ?string, name: string, image: ?array<string, mixed>, gallery: list<array<string, mixed>>, x: ?float, y: ?float}>, scene: ?array<string, mixed>}|null
     */
    private static function masterplan(array $section): ?array
    {
        $percent = fn (mixed $value): ?float => is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : null;
        $points = collect($section['points'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null))
            ->map(function (array $row) use ($percent): array {
                [$x, $y] = [$percent($row['x'] ?? null), $percent($row['y'] ?? null)];

                return [
                    'number' => filled($row['number'] ?? null) ? (string) $row['number'] : null,
                    'name' => (string) $row['name'],
                    'image' => self::image($row['image'] ?? null),
                    'gallery' => self::images($row['gallery'] ?? []),
                    'x' => $x !== null && $y !== null ? $x : null,
                    'y' => $x !== null && $y !== null ? $y : null,
                ];
            })
            ->values()
            ->all();

        $map = self::image($section['map'] ?? null);

        // The homepage's orbit (video, then the frames turning with the scroll; it is this area's render)
        // with the CMS's points as its hotspots: x/y are % of the orbit's first frame, scroll-scene.js
        // carries them through the other frames. Without a placed point, the four sample hotspots.
        $hotspots = self::hotspots($points, 'masterplan');
        $scene = $points === [] ? null : [...Content::sampleArea(), ...($hotspots !== [] ? ['hotspots' => $hotspots] : [])];

        return self::section($section, ['map' => $map, 'points' => $points, 'scene' => $scene], $points !== []);
    }

    /**
     * The location maps and the named spots on the first one (Lokasi → Titik
     * peta, x/y % of the map), shown on hover. The area's figures (Angka
     * kawasan) stay in the CMS but are no longer shown.
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, maps: list<array<string, mixed>>, pins: list<array{name: string, x: float, y: float}>}|null
     */
    private static function location(array $section): ?array
    {
        $maps = self::images($section['maps'] ?? []);
        $pins = collect($section['pins'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null) && is_numeric($row['x'] ?? null) && is_numeric($row['y'] ?? null))
            ->map(fn (array $row): array => [
                'name' => (string) $row['name'],
                'x' => max(0.0, min(100.0, (float) $row['x'])),
                'y' => max(0.0, min(100.0, (float) $row['y'])),
            ])
            ->values()
            ->all();

        return self::section($section, ['maps' => $maps, 'pins' => $maps === [] ? [] : $pins], $maps !== []);
    }

    /**
     * Cluster: the homepage's Cluster orbit with the CMS's points as its
     * hotspots (x/y % of the orbit's first frame, carried through the other
     * frames by scroll-scene.js); without a placed point, its sample hotspots.
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, points: list<array{number: ?string, name: string, description: ?string, image: ?array<string, mixed>, x: ?float, y: ?float}>, scene: ?array<string, mixed>}|null
     */
    private static function cluster(array $section): ?array
    {
        $percent = fn (mixed $value): ?float => is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : null;
        $points = collect($section['points'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null))
            ->map(function (array $row) use ($percent): array {
                [$x, $y] = [$percent($row['x'] ?? null), $percent($row['y'] ?? null)];

                return [
                    'number' => filled($row['number'] ?? null) ? (string) $row['number'] : null,
                    'name' => (string) $row['name'],
                    'description' => filled($row['description'] ?? null) ? (string) $row['description'] : null,
                    'image' => self::image($row['image'] ?? null),
                    'x' => $x !== null && $y !== null ? $x : null,
                    'y' => $x !== null && $y !== null ? $y : null,
                ];
            })
            ->values()
            ->all();

        $hotspots = self::hotspots($points, 'cluster');
        $scene = $points === [] ? null : [...Content::sampleCluster(), ...($hotspots !== [] ? ['hotspots' => $hotspots] : [])];

        return self::section($section, ['points' => $points, 'scene' => $scene], $points !== []);
    }

    /**
     * The points with a position as scroll-scene hotspots (x, y %), keyed {prefix}-{n}.
     *
     * @param  list<array<string, mixed>>  $points
     * @return array<string, array{number: ?string, x: float, y: float, title: string, short: string, text: string, image: ?string}>
     */
    private static function hotspots(array $points, string $prefix): array
    {
        return collect($points)
            ->filter(fn (array $point): bool => $point['x'] !== null && $point['y'] !== null)
            ->mapWithKeys(fn (array $point, int $index): array => ["{$prefix}-".($index + 1) => [
                'number' => $point['number'] ?? null,
                'x' => $point['x'],
                'y' => $point['y'],
                'title' => $point['name'],
                'short' => $point['name'],
                'text' => (string) ($point['description'] ?? ''),
                'image' => $point['image']['src'] ?? null,
            ]])
            ->all();
    }

    /**
     * The facilities carousel (the homepage's component): Facilities entries
     * (title, image, description) or rows of the same fields; only those with
     * a picture, a slide is a picture first.
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, items: list<array{title: string, description: ?string, image: string}>}|null
     */
    private static function facilities(array $section): ?array
    {
        $items = collect($section['items'] ?? [])
            ->map(fn (mixed $item): ?array => self::row($item))
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null) && ($row['image'] ?? null) instanceof Media)
            ->map(fn (array $row): array => [
                'title' => (string) $row['name'],
                'description' => filled($row['description'] ?? null) ? (string) $row['description'] : null,
                'image' => $row['image']->url('large'),
            ])
            ->values()
            ->all();

        return self::section($section, ['items' => $items], $items !== []);
    }

    /**
     * The cutaway, the advantages, and the unit types grouped by view (the
     * view's own copy heading its group), then the specifications.
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>|null
     */
    private static function types(array $section): ?array
    {
        $advantages = collect($section['advantages'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null))
            ->map(fn (array $row): array => [
                'name' => (string) $row['name'],
                'description' => filled($row['description'] ?? null) ? (string) $row['description'] : null,
                'image' => self::image($row['image'] ?? null, 'medium'),
            ])
            ->values()
            ->all();

        $views = collect($section['views'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && isset(self::VIEWS[$row['view'] ?? '']))
            ->keyBy('view');

        $units = collect($section['units'] ?? [])
            ->map(fn (mixed $item): ?array => self::row($item, ['label' => 'category']))
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null))
            ->values()
            ->map(fn (array $row, int $index): array => self::unit($row, $index));

        // The views' order in the CMS, then any view only a unit names.
        $groups = $views->keys()->merge($units->pluck('view')->filter())->unique()
            ->map(function (string $view) use ($views, $units): array {
                $copy = $views->get($view, []);

                return [
                    'view' => $view,
                    'name' => filled($copy['name'] ?? null) ? (string) $copy['name'] : self::VIEWS[$view],
                    'description' => filled($copy['description'] ?? null) ? (string) $copy['description'] : null,
                    'businesses' => self::lines($copy['businesses'] ?? null),
                    'units' => $units->where('view', $view)->values()->all(),
                ];
            })
            ->values();
        $unsorted = $units->whereNull('view')->values()->all();
        if ($unsorted !== []) {
            $groups->push(['view' => null, 'name' => null, 'description' => null, 'businesses' => [], 'units' => $unsorted]);
        }

        $specifications = collect($section['specifications'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['label'] ?? null) && filled($row['value'] ?? null))
            ->map(fn (array $row): array => ['label' => (string) $row['label'], 'value' => (string) $row['value']])
            ->values()
            ->all();

        $heroImage = self::image($section['hero_image'] ?? null);

        return self::section($section, [
            'heroImage' => $heroImage,
            'advantages' => $advantages,
            'groups' => $groups->all(),
            // The homepage's type cards (components/home/type-grid): a Types entry links to its own page,
            // a unit without one (a row) opens its sheet.
            'cards' => $groups->flatMap(fn (array $group): array => $group['units'])
                ->map(fn (array $unit): array => [
                    'name' => $unit['name'],
                    'label' => $unit['category'],
                    'url' => $unit['url'],
                    'sheet' => $unit['url'] === null ? $unit['id'] : null,
                    'image' => $unit['image']['src'] ?? null,
                    'size' => collect($unit['facts'])->firstWhere(0, 'Size')[1] ?? null,
                    'specs' => collect($unit['facts'])->reject(fn (array $fact): bool => $fact[0] === 'Size')->values()->all(),
                ])
                ->values()
                ->all(),
            'specifications' => $specifications,
        ], $heroImage !== null || $advantages !== [] || $units->isNotEmpty() || $specifications !== []);
    }

    /**
     * One unit type, a Types entry (or a row of the same fields): its card
     * and its detail sheet.
     *
     * @param  array<string, mixed>  $row
     * @return array{id: string, view: ?string, viewName: ?string, name: string, category: ?string, facts: list<array{0: string, 1: string}>, image: ?array<string, mixed>, plans: list<array<string, mixed>>, gallery: list<array<string, mixed>>, vr: ?string, url: ?string}
     */
    private static function unit(array $row, int $index): array
    {
        $view = isset(self::VIEWS[$row['view'] ?? '']) ? $row['view'] : null;
        $facts = array_values(array_filter([
            filled($row['dimensions'] ?? null) ? ['Size', (string) $row['dimensions']] : null,
            is_numeric($row['lt'] ?? null) && $row['lt'] > 0 ? ['LT', self::number($row['lt']).' m²'] : null,
            is_numeric($row['lb'] ?? null) && $row['lb'] > 0 ? ['LB', self::number($row['lb']).' m²'] : null,
            is_numeric($row['floors'] ?? null) && $row['floors'] > 0 ? ['Floor', (string) (int) $row['floors']] : null,
        ]));

        return [
            'id' => 'unit-'.($index + 1),
            'view' => $view,
            'viewName' => $view === null ? null : self::VIEWS[$view],
            'name' => (string) $row['name'],
            'category' => filled($row['category'] ?? null) ? (string) $row['category'] : null,
            'facts' => $facts,
            'image' => self::image($row['image'] ?? null, 'medium'),
            'plans' => collect($row['floor_plans'] ?? [])
                ->filter(fn (mixed $plan): bool => is_array($plan) && self::image($plan['image'] ?? null) !== null)
                ->map(fn (array $plan): array => [...self::image($plan['image']), 'label' => (string) ($plan['label'] ?? '')])
                ->values()
                ->all(),
            'gallery' => self::images($row['gallery'] ?? []),
            // A published tour's address, or a path on this site (/vr/…).
            'vr' => preg_match('#^(https?://|/)#', (string) ($row['vr_url'] ?? '')) === 1 ? url((string) $row['vr_url']) : null,
            'url' => $row['url'] ?? null,
        ];
    }

    /**
     * The siteplan and each block's plan.
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, siteplan: ?array<string, mixed>, blocks: list<array<string, mixed>>}|null
     */
    private static function availability(array $section): ?array
    {
        $siteplan = self::image($section['siteplan'] ?? null);
        $blocks = collect($section['blocks'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null) && self::image($row['image'] ?? null) !== null)
            ->map(fn (array $row): array => [
                ...self::image($row['image'], 'medium'),
                'name' => (string) $row['name'],
                'viewName' => self::VIEWS[$row['view'] ?? ''] ?? null,
            ])
            ->values()
            ->all();

        // The units' status dots on the siteplan (the homepage's Unit update), and how many of each.
        $units = collect($section['units'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['code'] ?? null) && is_numeric($row['x'] ?? null) && is_numeric($row['y'] ?? null))
            ->map(fn (array $row): array => [
                'id' => (string) $row['code'],
                'x' => (float) $row['x'],
                'y' => (float) $row['y'],
                'status' => in_array($row['status'] ?? null, Content::STATUSES, true) ? $row['status'] : 'available',
            ])
            ->values();
        $counts = collect(Content::STATUSES)->mapWithKeys(fn (string $status): array => [$status => $units->where('status', $status)->count()])->all();

        return self::section($section, ['siteplan' => $siteplan, 'blocks' => $blocks, 'units' => $units->all(), 'counts' => $counts], $siteplan !== null || $blocks !== []);
    }

    /**
     * The WhatsApp floating button: only with at least one contact with a
     * number, and unless "Tampilkan tombol" is switched off.
     *
     * @param  array<string, mixed>  $whatsapp
     * @return array{label: string, heading: ?string, description: ?string, image: ?array<string, mixed>, contacts: list<array{title: string, avatar: ?string, href: string}>}|null
     */
    private static function whatsapp(array $whatsapp): ?array
    {
        $contacts = collect($whatsapp['contacts'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && preg_match('/^[0-9]{8,15}$/', (string) ($row['number'] ?? '')) === 1)
            ->map(fn (array $row): array => [
                'title' => filled($row['title'] ?? null) ? (string) $row['title'] : '+'.$row['number'],
                'avatar' => ($row['avatar'] ?? null) instanceof Media ? $row['avatar']->url('thumbnail') : null,
                'href' => 'https://wa.me/'.$row['number'].(filled($row['message'] ?? null) ? '?text='.rawurlencode((string) $row['message']) : ''),
            ])
            ->values()
            ->all();

        if ($contacts === [] || ($whatsapp['show_button'] ?? null) === false) {
            return null;
        }

        return [
            'label' => filled($whatsapp['label'] ?? null) ? (string) $whatsapp['label'] : 'WhatsApp',
            'heading' => filled($whatsapp['heading'] ?? null) ? (string) $whatsapp['heading'] : null,
            'description' => filled($whatsapp['description'] ?? null) ? (string) $whatsapp['description'] : null,
            'image' => self::image($whatsapp['image'] ?? null, 'medium'),
            'contacts' => $contacts,
        ];
    }

    /**
     * A section's heading plus its content, or null when it has nothing to show.
     *
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>|null
     */
    private static function section(array $section, array $content, bool $hasContent): ?array
    {
        $title = filled($section['title'] ?? null) ? (string) $section['title'] : null;
        if ($title === null && ! $hasContent) {
            return null;
        }

        return [
            'title' => $title ?? '',
            'intro' => filled($section['description'] ?? null) ? (string) $section['description'] : null,
            ...$content,
        ];
    }

    /**
     * A row of a section: a repeater row as it is, or an entry picked in an
     * entries field as one (its title as `name`, its page as `url`, its
     * fields, some under the section's names: $rename field → row key).
     * Null for anything else.
     *
     * @param  array<string, string>  $rename
     * @return array<string, mixed>|null
     */
    private static function row(mixed $item, array $rename = []): ?array
    {
        if (is_array($item)) {
            return $item;
        }
        if (! $item instanceof EntryTranslation) {
            return null;
        }

        $values = $item->values();
        foreach ($rename as $field => $key) {
            $values[$key] = $values[$field] ?? null;
        }

        return [...$values, 'name' => $item->title, 'url' => $item->url()];
    }

    /**
     * A group field's values ([] when empty).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function group(array $values, string $handle): array
    {
        return is_array($values[$handle] ?? null) ? $values[$handle] : [];
    }

    /**
     * An image for an <img>: a sized copy (the original until the library has
     * made its sizes), its srcset and dimensions.
     *
     * @return array{src: string, full: string, srcset: string, width: ?int, height: ?int, alt: string}|null
     */
    private static function image(mixed $media, string $size = 'large'): ?array
    {
        if (! $media instanceof Media || ! $media->isImage()) {
            return null;
        }

        return [
            'src' => $media->url($size),
            'full' => $media->url('large'),
            'srcset' => $media->srcset('medium', 'large'),
            'width' => $media->width,
            'height' => $media->height,
            'alt' => $media->altIn() ?? '',
        ];
    }

    /**
     * @return list<array{src: string, full: string, srcset: string, width: ?int, height: ?int, alt: string}>
     */
    private static function images(mixed $media): array
    {
        return collect(is_iterable($media) ? $media : [])
            ->map(fn (mixed $item): ?array => self::image($item))
            ->filter()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private static function lines(mixed $text): array
    {
        return collect(preg_split('/\R/', (string) $text))->map(fn (string $line): string => trim($line))->filter()->values()->all();
    }

    private static function fontFormat(Media $font): ?string
    {
        return match (strtolower(pathinfo($font->filename, PATHINFO_EXTENSION))) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            default => null,
        };
    }

    private static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    /** A quoted CSS string that can't end the declaration or the <style> element. */
    private static function cssString(string $value): string
    {
        return '"'.str_replace(['\\', '"', '<', "\n", "\r"], ['\\\\', '\\"', '\\3C ', '', ''], $value).'"';
    }

    /** An area in m², with a decimal comma only when it has decimals ("45", "45,5"). */
    private static function number(int|float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',');
    }
}
