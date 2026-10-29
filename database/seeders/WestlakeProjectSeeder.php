<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Sunrice\Admin\Actions\Media\SaveMediaFolder;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Structure\StructureImporter;

/** Proyek Westlake Village */
class WestlakeProjectSeeder extends Seeder
{
    private const SLUG = 'ruko-westlake-village';

    private const SOURCE = 'project-one/Data & Image';

    /** Field tipe yang diisi bila kosong */
    private const TYPE_DETAILS = ['suitable_title', 'suitable_for', 'building_specs', 'building_specs_note'];

    /** @var array<string, int> file content hash => media id */
    private array $uploaded = [];

    private ?MediaFolder $folder = null;

    /** Jalankan seeder */
    public function run(StructureImporter $importer, SaveDraft $saveDraft, PublishEntry $publish): void
    {
        $definitions = app(SiteContentSeeder::class);
        $importer->import(['blueprints' => [$definitions->blueprint('tipe')]]);
        // Paksa impor ulang
        $importer->import(['blueprints' => [$definitions->blueprint('project')]], force: true);
        $importer->import(['entries' => $this->typeEntries()]);
        $this->fillTypeDetails($saveDraft, $publish);

        $translation = EntryTranslation::query()->where('slug', self::SLUG)->where('locale', 'id')->firstOrFail();
        $stored = array_filter($translation->draft_data ?? []);
        $missing = array_filter(
            $this->sections(),
            fn (callable $section, string $handle): bool => ! isset($stored[$handle]) || self::isOutdated($handle, $stored[$handle]),
            ARRAY_FILTER_USE_BOTH,
        );
        if ($missing === []) {
            return;
        }

        $data = collect($missing)->map(fn (callable $section): array => $section())->all();
        $publish->handle($saveDraft->handle($translation, ['data' => $data, 'version' => $translation->version]));
    }

    /**
     * Isi tiap section
     *
     * @return array<string, callable(): array<string, mixed>>
     */
    private function sections(): array
    {
        return [
            'hero' => fn (): array => [
                'heading' => 'A New Landmark by the Lake',
                'description' => 'Introducing a new lakeside community park in the heart of West Village Business District, BSD City.',
                'media' => [$this->image('Image Westlake/Rk West LS BEV 1.jpg'), $this->image('Image Westlake/Rk West LS Danau Far.jpg')],
            ],
            'masterplan' => fn (): array => [
                'title' => 'Masterplan',
                'description' => 'Perfectly positioned in the heart of West Village Business District, surrounded by residentials, communities, business hubs, and lifestyle destinations.',
                'points' => $this->rows([
                    ['01', 'West Lakeside', 'Data Masterplan/West-Lakeside-BSD-Position.webp'],
                    ['02', 'West Village Business Park 1', 'Data Masterplan/West Village 1.jpg'],
                    ['03', 'West Village Business Park 2', 'Data Masterplan/West Village 2.jpg'],
                    ['04', 'Genesis Global Community School', 'Data Masterplan/Genesis Global Community School.png'],
                    ['05', 'Office & Hotel', null],
                    ['06', 'JX Hometown', null],
                    ['07', 'Ikan Bakar Cianjur', 'Data Masterplan/Ikan Bakar Cianjur.jpeg'],
                    ['08', 'All Fresh Supermarket', 'Data Masterplan/All Fresh.jpeg'],
                    ['09', 'Grandlucky Superstore', 'Data Masterplan/Grand Lucky.png'],
                    ['10', 'The Barn', 'Data Masterplan/The Barn.png'],
                    ['11', 'SPBU BP', 'Data Masterplan/Pom Bensin BP.png'],
                ], fn (array $point): array => ['number' => $point[0], 'name' => $point[1], 'image' => $this->image($point[2])]),
            ],
            'location' => fn (): array => [
                'title' => 'Location',
                'description' => 'Direct access to the toll road, with residential, education and public facilities within a 1–3 km range.',
                'maps' => [$this->image('Data Location/Maps Westlake.png'), $this->image('Data Location/Maps_page-0001.jpg')],
            ],
            'cluster' => fn (): array => [
                'title' => 'Cluster',
                'points' => $this->rows([
                    ['01', 'Lakeview Units', 'Uninterrupted lake views create a unique destination, enhancing every visit with a relaxing waterfront ambience.', 'Image Westlake/Ruko Hadap Danau.jpg'],
                    ['02', 'Streetview Units', 'Position your business at the front of West Lakeside, with ROW 20–24 providing strong visibility and easy access for customers.', 'Image Westlake/Rk West LS Frontal Boulevard 1.jpg'],
                    ['03', 'Main Plaza', 'The main entrance to the promenade area: integrated pedestrian shortcuts provide immediate and convenient access to the Lakeside Social Deck.', 'Image Westlake/Rk West LS Plaza.jpg'],
                    ['04', 'Lakeside Social Deck', '4 public social decks ranging from 90–120 sqm, designed for events, gatherings, and pop-up activities, overlooking the lake.', 'Image Westlake/Rk West LS Deck.jpg'],
                    ['05', 'Lakeside Jogging Track', 'A scenic lakeside jogging track lined with cafes and shops, seamlessly blending fitness with vibrant social spaces.', 'Image Westlake/Rk West LS Jogging Track.jpg'],
                    ['06', 'Lakefront Pods', '', null],
                ], fn (array $point): array => ['number' => $point[0], 'name' => $point[1], 'description' => $point[2], 'image' => $this->image($point[3])]),
            ],
            'facilities' => fn (): array => [
                'title' => 'Facilities',
                'description' => 'Where lifestyle creates opportunity: a destination designed to elevate customer experiences while supporting thriving businesses.',
                'items' => $this->rows([
                    ['Lakeside Social Deck', 'Data Facilities/Lakeside Social Deck.jpg'],
                    ['Walkable Neighborhood', 'Data Facilities/Walkable Neighborhood.jpg'],
                    ['Prime Location in 10,5 Ha Area', 'Data Facilities/Prime Location In 10,5 Ha Area.jpg'],
                    ['Stunning Sunset View', 'Data Facilities/Stunning Sunset View.jpg'],
                ], fn (array $item): array => ['name' => $item[0], 'image' => $this->image($item[1])]),
            ],
            'types' => fn (): array => [
                'title' => 'Type',
                'hero_image' => $this->image('Data Type Unit/Rk West LS Potongan.jpg'),
                'units' => self::entryIds('types', array_column($this->typeEntries(), 'slug')),
            ],
            'availability' => fn (): array => [
                'title' => 'Unit Availability',
                'description' => 'Siteplan of every block. Status: available, reserved, sold.',
                'siteplan' => $this->image('Data Unit/Unit Avalability.png'),
            ],
        ];
    }

    /**
     * Tipe unit proyek
     *
     * @return list<array<string, mixed>>
     */
    private function typeEntries(): array
    {
        $sizes = [
            'A' => ['Ruko Hook', '4,5 x 10 m', 45, 93],
            'BCD' => ['Ruko Standard', '4 x 10 m', 40, 82],
            'E' => ['Ruko Hook', '5 x 10 m', 50, 103],
        ];
        $views = ['streetview' => 'Streetview', 'lakeview' => 'Lakeview'];
        // Presentasi hal. 35
        $suitable = [
            'streetview' => ['Cocok untuk usaha yang butuh eksposur maksimal', ['Klinik', 'Studio pilates', 'Minimarket', 'Ritel', 'Apotek', 'Kantor']],
            'lakeview' => ['Cocok untuk usaha berbasis pengalaman pelanggan', ['Kafe', 'Restoran', 'Toko teh artisan', 'Wellness', 'Restoran keluarga']],
        ];

        return collect($views)
            ->flatMap(fn (string $prefix, string $view): array => collect($sizes)->map(fn (array $size, string $type): array => self::entry(
                'types',
                'westlake-'.str("{$prefix} type {$type}")->slug(),
                $prefix.' '.($type === 'BCD' ? 'Type B/C/D' : "Type {$type}"),
                [
                    'label' => $size[0],
                    'view' => $view,
                    'dimensions' => $size[1],
                    'image' => $this->image("Data Type Unit/{$prefix} Type {$type}.png"),
                    'lt' => $size[2],
                    'lb' => $size[3],
                    'floors' => 2,
                    'floor_plans' => [['label' => 'Lantai 1 & 2', 'image' => $this->image("Data Type Unit/Denah {$prefix} Type {$type} - LT LB.webp")]],
                    'gallery' => [],
                    'suitable_title' => $suitable[$view][0],
                    'suitable_for' => array_map(fn (string $name): array => ['name' => $name], $suitable[$view][1]),
                    'building_specs' => self::buildingSpecs(),
                    'building_specs_note' => '*Spesifikasi dan material dapat berubah sewaktu-waktu tanpa pemberitahuan.',
                    'placeholder' => false,
                ],
            ))->values()->all())
            ->all();
    }

    /**
     * Spesifikasi bangunan, presentasi hal. 54
     *
     * @return list<array{label: string, value: string}>
     */
    private static function buildingSpecs(): array
    {
        return collect([
            'Pondasi' => 'Mini pile',
            'Struktur utama' => 'Beton bertulang',
            'Dinding' => 'Bata ringan (AAC), diplester dan dicat',
            'Plafon' => 'Gypsum board',
            'Atap' => 'Zincalume gelombang, rangka baja ringan dan insulasi',
            'Pintu utama' => 'Rangka aluminium dengan kaca',
            'Jendela' => 'Rangka aluminium dengan kaca',
            'Fasad' => 'Dinding plester cat, rangka aluminium dengan kaca',
            'Lantai teras' => 'Homogenous tile anti-slip',
            'Lantai dalam' => 'Homogenous tile',
            'Lantai toilet' => 'Homogenous tile anti-slip',
            'Sanitasi' => 'Standard set',
            'Listrik' => '3500 VA',
            'Internet' => 'Fiber optic',
            'Air bersih' => 'PAM',
        ])->map(fn (string $value, string $label): array => ['label' => $label, 'value' => $value])->values()->all();
    }

    /** Isi field tipe yang masih kosong */
    private function fillTypeDetails(SaveDraft $saveDraft, PublishEntry $publish): void
    {
        foreach ($this->typeEntries() as $type) {
            $translation = EntryTranslation::query()
                ->whereRelation('entry.collection', 'handle', 'types')
                ->where('locale', 'id')
                ->where('slug', $type['slug'])
                ->first();
            if ($translation === null) {
                continue;
            }

            $stored = $translation->draft_data ?? [];
            $missing = array_filter(
                Arr::only($type['translations']['id']['data'], self::TYPE_DETAILS),
                fn (mixed $value, string $handle): bool => blank($stored[$handle] ?? null),
                ARRAY_FILTER_USE_BOTH,
            );
            if ($missing === []) {
                continue;
            }

            $publish->handle($saveDraft->handle($translation, ['data' => $missing, 'version' => $translation->version]));
        }
    }

    /**
     * Entri terbit
     *
     * @param  array<string, mixed>  $data
     * @return array{collection: string, slug: string, translations: array<string, array<string, mixed>>}
     */
    private static function entry(string $collection, string $slug, string $title, array $data): array
    {
        return [
            'collection' => $collection,
            'slug' => $slug,
            'translations' => ['id' => ['title' => $title, 'slug' => $slug, 'published' => true, 'data' => $data]],
        ];
    }

    /**
     * ID entri koleksi
     *
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private static function entryIds(string $collection, array $slugs): array
    {
        $ids = EntryTranslation::query()
            ->whereRelation('entry.collection', 'handle', $collection)
            ->where('locale', 'id')
            ->whereIn('slug', $slugs)
            ->pluck('entry_id', 'slug');

        return collect($slugs)->map(fn (string $slug): int => $ids[$slug])->all();
    }

    /** Bentuk section lama */
    private static function isOutdated(string $handle, mixed $stored): bool
    {
        return match ($handle) {
            'hero' => str_contains((string) ($stored['heading'] ?? ''), "\n"),
            'facilities' => collect($stored['items'] ?? [])->contains(fn (mixed $item): bool => ! is_array($item)),
            'types' => collect($stored['units'] ?? [])->contains(fn (mixed $unit): bool => ! is_int($unit)),
            default => false,
        };
    }

    /**
     * @param  list<array<int, mixed>>  $items
     * @param  callable(array<int, mixed>): array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function rows(array $items, callable $row): array
    {
        return array_map($row, $items);
    }

    /** ID media foto */
    private function image(?string $path): ?int
    {
        if ($path === null) {
            return null;
        }

        $source = collect(glob(base_path(self::SOURCE.'/'.dirname($path)).'/*'))
            ->first(fn (string $file): bool => strcasecmp(basename($file), basename($path)) === 0)
            ?? throw new \RuntimeException("Missing in the client's material: {$path}");
        $hash = md5_file($source);

        if (isset($this->uploaded[$hash])) {
            return $this->uploaded[$hash];
        }

        $existing = Media::query()->where('filename', basename($source))->where('size', filesize($source))->first();

        return $this->uploaded[$hash] = ($existing ?? app(UploadMedia::class)->handle(
            new UploadedFile($source, basename($source), null, null, true),
            ['folder_id' => $this->folder()->id, 'alt' => pathinfo($source, PATHINFO_FILENAME)],
        ))->id;
    }

    /** Folder media proyek */
    private function folder(): MediaFolder
    {
        if ($this->folder !== null) {
            return $this->folder;
        }

        $saveFolder = app(SaveMediaFolder::class);
        $root = MediaFolder::query()->whereNull('parent_id')->where('name', 'Projects')->first() ?? $saveFolder->handle('Projects');

        return $this->folder = MediaFolder::query()->where('parent_id', $root->id)->where('name', self::SLUG)->first()
            ?? $saveFolder->handle(self::SLUG, $root);
    }
}
