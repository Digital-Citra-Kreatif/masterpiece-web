<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Cms\Content as Cms;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Support\HtmlSanitizer;

/** Konten CMS situs */
final class Content
{
    /** Urutan status unit */
    public const STATUSES = ['available', 'reserved', 'sold'];

    /**
     * Data situs bersama
     *
     * @return array{lang: string, fullName: string, description: string, favicon: ?array{href: string, type: string}, protectMedia: bool, showPlaceholderBadges: bool, logo: array{light: string, dark: string, alt: string, width: int, height: int}, nav: list<array{href: string, label: string, active: bool, newTab: bool}>, statusLabels: array<string, string>}
     */
    public static function site(): array
    {
        return once(function (): array {
            $settings = Cms::global('situs');
            $labels = $settings['status_labels'] ?? [];

            return [
                'lang' => str_replace('_', '-', app()->getLocale()),
                'fullName' => Cms::site()->name(),
                'description' => Cms::site()->descriptionIn(),
                'favicon' => self::favicon(),
                'protectMedia' => (bool) ($settings['protect_media'] ?? false),
                'showPlaceholderBadges' => (bool) config('site.placeholder_badges'),
                'logo' => [...self::headerLogo($settings), 'alt' => Cms::site()->name()],
                'nav' => self::nav(),
                'statusLabels' => [
                    'sold' => $labels['sold'] ?? 'Terjual',
                    'reserved' => $labels['reserved'] ?? 'Dipesan',
                    'available' => $labels['available'] ?? 'Tersedia',
                ],
            ];
        });
    }

    /**
     * Ikon browser
     *
     * @return array{href: string, type: string}|null
     */
    private static function favicon(): ?array
    {
        $id = app(BrandingSettings::class)->favicon;
        $media = $id === null ? null : Media::query()->find($id);
        if (! $media instanceof Media || ! $media->isImage()) {
            return null;
        }

        return ['href' => $media->url(), 'type' => $media->mime_type];
    }

    /**
     * Isi halaman 404
     *
     * @return array{title: string, description: ?string, link: array{href: string, label: string, newTab: bool}}
     */
    public static function notFound(): array
    {
        $values = Cms::global('not_found');
        $link = is_array($values['link'] ?? null) ? $values['link'] : [];

        return [
            'title' => filled($values['heading'] ?? null) ? (string) $values['heading'] : 'Halaman tidak ditemukan',
            'description' => filled($values['description'] ?? null) ? (string) $values['description'] : null,
            'link' => [
                // Tanpa tujuan: beranda
                'href' => filled($link['url'] ?? null) ? (string) $link['url'] : url('/'),
                'label' => filled($link['label'] ?? null) ? (string) $link['label'] : 'Kembali ke beranda',
                'newTab' => ($link['new_tab'] ?? false) === true,
            ],
        ];
    }

    /**
     * Isi footer
     *
     * @return array{logo: ?array{src: string, width: ?int, height: ?int}, description: ?string, address: ?string, hours: ?string, contactTitle: string, hoursTitle: string, phone: ?array{label: string, href: string}, email: ?array{label: string, href: string}, whatsapp: ?array{label: string, href: string}, copyright: string}
     */
    public static function footer(): array
    {
        return once(function (): array {
            $values = Cms::global('footer');
            $brand = app(BrandingSettings::class)->logo;
            $brand = $brand === null ? null : Media::query()->find($brand);
            $logo = $brand instanceof Media && $brand->isImage() ? $brand : ($values['logo'] ?? null);
            $filled = fn (string $key): ?string => filled($values[$key] ?? null) ? trim((string) $values[$key]) : null;
            $phone = $filled('phone');
            $email = $filled('email');
            $whatsapp = $filled('whatsapp');

            return [
                'logo' => $logo instanceof Media && $logo->isImage() ? ['src' => $logo->url('medium'), 'width' => $logo->width, 'height' => $logo->height] : null,
                'description' => $filled('description'),
                'address' => $filled('address'),
                'hours' => $filled('hours'),
                'contactTitle' => $filled('contact_title') ?? 'Kontak',
                'hoursTitle' => $filled('hours_title') ?? 'Jam operasional',
                'phone' => $phone ? ['label' => $phone, 'href' => 'tel:'.preg_replace('/[^0-9+]/', '', $phone)] : null,
                'email' => $email ? ['label' => $email, 'href' => 'mailto:'.$email] : null,
                'whatsapp' => $whatsapp ? ['label' => '+'.$whatsapp, 'href' => 'https://wa.me/'.$whatsapp] : null,
                // Tahun otomatis, nama dari Settings → Site
                'copyright' => '© '.date('Y').' '.Cms::site()->name(),
            ];
        });
    }

    /**
     * Link header dari CMS → Menus
     *
     * @return list<array{href: string, label: string, active: bool, newTab: bool}>
     */
    private static function nav(): array
    {
        return collect(Cms::menu((string) config('site.menu')))
            ->map(fn (array $item): array => ['href' => $item['url'], 'label' => $item['label'], 'active' => $item['active'], 'newTab' => $item['new_tab']])
            ->values()
            ->all();
    }

    /**
     * Section beranda
     *
     * @param  array<string, mixed>  $entryValues
     * @return array<string, mixed>
     */
    public static function home(array $entryValues = []): array
    {
        $values = require resource_path('data/home.php');
        $hero = $values['hero'];
        $cmsHero = $entryValues['hero'] ?? [];
        $video = ($cmsHero['video'] ?? null) instanceof Media ? $cmsHero['video'] : null;
        $image = ($cmsHero['image'] ?? null) instanceof Media ? $cmsHero['image'] : null;
        $heading = fn (string $handle, array $fallback): array => self::heading($entryValues[$handle] ?? [], $fallback);

        $cards = Cms::entries('projects')->ordered()->get()->map(self::projectCard(...))->values()->all();
        $projectsId = self::cssId($entryValues['projects']['css_id'] ?? null) ?? 'projects';

        return [
            'hero' => [
                ...$hero,
                'id' => self::cssId($cmsHero['css_id'] ?? null),
                // Panah ke grid proyek
                'scrollTo' => $cards !== [] ? "#{$projectsId}" : null,
                'title' => filled($cmsHero['heading'] ?? null) ? $cmsHero['heading'] : $hero['title'],
                'subtitle' => $cmsHero === [] ? $hero['subtitle'] : ($cmsHero['description'] ?? ''),
                // Video di atas gambar
                ...match (true) {
                    $video !== null => ['background' => 'video', 'teaser' => $video->url(), 'cover' => $image?->url()],
                    $image !== null => ['background' => 'slider', 'slides' => [['type' => 'image', 'src' => $image->url(), 'alt' => $image->altIn() ?? '']]],
                    default => [],
                },
            ],
            // Semua proyek terbit
            'projects' => [
                'id' => $projectsId,
                'heading' => $heading('projects', ['title' => 'Proyek', 'intro' => null]),
                'cards' => $cards,
            ],
        ];
    }

    /**
     * Contoh scene Kawasan
     *
     * @return array{scene: array<string, mixed>, hotspots: array<string, array<string, mixed>>, video: string}
     */
    public static function sampleArea(): array
    {
        $values = require resource_path('data/home.php');
        $scenes = json_decode((string) file_get_contents(resource_path('data/area.json')), true);
        $area = self::scene($values['kawasan'], $scenes['area']);

        return ['scene' => [...$area['scene'], 'placeholder' => true], 'hotspots' => $area['hotspots'], 'video' => $values['hero']['teaser']];
    }

    /**
     * Contoh scene Cluster
     *
     * @return array{scene: array<string, mixed>, hotspots: array<string, array<string, mixed>>}
     */
    public static function sampleCluster(): array
    {
        $values = require resource_path('data/home.php');
        $scenes = json_decode((string) file_get_contents(resource_path('data/area.json')), true);
        $cluster = self::scene($values['cluster'], $scenes['cluster']);

        return ['scene' => [...$cluster['scene'], 'placeholder' => true], 'hotspots' => $cluster['hotspots']];
    }

    /**
     * Judul dan intro
     *
     * @param  array<string, mixed>  $group
     * @param  array{title: string, intro: ?string}  $fallback
     * @return array{title: string, intro: ?string}
     */
    private static function heading(array $group, array $fallback): array
    {
        return filled($group['title'] ?? null)
            ? ['title' => $group['title'], 'intro' => $group['description'] ?? null]
            : ['title' => $fallback['title'], 'intro' => $fallback['intro'] ?? null];
    }

    /**
     * Data halaman tipe
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function type(EntryTranslation $entry, array $values): array
    {
        // Lantai boleh kosong
        $floors = is_numeric($values['floors'] ?? null) && $values['floors'] > 0 ? (string) (int) $values['floors'] : null;
        $card = self::card($entry, $values);
        $project = ProjectPage::forType($entry, $values['project'] ?? null);
        // 360° tipe, kosong: 360° proyek
        $turntable = collect(ProjectPage::turntableTabs(is_array($values['turntable'] ?? null) ? $values['turntable'] : []))
            ->map(fn (array $tab): array => Arr::except($tab, 'source'))
            ->all();
        $denah = collect($values['floor_plans'] ?? [])
            ->filter(fn (array $plan): bool => $plan['image'] instanceof Media)
            ->map(fn (array $plan): array => ['label' => $plan['label'], 'image' => self::url($plan['image'])])
            ->values();

        return [
            ...$card,
            'dimensions' => filled($values['dimensions'] ?? null) ? (string) $values['dimensions'] : null,
            'intro' => self::richText($values['intro'] ?? null),
            // Ringkasan atas
            'facts' => [
                // LT dan LB boleh kosong
                ...$card['lt'] !== null ? [['LT', "{$card['lt']} m²"]] : [],
                ...$card['lb'] !== null ? [['LB', "{$card['lb']} m²"]] : [],
                ...$floors !== null ? [['Lantai', $floors]] : [],
                ...filled($values['bedrooms'] ?? null) ? [['KT', (string) $card['bedrooms']]] : [],
                ...filled($values['bathrooms'] ?? null) ? [['KM', (string) $card['bathrooms']]] : [],
                ...collect($values['summary_extra'] ?? [])->map(fn (array $row): array => [$row['label'], $row['value']])->all(),
            ],
            'info' => [
                ...$card['lt'] !== null ? [['Luas tanah', "{$card['lt']} m²"]] : [],
                ...$card['lb'] !== null ? [['Luas bangunan', "{$card['lb']} m²"]] : [],
                ...$floors !== null ? [['Jumlah lantai', $floors]] : [],
                // Ruko tanpa kamar
                ...filled($values['bedrooms'] ?? null) ? [['Kamar tidur', (string) $card['bedrooms']]] : [],
                ...filled($values['bathrooms'] ?? null) ? [['Kamar mandi', (string) $card['bathrooms']]] : [],
                ...collect($values['info_rows'] ?? [])->map(fn (array $row): array => [$row['label'], $row['value']])->all(),
            ],
            // Usaha sesuai view
            'suitable' => [
                'title' => filled($values['suitable_title'] ?? null) ? (string) $values['suitable_title'] : 'Cocok untuk',
                'items' => collect($values['suitable_for'] ?? [])->pluck('name')->filter()->values()->all(),
            ],
            'denah' => $denah->all(),
            'buildingSpecs' => collect($values['building_specs'] ?? [])->map(fn (array $row): array => [$row['label'], $row['value']])->values()->all(),
            'specsTitle' => filled($values['building_specs_title'] ?? null) ? (string) $values['building_specs_title'] : 'Spesifikasi bangunan',
            'specsNote' => filled($values['building_specs_note'] ?? null) ? (string) $values['building_specs_note'] : null,
            'gallery' => collect($values['gallery'] ?? [])->map(fn (Media $image): array => ['src' => $image->url('large'), 'thumb' => $image->url('medium'), ...self::largeSize($image)])->values()->all(),
            'vr' => ['url' => $values['vr_url'] ?? ''],
            'turntable' => $turntable !== [] ? $turntable : ($project['turntable']['tabs'] ?? []),
            'project' => $project,
        ];
    }

    /**
     * Kartu proyek beranda
     *
     * @return array{name: string, url: string, image: ?string, logo: ?string, description: ?string}
     */
    private static function projectCard(EntryTranslation $project): array
    {
        $values = $project->values();
        $image = $values['card']['image'] ?? null;
        $logo = $values['global_style']['logo'] ?? null;

        return [
            'name' => (string) $project->title,
            'url' => (string) $project->url(),
            'image' => $image instanceof Media ? $image->url('large') : null,
            'logo' => $logo instanceof Media && $logo->isImage() ? $logo->url('medium') : null,
            'description' => filled($values['card']['description'] ?? null) ? (string) $values['card']['description'] : null,
        ];
    }

    /**
     * Kartu tipe beranda
     *
     * @param  array<string, mixed>  $values
     * @return array{slug: string, name: string, label: ?string, url: ?string, image: ?string, lt: ?string, lb: ?string, bedrooms: int, bathrooms: int}
     */
    private static function card(EntryTranslation $type, array $values): array
    {
        return [
            'slug' => $type->slug,
            'name' => $type->title,
            'label' => $values['label'] ?? null,
            'url' => $type->url(),
            'image' => self::url($values['image'] ?? null),
            'lt' => is_numeric($values['lt'] ?? null) && $values['lt'] > 0 ? self::number($values['lt']) : null,
            'lb' => is_numeric($values['lb'] ?? null) && $values['lb'] > 0 ? self::number($values['lb']) : null,
            'bedrooms' => (int) ($values['bedrooms'] ?? 0),
            'bathrooms' => (int) ($values['bathrooms'] ?? 0),
        ];
    }

    /**
     * Scene scroll
     *
     * @param  array<string, mixed>  $section
     * @param  array{scene: array<string, mixed>, hotspots: array<string, array{track: string, frame?: int}>}  $package
     * @return array{title: string, intro: string, scene: array<string, mixed>, hotspots: array<string, array<string, mixed>>}
     */
    private static function scene(array $section, array $package): array
    {
        return [
            'title' => $section['title'] ?? '',
            'intro' => $section['intro'] ?? '',
            'scene' => $package['scene'],
            'hotspots' => collect($section['hotspots'] ?? [])
                ->filter(fn (array $spot): bool => isset($package['hotspots'][$spot['key']]))
                ->mapWithKeys(fn (array $spot): array => [$spot['key'] => [
                    ...$package['hotspots'][$spot['key']],
                    'title' => $spot['title'],
                    'short' => $spot['short'] ?? null,
                    'text' => $spot['text'] ?? '',
                ]])
                ->all(),
        ];
    }

    /**
     * Logo header dari Globals Situs, cadangan config
     *
     * @param  array<string, mixed>  $settings
     * @return array{light: string, dark: string, width: int, height: int}
     */
    private static function headerLogo(array $settings): array
    {
        $logo = config('site.logo');
        $size = null;
        foreach (['light' => 'logo_light', 'dark' => 'logo_dark'] as $tone => $field) {
            $media = $settings[$field] ?? null;
            if ($media instanceof Media && $media->isImage()) {
                $logo[$tone] = $media->url('medium');
                $size ??= [$media->width, $media->height];
            }
        }
        if ($size !== null && $size[0] && $size[1]) {
            [$logo['width'], $logo['height']] = [(int) $size[0], (int) $size[1]];
        }

        return $logo;
    }

    /**
     * Ukuran varian large (lebar maks 1600)
     *
     * @return array{w: ?int, h: ?int}
     */
    private static function largeSize(Media $image): array
    {
        $w = (int) $image->width;
        $h = (int) $image->height;
        if ($w === 0 || $h === 0) {
            return ['w' => null, 'h' => null];
        }

        return $w > 1600 ? ['w' => 1600, 'h' => (int) round($h * 1600 / $w)] : ['w' => $w, 'h' => $h];
    }

    /** CSS ID dari CMS, tanpa # */
    private static function cssId(?string $value): ?string
    {
        $id = Str::slug(ltrim(trim((string) $value), '#'));

        return $id === '' ? null : $id;
    }

    /** HTML rich text, teks lama jadi paragraf */
    private static function richText(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return HtmlSanitizer::sanitize(str_contains($value, '<') ? $value : '<p>'.nl2br(e($value), false).'</p>');
    }

    private static function url(?Media $media): ?string
    {
        return $media?->url();
    }

    /** Format luas */
    private static function number(int|float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',');
    }
}
