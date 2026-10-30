<?php

namespace App\Support;

use App\Models\UnitPoint;
use Illuminate\Support\Arr;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Content as Cms;
use Sunrice\Cms\Models\EntryTranslation;

/** Data halaman proyek */
final class ProjectPage
{
    /**
     * Peta warna Global Style
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
     * Peta font Global Style
     *
     * @var array<string, string>
     */
    public const FONTS = [
        'heading' => '--display',
        'description' => '--sans',
        'button' => '--button',
    ];

    /** Urutan section */
    public const SECTIONS = [
        'masterplan' => ['id' => 'masterplan', 'label' => 'Masterplan'],
        'location' => ['id' => 'location', 'label' => 'Lokasi'],
        'cluster' => ['id' => 'cluster', 'label' => 'Cluster'],
        'facilities' => ['id' => 'facility', 'label' => 'Fasilitas'],
        'turntable' => ['id' => 'view-360', 'label' => '360°'],
        'types' => ['id' => 'type', 'label' => 'Tipe'],
        'availability' => ['id' => 'unit', 'label' => 'Unit'],
    ];

    private const FALLBACK_FONT = "'DM Sans Variable', 'Helvetica Neue', Arial, sans-serif";

    /**
     * @param  array<string, mixed>  $values
     * @return array{title: string, description: ?string, style: array{css: string, logo: ?array<string, mixed>, favicon: ?array{href: string, type: string}, marker: ?array{shape: ?string, image: ?string}}, hero: array<string, mixed>, sections: array<string, array<string, mixed>>, nav: list<array{href: string, label: string, active: bool, newTab: bool}>, whatsapp: ?array<string, mixed>}
     */
    public static function page(EntryTranslation $entry, array $values): array
    {
        $sections = self::sections($values, $entry->entry_id);

        // Jalur titik orbit
        foreach (array_keys(ProjectTracks::sections()) as $handle) {
            if (isset($sections[$handle]['scene']) && ! isset($sections[$handle]['scene']['scene']['still'])) {
                $placed = ProjectTracks::placed($sections[$handle]['points'], $handle);
                $sections[$handle]['scene']['tracks'] = ProjectTracks::for($entry->entry_id, $handle, $placed, (string) $entry->slug);
            }
        }

        return [
            'title' => (string) $entry->title,
            // Meta dari deskripsi kartu
            'description' => filled($values['card']['description'] ?? null) ? (string) $values['card']['description'] : null,
            'style' => self::style(self::group($values, 'global_style')),
            'hero' => self::hero(self::group($values, 'hero'), (string) $entry->title),
            'sections' => $sections,
            'nav' => self::nav($sections),
            'whatsapp' => self::whatsapp(self::group($values, 'whatsapp')),
        ];
    }

    /**
     * Proyek halaman tipe
     *
     * @return array{title: string, url: string, style: array<string, mixed>, nav: list<array{href: string, label: string, active: bool, newTab: bool}>, whatsapp: ?array<string, mixed>, turntable: ?array<string, mixed>, othersTitle: string, types: list<array<string, mixed>>}|null
     */
    public static function forType(EntryTranslation $type, mixed $project): ?array
    {
        if (! $project instanceof EntryTranslation) {
            return null;
        }

        $values = $project->values();
        $sections = self::sections($values, $project->entry_id);
        $url = (string) $project->url();

        return [
            'title' => (string) $project->title,
            'url' => $url,
            'style' => self::style(self::group($values, 'global_style')),
            'nav' => self::nav($sections, $url),
            'whatsapp' => self::whatsapp(self::group($values, 'whatsapp')),
            // Tab 360° proyek
            'turntable' => $sections['turntable'] ?? null,
            'othersTitle' => filled(self::group($values, 'types')['others_title'] ?? null) ? (string) self::group($values, 'types')['others_title'] : 'Tipe lain',
            'types' => collect($sections['types']['cards'] ?? [])
                ->filter(fn (array $card): bool => $card['url'] !== null && $card['url'] !== $type->url())
                ->values()
                ->all(),
        ];
    }

    /**
     * Section berisi
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array<string, mixed>>
     */
    private static function sections(array $values, int $project): array
    {
        return array_filter([
            'masterplan' => self::masterplan(self::group($values, 'masterplan'), $project),
            'location' => self::location(self::group($values, 'location')),
            'cluster' => self::cluster(self::group($values, 'cluster'), $project),
            'facilities' => self::facilities(self::group($values, 'facilities')),
            'turntable' => self::turntable(self::group($values, 'turntable'), $project),
            'types' => self::types(self::group($values, 'types')),
            'availability' => self::availability(self::group($values, 'availability'), $project),
        ]);
    }

    /**
     * Link header section
     *
     * @param  array<string, array<string, mixed>>  $sections
     * @return list<array{href: string, label: string, active: bool, newTab: bool}>
     */
    private static function nav(array $sections, string $base = ''): array
    {
        return collect(self::SECTIONS)
            ->filter(fn (array $section, string $handle): bool => isset($sections[$handle]))
            // Judul section dari CMS
            ->map(fn (array $section, string $handle): array => [
                'href' => $base.'#'.$section['id'],
                'label' => filled($sections[$handle]['title'] ?? null) ? (string) $sections[$handle]['title'] : $section['label'],
                'active' => false,
                'newTab' => false,
            ])
            ->values()
            ->all();
    }

    /**
     * Gaya global proyek
     *
     * @param  array<string, mixed>  $style
     * @return array{css: string, logo: ?array<string, mixed>, marker: ?array{shape: ?string, image: ?string}}
     */
    public static function style(array $style): array
    {
        if (($style['active'] ?? false) !== true) {
            return ['css' => '', 'logo' => null, 'favicon' => null, 'marker' => null];
        }

        $imports = [];
        $faces = [];
        $tokens = [];
        $fonts = is_array($style['fonts'] ?? null) ? $style['fonts'] : [];
        foreach (self::FONTS as $field => $token) {
            // Google Font menimpa file upload
            $google = self::googleFont($fonts["{$field}_embed"] ?? null);
            if ($google !== null) {
                $imports[$google['url']] = '@import url('.self::cssString($google['url']).');';
                $tokens[$token] = self::cssString($google['family']).', '.self::FALLBACK_FONT;

                continue;
            }

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

        $corner = is_array($style['corner'] ?? null) ? $style['corner'] : [];
        $size = is_numeric($corner['size'] ?? null) ? max(0, (int) $corner['size']) : 0;
        $chamfer = ($corner['style'] ?? 'rounded') === 'chamfer';
        // Selalu tertulis: 0 atau chamfer berarti tanpa lengkung
        $tokens['--radius'] = $chamfer ? '0px' : "{$size}px";
        if ($chamfer && $size > 0) {
            // Kartu dan gambar: potongan maks 24px
            $cut = min($size, 24).'px';
            $tokens['--frame-clip'] = "polygon({$cut} 0, calc(100% - {$cut}) 0, 100% {$cut}, 100% calc(100% - {$cut}), calc(100% - {$cut}) 100%, {$cut} 100%, 0 calc(100% - {$cut}), 0 {$cut})";
            // Logo: sudut bawah saja
            $tokens['--logo-clip'] = "polygon(0 0, 100% 0, 100% calc(100% - {$cut}), calc(100% - {$cut}) 100%, {$cut} 100%, 0 calc(100% - {$cut}))";
            $tokens['--chamfer'] = $cut;
        }

        $background = $style['background'] ?? null;
        if ($background instanceof Media && $background->isImage()) {
            $tokens['--theme-bg'] = 'url('.self::cssString($background->url('large')).')';
        }

        // @import harus paling awal
        $css = implode('', $imports).implode('', $faces);
        if ($tokens !== []) {
            $css .= ':root{'.collect($tokens)->map(fn (string $value, string $token): string => "{$token}:{$value};")->implode('').'}';
        }

        $markerImage = ($marker['type'] ?? 'shape') === 'image' ? ($marker['image'] ?? null) : null;

        return [
            'css' => $css,
            'logo' => self::image($style['logo'] ?? null, 'medium'),
            // Favicon dari logo
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
     * Hero proyek
     *
     * @param  array<string, mixed>  $hero
     * @return array{title: string, background: string, slides: list<array{type: string, src: string, alt: string}>}
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
            'background' => $slides === [] ? 'none' : 'slider',
            'slides' => $slides,
        ];
    }

    /**
     * Titik Masterplan
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, points: list<array{number: ?string, name: string, image: ?array<string, mixed>, zoom: ?string, x: ?float, y: ?float}>, scene: ?array<string, mixed>}|null
     */
    private static function masterplan(array $section, int $project): ?array
    {
        $percent = fn (mixed $value): ?float => is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : null;
        $points = collect($section['points'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null))
            ->values()
            ->map(function (array $row, int $index) use ($percent): array {
                [$x, $y] = [$percent($row['x'] ?? null), $percent($row['y'] ?? null)];

                return [
                    // Nomor dari urutan
                    'number' => sprintf('%02d', $index + 1),
                    'name' => (string) $row['name'],
                    'image' => self::image($row['image'] ?? null, 'medium'),
                    'zoom' => self::zoomImage($row['zoom_image'] ?? null),
                    'x' => $x !== null && $y !== null ? $x : null,
                    'y' => $x !== null && $y !== null ? $y : null,
                ];
            })
            ->all();

        // Orbit dengan titik
        $hotspots = self::hotspots($points, 'masterplan');
        $scene = $points === [] ? null : self::frames([...Content::sampleArea(), 'hotspots' => $hotspots], $section['image'] ?? null, $project, 'masterplan');

        return self::section($section, ['points' => $points, 'scene' => $scene], $points !== []);
    }

    /**
     * Peta lokasi
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, maps: list<array<string, mixed>>}|null
     */
    private static function location(array $section): ?array
    {
        $maps = self::images($section['maps'] ?? []);

        return self::section($section, ['maps' => $maps], $maps !== []);
    }

    /**
     * Orbit Cluster
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, points: list<array{number: ?string, name: string, description: ?string, image: ?array<string, mixed>, zoom: ?string, x: ?float, y: ?float}>, scene: ?array<string, mixed>}|null
     */
    private static function cluster(array $section, int $project): ?array
    {
        $percent = fn (mixed $value): ?float => is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : null;
        $points = collect($section['points'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null))
            ->values()
            ->map(function (array $row, int $index) use ($percent): array {
                [$x, $y] = [$percent($row['x'] ?? null), $percent($row['y'] ?? null)];

                return [
                    // Nomor dari urutan
                    'number' => sprintf('%02d', $index + 1),
                    'name' => (string) $row['name'],
                    'description' => filled($row['description'] ?? null) ? (string) $row['description'] : null,
                    'image' => self::image($row['image'] ?? null, 'medium'),
                    'zoom' => self::zoomImage($row['zoom_image'] ?? null),
                    'x' => $x !== null && $y !== null ? $x : null,
                    'y' => $x !== null && $y !== null ? $y : null,
                ];
            })
            ->all();

        $hotspots = self::hotspots($points, 'cluster');
        $scene = $points === [] ? null : self::frames([...Content::sampleCluster(), 'hotspots' => $hotspots], $section['image'] ?? null, $project, 'cluster');

        return self::section($section, ['points' => $points, 'scene' => $scene], $points !== []);
    }

    /**
     * Gambar scene: frame ZIP, gambar diam CMS, atau frame contoh
     *
     * @param  array{scene: array<string, mixed>, hotspots: array<string, array<string, mixed>>, video?: string}  $scene
     * @return array{scene: array<string, mixed>, hotspots: array<string, array<string, mixed>>, video?: ?string}
     */
    private static function frames(array $scene, mixed $media, int $project, string $section): array
    {
        $base = OrbitFrames::base($project, $section);
        if ($base !== null) {
            return ['scene' => ['base' => $base], 'hotspots' => $scene['hotspots'], 'video' => null];
        }
        $image = self::image($media);
        if ($image === null) {
            return $scene;
        }

        return [
            'scene' => ['still' => ['src' => $image['full'], 'width' => $image['width'] ?: 1600, 'height' => $image['height'] ?: 900]],
            'hotspots' => $scene['hotspots'],
            'video' => null,
        ];
    }

    /** Latar zoom titik: file asli, bukan konversi kecil */
    private static function zoomImage(mixed $media): ?string
    {
        return $media instanceof Media && $media->isImage() ? $media->url() : null;
    }

    /**
     * Titik jadi hotspot
     *
     * @param  list<array<string, mixed>>  $points
     * @return array<string, array{number: ?string, x: float, y: float, title: string, short: string, text: string, image: ?string, zoom: ?string}>
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
                'zoom' => $point['zoom'] ?? null,
            ]])
            ->all();
    }

    /**
     * Tab 360°
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, tabs: list<array{name: string, text: string, frames: list<string>, width: int, height: int, frame: int, config: string, root: string}>}|null
     */
    private static function turntable(array $section, int $project): ?array
    {
        $tabs = collect(self::turntableTabs($section))
            ->map(fn (array $tab): array => Arr::except($tab, 'source'))
            ->all();

        // Tanpa tab tidak tampil
        return $tabs === [] ? null : self::section($section, ['tabs' => $tabs], true);
    }

    /**
     * Tab 360° dengan gambarnya
     *
     * @param  array<string, mixed>  $section
     * @return list<array{name: string, text: string, frames: list<string>, width: int, height: int, frame: int, source: int}>
     */
    public static function turntableTabs(array $section): array
    {
        $rows = collect($section['tabs'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null))
            ->values()
            ->map(fn (array $row): array => [
                'row' => $row,
                // Urut nama file
                'frames' => collect(is_iterable($row['images'] ?? null) ? $row['images'] : [])
                    ->filter(fn (mixed $media): bool => $media instanceof Media && $media->isImage())
                    ->sortBy(fn (Media $media): string => (string) $media->filename, SORT_NATURAL | SORT_FLAG_CASE)
                    ->values(),
            ]);

        // Tab kosong pakai gambar tab pertama
        $first = $rows->search(fn (array $item): bool => $item['frames']->isNotEmpty());
        if ($first === false) {
            return [];
        }

        // Tab dengan gambar sama dianggap satu sumber
        $keys = $rows->map(fn (array $item): string => $item['frames']->map(fn (Media $media): int => $media->id)->implode(','));
        $sources = $keys->map(fn (string $key): int => $key === '' ? $first : (int) $keys->search($key));

        return $rows
            ->map(function (array $item, int $index) use ($rows, $sources): array {
                $source = $sources[$index];
                $frames = $rows[$source]['frames'];
                // Sudut dibagi rata antar tab segambar
                $sharing = $sources->filter(fn (int $other): bool => $other === $source)->keys()->values();
                $frame = intdiv($sharing->search($index) * $frames->count(), $sharing->count());

                return [
                    'name' => (string) $item['row']['name'],
                    'text' => (string) ($item['row']['description'] ?? ''),
                    'frames' => $frames->map(fn (Media $media): string => $media->url())->all(),
                    'width' => (int) ($frames->first()->width ?: 1600),
                    'height' => (int) ($frames->first()->height ?: 1200),
                    'frame' => $frame,
                    'source' => $source,
                ];
            })
            ->all();
    }

    /**
     * Karusel fasilitas
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, items: list<array{title: string, image: string}>}|null
     */
    private static function facilities(array $section): ?array
    {
        $items = collect($section['items'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null) && ($row['image'] ?? null) instanceof Media)
            ->map(fn (array $row): array => [
                'title' => (string) $row['name'],
                'image' => $row['image']->url('large'),
            ])
            ->values()
            ->all();

        return self::section($section, ['items' => $items], $items !== []);
    }

    /**
     * Tipe unit
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>|null
     */
    private static function types(array $section): ?array
    {
        $units = collect($section['units'] ?? [])
            ->map(fn (mixed $item): ?array => self::row($item, ['label' => 'category']))
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null))
            ->values()
            ->map(fn (array $row, int $index): array => self::unit($row, $index));

        $heroImage = self::image($section['hero_image'] ?? null);

        return self::section($section, [
            'heroImage' => $heroImage,
            'units' => $units->all(),
            // Kartu tipe
            'cards' => $units
                ->map(fn (array $unit): array => [
                    'name' => $unit['name'],
                    'label' => $unit['category'],
                    'url' => $unit['url'],
                    'sheet' => $unit['url'] === null ? $unit['id'] : null,
                    'image' => $unit['image']['src'] ?? null,
                    'size' => collect($unit['facts'])->firstWhere(0, 'Ukuran')[1] ?? null,
                    'specs' => collect($unit['facts'])->reject(fn (array $fact): bool => $fact[0] === 'Ukuran')->values()->all(),
                ])
                ->values()
                ->all(),
        ], $heroImage !== null || $units->isNotEmpty());
    }

    /**
     * Satu tipe unit
     *
     * @param  array<string, mixed>  $row
     * @return array{id: string, name: string, category: ?string, facts: list<array{0: string, 1: string}>, image: ?array<string, mixed>, plans: list<array<string, mixed>>, gallery: list<array<string, mixed>>, vr: ?string, url: ?string}
     */
    private static function unit(array $row, int $index): array
    {
        $facts = array_values(array_filter([
            filled($row['dimensions'] ?? null) ? ['Ukuran', (string) $row['dimensions']] : null,
            is_numeric($row['lt'] ?? null) && $row['lt'] > 0 ? ['LT', self::number($row['lt']).' m²'] : null,
            is_numeric($row['lb'] ?? null) && $row['lb'] > 0 ? ['LB', self::number($row['lb']).' m²'] : null,
            is_numeric($row['floors'] ?? null) && $row['floors'] > 0 ? ['Lantai', (string) (int) $row['floors']] : null,
        ]));

        return [
            'id' => 'unit-'.($index + 1),
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
            // Alamat tur
            'vr' => preg_match('#^(https?://|/)#', (string) ($row['vr_url'] ?? '')) === 1 ? url((string) $row['vr_url']) : null,
            'url' => $row['url'] ?? null,
        ];
    }

    /**
     * Siteplan dan titik unit
     *
     * @param  array<string, mixed>  $section
     * @return array{title: string, intro: ?string, siteplan: ?array<string, mixed>, units: list<array{id: string, x: float, y: float, status: string}>, counts: array<string, int>}|null
     */
    private static function availability(array $section, int $project): ?array
    {
        $siteplan = self::image($section['siteplan'] ?? null);

        // Titik status unit
        $points = UnitPoint::query()->where('project_id', $project)->get()->keyBy('unit_id');
        $units = Cms::entries('units')->whereEntry('project', $project)->alphabetical()->get()
            ->filter(fn (EntryTranslation $unit): bool => $points->has($unit->entry_id))
            ->map(fn (EntryTranslation $unit): array => [
                'id' => (string) $unit->title,
                'x' => $points[$unit->entry_id]->x,
                'y' => $points[$unit->entry_id]->y,
                'status' => in_array($unit->values()['sale_status'] ?? null, Content::STATUSES, true) ? $unit->values()['sale_status'] : 'available',
            ])
            ->values();
        $counts = collect(Content::STATUSES)->mapWithKeys(fn (string $status): array => [$status => $units->where('status', $status)->count()])->all();

        return self::section($section, ['siteplan' => $siteplan, 'units' => $units->all(), 'counts' => $counts], $siteplan !== null);
    }

    /**
     * Tombol WhatsApp
     *
     * @param  array<string, mixed>  $whatsapp
     * @return array{label: string, heading: ?string, description: ?string, contacts: list<array{title: string, avatar: ?string, href: string}>}|null
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
            'contacts' => $contacts,
        ];
    }

    /**
     * Judul dan isi
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
     * Baris section
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
     * Nilai group
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function group(array $values, string $handle): array
    {
        return is_array($values[$handle] ?? null) ? $values[$handle] : [];
    }

    /**
     * Gambar responsif
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

    /** String CSS aman */
    /**
     * Font pertama dari kode embed Google Font; selain fonts.googleapis.com diabaikan
     *
     * @return array{url: string, family: string}|null
     */
    private static function googleFont(mixed $embed): ?array
    {
        if (! is_string($embed) || ! preg_match('~https://fonts\.googleapis\.com/css2?\?[^\s\'"()<>]+~', $embed, $match)) {
            return null;
        }

        $url = html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5);
        if (! preg_match('/(?:^|&)family=([^&:]+)/', (string) parse_url($url, PHP_URL_QUERY), $family)) {
            return null;
        }

        $name = trim(str_replace('+', ' ', urldecode($family[1])));

        return preg_match('/^[A-Za-z0-9 ]+$/', $name) === 1 ? ['url' => $url, 'family' => $name] : null;
    }

    private static function cssString(string $value): string
    {
        return '"'.str_replace(['\\', '"', '<', "\n", "\r"], ['\\\\', '\\"', '\\3C ', '', ''], $value).'"';
    }

    /** Format luas */
    private static function number(int|float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',');
    }
}
