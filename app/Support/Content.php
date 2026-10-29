<?php

namespace App\Support;

use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Cms\Content as Cms;
use Sunrice\Cms\Models\EntryTranslation;

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
                'logo' => [...config('site.logo'), 'alt' => Cms::site()->name()],
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
     * @return array{logo: ?array{src: string, width: ?int, height: ?int}, description: ?string, address: ?string, hours: ?string, phone: ?array{label: string, href: string}, email: ?array{label: string, href: string}, whatsapp: ?array{label: string, href: string}, copyright: string}
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
                'logo' => $logo instanceof Media && $logo->isImage() ? ['src' => $logo->url(), 'width' => $logo->width, 'height' => $logo->height] : null,
                'description' => $filled('description'),
                'address' => $filled('address'),
                'hours' => $filled('hours'),
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
        $scenes = json_decode((string) file_get_contents(resource_path('data/area.json')), true);
        $hero = $values['hero'];
        $cmsHero = $entryValues['hero'] ?? [];
        $cmsSlides = collect($cmsHero['media'] ?? [])
            ->filter(fn (mixed $media): bool => $media instanceof Media)
            ->map(fn (Media $media): array => [
                'type' => $media->isImage() ? 'image' : 'video',
                'src' => $media->url(),
                'alt' => $media->altIn() ?? '',
            ])
            ->values()
            ->all();
        $slides = $cmsSlides !== [] ? $cmsSlides : $hero['slides'];
        // URL tipe CMS
        $typeUrls = Cms::entries('types')->get()->mapWithKeys(fn (EntryTranslation $type): array => [$type->slug => $type->url()]);
        $heading = fn (string $handle, array $fallback): array => self::heading($entryValues[$handle] ?? [], $fallback);
        $masterplan = $heading('masterplan', ['title' => $values['masterplan']['title'], 'intro' => $values['masterplan']['text']]);
        $areaMap = $heading('lokasi', $values['lokasi']);

        return [
            'hero' => [
                ...$hero,
                'title' => filled($cmsHero['heading'] ?? null) ? $cmsHero['heading'] : $hero['title'],
                'subtitle' => $cmsHero === [] ? $hero['subtitle'] : ($cmsHero['description'] ?? ''),
                // Cadangan video teaser
                'background' => $cmsSlides !== [] || ($hero['background'] === 'slider' && $slides !== []) ? 'slider' : 'video',
                'slides' => $slides,
            ],
            'masterplan' => [
                'title' => $masterplan['title'],
                'text' => $masterplan['intro'],
                // Orbit Kawasan
                'video' => $hero['teaser'],
            ],
            'area' => self::scene($values['kawasan'], $scenes['area']),
            'areaMap' => [
                'title' => $areaMap['title'],
                'intro' => $areaMap['intro'],
                'image' => $values['lokasi']['map'],
                'alt' => $values['lokasi']['alt'],
                'width' => $values['lokasi']['width'],
                'height' => $values['lokasi']['height'],
                'placeholder' => $values['lokasi']['placeholder'],
            ],
            'cluster' => self::scene([...$values['cluster'], ...$heading('cluster', $values['cluster'])], $scenes['cluster']),
            'headings' => [
                'facilities' => $heading('fasilitas', $values['headings']['fasilitas']),
                'types' => $heading('tipe', $values['headings']['tipe']),
            ],
            'facilities' => $values['fasilitas'],
            'types' => collect($values['tipe'])
                ->map(fn (array $type): array => [
                    'slug' => $type[0],
                    'name' => $type[1],
                    'label' => $type[2],
                    'url' => $typeUrls[$type[0]] ?? url("/tipe/{$type[0]}"),
                    'image' => $type[3],
                    'lt' => self::number($type[4]),
                    'lb' => self::number($type[5]),
                    'bedrooms' => $type[6],
                    'bathrooms' => $type[7],
                    'placeholder' => true,
                ])
                ->all(),
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
        $floors = max(1, (int) ($values['floors'] ?? 1));
        $card = self::card($entry, $values);
        $denah = collect($values['floor_plans'] ?? [])
            ->filter(fn (array $plan): bool => $plan['image'] instanceof Media)
            ->map(fn (array $plan): array => ['label' => $plan['label'], 'image' => self::url($plan['image'])])
            ->values();

        return [
            ...$card,
            'dimensions' => filled($values['dimensions'] ?? null) ? (string) $values['dimensions'] : null,
            'intro' => $values['intro'] ?? '',
            // Ringkasan atas
            'facts' => [
                ['LT', "{$card['lt']} m²"],
                ['LB', "{$card['lb']} m²"],
                ['Lantai', (string) $floors],
                ...filled($values['bedrooms'] ?? null) ? [['KT', (string) $card['bedrooms']]] : [],
                ...filled($values['bathrooms'] ?? null) ? [['KM', (string) $card['bathrooms']]] : [],
            ],
            'info' => [
                ['Luas tanah', "{$card['lt']} m²"],
                ['Luas bangunan', "{$card['lb']} m²"],
                ['Jumlah lantai', (string) $floors],
                // Ruko tanpa kamar
                ...filled($values['bedrooms'] ?? null) ? [['Kamar tidur', (string) $card['bedrooms']]] : [],
                ...filled($values['bathrooms'] ?? null) ? [['Kamar mandi', (string) $card['bathrooms']]] : [],
                ...collect($values['info_rows'] ?? [])->map(fn (array $row): array => [$row['label'], $row['value']])->all(),
            ],
            'denah' => $denah->all(),
            'gallery' => collect($values['gallery'] ?? [])->map(fn (Media $image): array => ['src' => $image->url(), 'thumb' => $image->url('medium')])->values()->all(),
            'vr' => ['url' => $values['vr_url'] ?? ''],
            'project' => ProjectPage::forType($entry, $values['project'] ?? null),
        ];
    }

    /**
     * Kartu tipe beranda
     *
     * @param  array<string, mixed>  $values
     * @return array{slug: string, name: string, label: ?string, url: ?string, image: ?string, lt: string, lb: string, bedrooms: int, bathrooms: int, placeholder: bool}
     */
    private static function card(EntryTranslation $type, array $values): array
    {
        return [
            'slug' => $type->slug,
            'name' => $type->title,
            'label' => $values['label'] ?? null,
            'url' => $type->url(),
            'image' => self::url($values['image'] ?? null),
            'lt' => self::number($values['lt'] ?? 0),
            'lb' => self::number($values['lb'] ?? 0),
            'bedrooms' => (int) ($values['bedrooms'] ?? 0),
            'bathrooms' => (int) ($values['bathrooms'] ?? 0),
            'placeholder' => (bool) ($values['placeholder'] ?? false),
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
