<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Content as Cms;
use Sunrice\Cms\Models\EntryTranslation;

/**
 * Turns CMS content into the arrays the site's Blade views read.
 *
 * Editors change words, pictures, numbers and statuses in the CMS. Motion packages
 * (scroll-scene frames and the WebRotate sequence) are delivered by the 3D team, so
 * their data stays in resources/data: area.json places each hotspot on its landmark,
 * sequence.php describes the type pages' 360° sequence.
 */
final class Content
{
    /** Unit statuses, in the order the legend lists them. */
    public const STATUSES = ['available', 'reserved', 'sold'];

    /**
     * Site-wide data every page shares as `$site` (Globals → Situs and Settings → Site).
     *
     * @return array{lang: string, fullName: string, description: string, protectMedia: bool, showPlaceholderBadges: bool, logo: array{light: string, dark: string, alt: string, width: int, height: int}, nav: list<array{href: string, label: string}>, statusLabels: array<string, string>, siteplan: array{image: ?string, width: int, height: int, placeholder: bool}}
     */
    public static function site(): array
    {
        return once(function (): array {
            $settings = Cms::global('situs');
            $labels = $settings['status_labels'] ?? [];
            $siteplan = $settings['siteplan'] ?? null;

            return [
                'lang' => str_replace('_', '-', app()->getLocale()),
                'fullName' => Cms::site()->name(),
                'description' => Cms::site()->descriptionIn(),
                'protectMedia' => (bool) ($settings['protect_media'] ?? false),
                'showPlaceholderBadges' => (bool) config('site.placeholder_badges'),
                'logo' => [...config('site.logo'), 'alt' => Cms::site()->name()],
                'nav' => collect($settings['nav'] ?? [])
                    ->map(fn (array $item): array => ['href' => '#'.$item['section'], 'label' => $item['label']])
                    ->values()
                    ->all(),
                'statusLabels' => [
                    'sold' => $labels['sold'] ?? 'Terjual',
                    'reserved' => $labels['reserved'] ?? 'Dipesan',
                    'available' => $labels['available'] ?? 'Tersedia',
                ],
                'siteplan' => [
                    'image' => self::url($siteplan),
                    'width' => $siteplan?->width ?: 1600,
                    'height' => $siteplan?->height ?: 1000,
                    'placeholder' => (bool) ($settings['siteplan_placeholder'] ?? false),
                ],
            ];
        });
    }

    /**
     * The homepage's sections: the hero banner and each section's title and
     * description from the Beranda entry's fields, the rest from
     * resources/data/home.php (hardcoded for now) and the Unit collection.
     *
     * Hero media: one image is a still background, one video a loop, more take turns
     * as a slider. Without media the hero keeps the hardcoded background.
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
        $units = self::units();
        $heading = fn (string $handle, array $fallback): array => self::heading($entryValues[$handle] ?? [], $fallback);
        $masterplan = $heading('masterplan', ['title' => $values['masterplan']['title'], 'intro' => $values['masterplan']['text']]);
        $areaMap = $heading('lokasi', $values['lokasi']);

        return [
            'hero' => [
                ...$hero,
                'title' => filled($cmsHero['heading'] ?? null) ? $cmsHero['heading'] : $hero['title'],
                'subtitle' => $cmsHero === [] ? $hero['subtitle'] : ($cmsHero['description'] ?? ''),
                // A slider without slides falls back to the teaser loop.
                'background' => $cmsSlides !== [] || ($hero['background'] === 'slider' && $slides !== []) ? 'slider' : 'video',
                'slides' => $slides,
            ],
            'masterplan' => [
                'title' => $masterplan['title'],
                'text' => $masterplan['intro'],
                // The hero's loop is the same orbit as the Kawasan frames: it plays once the frame is full size.
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
                'units' => $heading('unit_update', $values['headings']['unit_update']),
            ],
            'facilities' => $values['fasilitas'],
            'types' => collect($values['tipe'])
                ->map(fn (array $type): array => [
                    'slug' => $type[0],
                    'name' => $type[1],
                    'label' => $type[2],
                    'url' => url("/tipe/{$type[0]}"),
                    'image' => $type[3],
                    'lt' => self::number($type[4]),
                    'lb' => self::number($type[5]),
                    'bedrooms' => $type[6],
                    'bathrooms' => $type[7],
                    'placeholder' => true,
                ])
                ->all(),
            'units' => $units->all(),
            'counts' => collect(self::STATUSES)
                ->mapWithKeys(fn (string $status): array => [$status => $units->where('status', $status)->count()])
                ->all(),
        ];
    }

    /**
     * A section's title and intro: the CMS group's (title and description) once its
     * title is filled in, otherwise the hardcoded ones.
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
     * One type page: its card data plus the 360° sequence, its parts, denah, gallery and VR.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function type(EntryTranslation $entry, array $values): array
    {
        $shared = Cms::global('tipe_bersama');
        $sequence = require resource_path('data/sequence.php');
        $floors = max(1, (int) ($values['floors'] ?? 1));
        $floorTexts = collect($shared['floor_texts'] ?? [])->pluck('text')->filter()->values();
        $card = self::card($entry, $values);

        $parts = [[
            'id' => 'fasad',
            'name' => $shared['facade_name'] ?? 'Fasad',
            'kind' => 'Tampak luar',
            'frame' => 0,
            'text' => $shared['facade_text'] ?? '',
        ]];
        foreach (range(1, $floors) as $floor) {
            $parts[] = [
                'id' => "lantai-{$floor}",
                'name' => "Lantai {$floor}",
                'kind' => 'Aksonometri',
                // Until the client's sequence has a keyframe per floor, floors sit at evenly spaced angles.
                'frame' => (int) round($floor * $sequence['frames'] / ($floors + 1)),
                'text' => $floorTexts->get($floor - 1) ?? $floorTexts->last() ?? '',
                'denah' => "Lantai {$floor}",
            ];
        }

        return [
            ...$card,
            'intro' => $values['intro'] ?? '',
            'info' => [
                ['Luas tanah', "{$card['lt']} m²"],
                ['Luas bangunan', "{$card['lb']} m²"],
                ['Jumlah lantai', (string) $floors],
                ['Kamar tidur', (string) $card['bedrooms']],
                ['Kamar mandi', (string) $card['bathrooms']],
                ...collect($values['info_rows'] ?? [])->map(fn (array $row): array => [$row['label'], $row['value']])->all(),
            ],
            'sequence' => [...$sequence, 'placeholder' => ! ($values['real_facade'] ?? false)],
            'parts' => $parts,
            'denah' => collect($values['floor_plans'] ?? [])
                ->filter(fn (array $plan): bool => $plan['image'] instanceof Media)
                ->map(fn (array $plan): array => ['label' => $plan['label'], 'image' => self::url($plan['image'])])
                ->values()
                ->all(),
            'gallery' => collect($values['gallery'] ?? [])->map(fn (Media $image): string => $image->url())->values()->all(),
            'vr' => ['url' => $values['vr_url'] ?? ''],
        ];
    }

    /**
     * What a type card on the homepage shows.
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
     * A scroll scene: the section's copy, its frames package and the hotspots that have a landmark.
     *
     * Hotspot copy comes from the CMS; where each one sits (its landmark and default
     * angle) comes from the scene package's data, matched by key.
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
     * The siteplan dots (Unit collection).
     *
     * @return Collection<int, array{id: string, x: float, y: float, status: string}>
     */
    private static function units(): Collection
    {
        return Cms::entries('units')->alphabetical()->get()
            ->map(function (EntryTranslation $unit): array {
                $values = $unit->values();

                return [
                    'id' => $unit->title,
                    'x' => (float) ($values['x'] ?? 0),
                    'y' => (float) ($values['y'] ?? 0),
                    'status' => in_array($values['sale_status'] ?? null, self::STATUSES, true) ? $values['sale_status'] : 'available',
                ];
            })
            ->values();
    }

    private static function url(?Media $media): ?string
    {
        return $media?->url();
    }

    /** An area in m², with a decimal comma only when it has decimals ("120", "120,5"). */
    private static function number(int|float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',');
    }
}
