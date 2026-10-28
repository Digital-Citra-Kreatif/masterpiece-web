<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\SetHomepage;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Structure\StructureImporter;

/** Struktur konten CMS */
class SiteContentSeeder extends Seeder
{
    /** @var list<array{int, string, string, int|float, int|float, int, int, int}> number, label, card image, LT, LB, bedrooms, bathrooms, floors */
    private const TYPES = [
        [1, 'Ruko Hook', 'ruko-front-34.jpg', 120, 180, 2, 2, 2],
        [2, '', 'ruko-34.jpg', 90, 150, 2, 2, 2],
        [3, '', 'ruko-side-2.jpg', 100, 165, 3, 2, 2],
        [4, '', 'ruko-front.jpg', 110, 170, 3, 3, 2],
        [5, '', 'ruko-back-34.jpg', 126, 190, 3, 3, 2],
        [6, '', 'ruko-side.jpg', 140, 240, 4, 3, 3],
    ];

    private const GALLERY = ['ruko-front.jpg', 'ruko-34.jpg', 'ruko-side.jpg', 'ruko-back.jpg', 'ruko-back-34.jpg', 'ruko-side-2.jpg', 'ruko-front-34.jpg'];

    /** Jalankan seeder */
    public function run(StructureImporter $importer): void
    {
        // Rute proyek Types
        $structure = $this->structure();
        $types = collect($structure['collections'])->firstWhere('handle', 'types');
        $tipe = Blueprint::query()->where('handle', 'tipe')->first();
        if (! collect($tipe->fields ?? [])->contains('handle', 'project')) {
            $structure['collections'] = array_map(fn (array $collection): array => $collection['handle'] === 'types' ? [...$collection, 'route' => '/tipe/{slug}'] : $collection, $structure['collections']);
        }
        $importer->import($structure);
        $importer->import(['collections' => [$types]]);
        $importer->import([
            'globals' => $this->globals(),
            'entries' => [...$this->types(), $this->homepage()],
            'settings' => ['site' => [
                'description' => 'Kawasan ruko dan hunian. Jelajahi kawasan, cluster, fasilitas, dan tipe unit dalam 360°.',
            ]],
        ]);

        $home = Collection::query()->where('handle', 'pages')->firstOrFail()
            ->entries()->whereHas('translations', fn ($query) => $query->where('slug', 'home'))->firstOrFail();
        app(SetHomepage::class)->handle($home);
    }

    /**
     * @return array<string, mixed>
     */
    private function structure(): array
    {
        $text = fn (string $handle, array $extra = []): array => $this->field($handle, 'text', [], $extra);
        $textarea = fn (string $handle, array $extra = []): array => $this->field($handle, 'textarea', [], $extra);
        $image = fn (string $handle, array $extra = []): array => $this->field($handle, 'media', ['types' => ['image']], $extra);
        $number = fn (string $handle, array $config = []): array => $this->field($handle, 'number', ['min' => 0, ...$config], ['required' => true, 'width' => 25]);

        return [
            'blueprints' => [
                ['handle' => 'page', 'title' => 'Halaman', 'fields' => [$textarea('intro'), $this->field('body', 'rich_text')]],
                // Section lain hardcoded
                ['handle' => 'homepage', 'title' => 'Beranda', 'fields' => [
                    $this->field('hero', 'group', [], ['label' => 'Hero banner', 'fields' => [
                        $textarea('heading', ['label' => 'Heading', 'required' => true, 'instructions' => 'Baris baru tetap jadi baris baru.']),
                        $textarea('description', ['label' => 'Deskripsi']),
                        $this->field('media', 'media', ['types' => ['image', 'video'], 'multiple' => true, 'max' => 6], [
                            'label' => 'Media',
                            'translatable' => false,
                            'instructions' => 'Gambar/video 1920×1080. Lebih dari 1 file = slider.',
                        ]),
                    ]]),
                    ...collect(['masterplan' => 'Masterplan', 'lokasi' => 'Lokasi', 'cluster' => 'Cluster', 'fasilitas' => 'Fasilitas', 'tipe' => 'Tipe unit', 'unit_update' => 'Status siteplan'])
                        ->map(fn (string $label, string $handle): array => $this->field($handle, 'group', [], ['label' => $label, 'fields' => [
                            $text('title', ['label' => 'Judul']),
                            $textarea('description', ['label' => 'Deskripsi']),
                        ]]))
                        ->values()
                        ->all(),
                ]],
                ['handle' => 'tipe', 'title' => 'Tipe', 'fields' => [
                    $this->field('project', 'entries', ['collections' => ['projects'], 'multiple' => false], [
                        'label' => 'Proyek',
                        'translatable' => false,
                        'instructions' => 'Proyek tipe ini; slug-nya masuk ke URL tipe (/proyek/tipe/slug).',
                    ]),
                    $text('label', ['instructions' => 'Baris kecil di bawah nama, misalnya "Ruko Hook".']),
                    $this->field('view', 'select', ['options' => [
                        ['value' => 'lakeview', 'label' => 'Lake View'],
                        ['value' => 'streetview', 'label' => 'Street View'],
                    ]], ['translatable' => false, 'width' => 50, 'instructions' => 'Untuk project dengan pilihan view.']),
                    $text('dimensions', ['translatable' => false, 'width' => 50, 'instructions' => 'Misalnya "4,5 x 10 m".']),
                    $image('image', ['required' => true, 'instructions' => 'Gambar kartu di beranda, rasio 6:5.']),
                    $number('lt'),
                    $number('lb'),
                    $this->field('bedrooms', 'number', ['min' => 0, 'step' => 1], ['width' => 25, 'instructions' => 'Kosongkan untuk ruko tanpa kamar tidur.']),
                    $this->field('bathrooms', 'number', ['min' => 0, 'step' => 1], ['width' => 25]),
                    $this->field('floors', 'number', ['min' => 1, 'step' => 1], ['required' => true, 'instructions' => 'Satu bagian per lantai di halaman tipe.']),
                    $textarea('intro'),
                    $this->field('info_rows', 'repeater', [], [
                        'instructions' => 'Info tambahan selain luas, lantai, dan kamar (yang sudah diambil dari isian di atas).',
                        'fields' => [$text('label', ['required' => true, 'width' => 50]), $text('value', ['required' => true, 'width' => 50])],
                    ]),
                    $this->field('floor_plans', 'repeater', ['max' => 6], [
                        'instructions' => 'Label "Lantai 1", "Lantai 2" … tampil bersama bagian lantai yang sama.',
                        'fields' => [$text('label', ['required' => true, 'width' => 50]), $image('image', ['required' => true, 'width' => 50])],
                    ]),
                    $this->field('gallery', 'media', ['types' => ['image'], 'multiple' => true], ['translatable' => false]),
                    $text('vr_url', ['translatable' => false, 'instructions' => 'Alamat tur 3DVista yang sudah dipublikasikan.']),
                    $this->field('placeholder', 'toggle', [], ['instructions' => 'Data contoh, belum dari klien.']),
                ]],
                ['handle' => 'unit', 'title' => 'Unit', 'fields' => [
                    $this->field('project', 'entries', ['collections' => ['projects'], 'multiple' => false], [
                        'label' => 'Proyek',
                        'required' => true,
                        'translatable' => false,
                        'width' => 50,
                        'instructions' => 'Judul entri = kode unit, misalnya "A-1".',
                    ]),
                    $this->field('sale_status', 'select', ['options' => [
                        ['value' => 'available', 'label' => 'Tersedia'],
                        ['value' => 'reserved', 'label' => 'Dipesan'],
                        ['value' => 'sold', 'label' => 'Terjual'],
                    ], 'default' => 'available'], ['label' => 'Status', 'required' => true, 'width' => 50]),
                ]],
                ['handle' => 'situs', 'title' => 'Situs', 'fields' => [
                    $this->field('nav', 'repeater', [], ['fields' => [
                        $text('label', ['required' => true, 'width' => 50]),
                        $this->field('section', 'select', ['options' => collect(['kawasan', 'peta', 'cluster', 'fasilitas', 'tipe', 'unit-update'])
                            ->map(fn (string $section): array => ['value' => $section, 'label' => $section])->all()], ['required' => true, 'width' => 50]),
                    ]]),
                    $this->field('status_labels', 'group', [], ['fields' => [
                        $text('sold', ['required' => true, 'width' => 33]),
                        $text('reserved', ['required' => true, 'width' => 33]),
                        $text('available', ['required' => true, 'width' => 33]),
                    ]]),
                    $image('siteplan', ['required' => true, 'instructions' => 'Mengganti siteplan berarti memeriksa ulang posisi setiap Unit (% dari gambar ini).']),
                    $this->field('siteplan_placeholder', 'toggle', [], ['instructions' => 'Siteplan contoh, bukan siteplan asli.']),
                    $this->field('protect_media', 'toggle', [], ['instructions' => 'Lindungi gambar & video: menutup menu simpan gambar/video di browser.']),
                ]],
                $this->projectBlueprint(),
                $this->footerBlueprint(),
            ],
            'collections' => [
                ['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'blueprints' => ['homepage'], 'hierarchical' => true, 'ordered' => true, 'route' => '/{slug}'],
                ['handle' => 'types', 'title' => 'Types', 'blueprint' => 'tipe', 'ordered' => true, 'route' => '/{project}/tipe/{slug}'],
                ['handle' => 'units', 'title' => 'Units', 'blueprint' => 'unit'],
                ['handle' => 'projects', 'title' => 'Projects', 'blueprint' => 'project', 'ordered' => true, 'route' => '/projects/{slug}'],
            ],
        ];
    }

    /**
     * Definisi satu blueprint
     *
     * @return array<string, mixed>
     */
    public function blueprint(string $handle): array
    {
        return collect($this->structure()['blueprints'])->firstOrFail(fn (array $blueprint): bool => $blueprint['handle'] === $handle);
    }

    /**
     * Blueprint proyek
     *
     * @return array<string, mixed>
     */
    private function projectBlueprint(): array
    {
        $whenActive = ['condition' => ['field' => 'active', 'operator' => 'equals', 'value' => true]];
        $hex = fn (string $handle, string $label, array $extra = []): array => $this->field($handle, 'text', ['placeholder' => '#000000'], [
            'label' => $label,
            'translatable' => false,
            'validation' => ['regex:/^#[0-9A-Fa-f]{6}$/'],
            ...$extra,
        ]);
        $font = fn (string $handle, string $label): array => $this->field($handle, 'media', [], ['label' => $label, 'translatable' => false, 'width' => 33]);
        $image = fn (string $handle, string $label, array $extra = []): array => $this->field($handle, 'media', ['types' => ['image']], [
            'label' => $label, 'translatable' => false, ...$extra,
        ]);
        $text = fn (string $handle, string $label, array $extra = []): array => $this->field($handle, 'text', [], ['label' => $label, ...$extra]);

        $globalStyle = $this->field('global_style', 'group', [], ['label' => 'Global Style', 'fields' => [
            $this->field('active', 'toggle', ['default' => false], ['label' => 'Aktif', 'translatable' => false, 'instructions' => 'Aktif: tampilan project mengikuti Global Style.']),
            $image('logo', 'Logo', ['width' => 25, ...$whenActive]),
            $this->field('palette', 'group', [], [
                'label' => 'Warna',
                'width' => 75,
                ...$whenActive,
                // Token warna situs
                'fields' => collect([
                    1 => 'Warna utama',
                    2 => 'Warna utama gelap',
                    3 => 'Warna utama terang',
                    4 => 'Latar halaman',
                    5 => 'Latar sekunder',
                    6 => 'Teks',
                    7 => 'Latar gelap',
                    8 => 'Teks di latar gelap',
                ])->map(fn (string $label, int $number): array => $hex("color_{$number}", $label, ['width' => 25]))->values()->all(),
            ]),
            $this->field('fonts', 'group', [], [
                'label' => 'Font',
                ...$whenActive,
                'fields' => [$font('heading', 'Heading'), $font('description', 'Deskripsi'), $font('button', 'Tombol')],
            ]),
            // Bentuk atau gambar
            $this->field('marker', 'group', [], [
                'label' => 'Penanda',
                ...$whenActive,
                'fields' => [
                    $this->field('type', 'select', ['options' => [
                        ['value' => 'shape', 'label' => 'Warna & bentuk'],
                        ['value' => 'image', 'label' => 'Gambar / SVG'],
                    ], 'default' => 'shape'], ['label' => 'Jenis', 'required' => true, 'translatable' => false, 'width' => 33]),
                    $hex('color', 'Warna', ['width' => 33, 'condition' => ['field' => 'type', 'operator' => 'not_equals', 'value' => 'image']]),
                    $this->field('shape', 'select', ['options' => [
                        ['value' => 'circle', 'label' => 'Lingkaran'],
                        ['value' => 'square', 'label' => 'Kotak'],
                        ['value' => 'diamond', 'label' => 'Belah ketupat'],
                        ['value' => 'pin', 'label' => 'Pin'],
                    ], 'default' => 'circle'], ['label' => 'Bentuk', 'required' => true, 'translatable' => false, 'width' => 33, 'condition' => ['field' => 'type', 'operator' => 'not_equals', 'value' => 'image']]),
                    $image('image', 'Gambar / SVG', ['width' => 33, 'condition' => ['field' => 'type', 'operator' => 'equals', 'value' => 'image']]),
                ],
            ]),
            $this->field('corner', 'group', [], [
                'label' => 'Sudut',
                ...$whenActive,
                'fields' => [
                    $this->field('style', 'select', ['options' => [
                        ['value' => 'rounded', 'label' => 'Membulat'],
                        ['value' => 'chamfer', 'label' => 'Chamfer'],
                    ], 'default' => 'rounded'], ['label' => 'Bentuk', 'translatable' => false, 'width' => 50]),
                    $this->field('size', 'number', ['min' => 0, 'step' => 1, 'suffix' => 'px'], ['label' => 'Ukuran', 'translatable' => false]),
                ],
            ]),
            $image('background', 'Background tema', $whenActive),
        ]]);

        return ['handle' => 'project', 'title' => 'Project', 'fields' => [
            ...$this->projectSections(),
            $this->field('whatsapp', 'group', [], ['label' => 'WhatsApp Floating', 'fields' => [
                $text('label', 'Label', ['width' => 50]),
                $text('heading', 'Heading', ['width' => 50]),
                $this->field('description', 'textarea', [], ['label' => 'Deskripsi singkat']),
                $this->field('show_button', 'toggle', [], ['label' => 'Tampilkan tombol', 'translatable' => false]),
                $this->field('contacts', 'repeater', [], ['label' => 'Kontak WhatsApp', 'fields' => [
                    $image('avatar', 'Avatar', ['width' => 50]),
                    $text('title', 'Judul', ['width' => 50]),
                    $text('number', 'Nomor WhatsApp', [
                        'required' => true,
                        'translatable' => false,
                        'validation' => ['regex:/^[0-9]{8,15}$/'],
                        'instructions' => 'Contoh: 6281234567890',
                    ]),
                    $this->field('message', 'textarea', [], ['label' => 'Pesan']),
                ]]),
            ]]),
            $globalStyle,
        ]];
    }

    /**
     * Section halaman proyek
     *
     * @return list<array<string, mixed>>
     */
    private function projectSections(): array
    {
        $text = fn (string $handle, string $label, array $extra = []): array => $this->field($handle, 'text', [], ['label' => $label, ...$extra]);
        $textarea = fn (string $handle, string $label, array $extra = []): array => $this->field($handle, 'textarea', [], ['label' => $label, ...$extra]);
        $image = fn (string $handle, string $label, array $extra = []): array => $this->field($handle, 'media', ['types' => ['image']], [
            'label' => $label, 'translatable' => false, ...$extra,
        ]);
        $gallery = fn (string $handle, string $label, array $extra = []): array => $this->field($handle, 'media', ['types' => ['image'], 'multiple' => true], [
            'label' => $label, 'translatable' => false, ...$extra,
        ]);
        $section = fn (string $handle, string $label, array $fields, bool $description = true): array => $this->field($handle, 'group', [], ['label' => $label, 'fields' => [
            $text('title', 'Judul'),
            ...($description ? [$textarea('description', 'Deskripsi')] : []),
            ...$fields,
        ]]);
        $percent = fn (string $handle, string $label, string $instructions): array => $this->field($handle, 'number', ['min' => 0, 'max' => 100], [
            'label' => $label, 'translatable' => false, 'width' => 50, 'instructions' => $instructions,
        ]);
        $points = fn (string $label, array $fields): array => $this->field('points', 'repeater', [], ['label' => $label, 'fields' => [
            $text('number', 'Nomor', ['translatable' => false, 'width' => 25]),
            $text('name', 'Nama', ['required' => true, 'width' => 75]),
            ...$fields,
        ]]);

        return [
            $this->field('hero', 'group', [], ['label' => 'Hero', 'fields' => [
                $text('heading', 'Heading'),
                $textarea('description', 'Deskripsi'),
                $this->field('media', 'media', ['types' => ['image', 'video'], 'multiple' => true, 'max' => 6], [
                    'label' => 'Media',
                    'translatable' => false,
                    'instructions' => 'Gambar/video 1920×1080.',
                ]),
            ]]),
            $section('masterplan', 'Masterplan', [
                $points('Titik kawasan', [
                    $image('image', 'Gambar'),
                    $percent('x', 'Posisi X (%)', 'Dari kiri gambar pertama orbit Masterplan. Kosong: titik tidak tampil.'),
                    $percent('y', 'Posisi Y (%)', 'Dari atas gambar pertama orbit Masterplan.'),
                ]),
            ]),
            $section('location', 'Lokasi', [
                $gallery('maps', 'Peta'),
            ]),
            $section('cluster', 'Cluster', [
                $points('Titik cluster', [
                    $image('image', 'Gambar', ['width' => 50]),
                    $textarea('description', 'Deskripsi', ['width' => 50]),
                    $percent('x', 'Posisi X (%)', 'Dari kiri gambar pertama orbit Cluster. Kosong: titik tidak tampil.'),
                    $percent('y', 'Posisi Y (%)', 'Dari atas gambar pertama orbit Cluster.'),
                ]),
            ], description: false),
            $section('facilities', 'Fasilitas', [
                // Label baris tanpa akhiran "s"
                $this->field('items', 'repeater', [], ['label' => 'Fasilitas proyek', 'fields' => [
                    $image('image', 'Gambar', ['required' => true, 'width' => 50]),
                    $text('name', 'Nama', ['required' => true, 'width' => 50]),
                ]]),
            ]),
            $section('turntable', '360°', [
                $this->field('tabs', 'repeater', [], ['label' => 'Tab', 'fields' => [
                    $text('name', 'Nama', ['required' => true, 'instructions' => 'Misalnya "Fasad" atau "Lantai 1".']),
                    $textarea('description', 'Deskripsi'),
                    $gallery('images', 'Gambar', ['instructions' => '1 gambar: gambar diam. Banyak gambar: diputar 360°, urut sesuai nama file. Kosong: memakai gambar tab pertama.']),
                    $this->field('frame', 'number', ['min' => 0, 'step' => 1], ['label' => 'Frame awal', 'translatable' => false, 'instructions' => 'Gambar ke berapa yang tampil saat tab dibuka, mulai 0.']),
                ]]),
            ], description: false),
            $section('types', 'Tipe', [
                $image('hero_image', 'Gambar potongan'),
                $this->field('units', 'entries', ['collections' => ['types'], 'multiple' => true], ['label' => 'Tipe unit', 'translatable' => false]),
            ], description: false),
            $section('availability', 'Ketersediaan unit', [
                $image('siteplan', 'Siteplan'),
            ]),
        ];
    }

    /**
     * Footer semua halaman
     *
     * @return array<string, mixed>
     */
    private function footerBlueprint(): array
    {
        $text = fn (string $handle, string $label, array $extra = []): array => $this->field($handle, 'text', [], ['label' => $label, ...$extra]);

        return ['handle' => 'footer', 'title' => 'Footer', 'fields' => [
            $this->field('logo', 'media', ['types' => ['image']], ['label' => 'Logo', 'translatable' => false, 'width' => 33]),
            $this->field('description', 'textarea', [], ['label' => 'Deskripsi singkat', 'width' => 66]),
            $this->field('address', 'textarea', [], ['label' => 'Alamat']),
            $this->field('hours', 'textarea', [], ['label' => 'Jam operasional', 'instructions' => 'Satu baris per jadwal, mis. Senin–Jumat, 09.00–17.00.']),
            $text('phone', 'Telepon', ['translatable' => false, 'width' => 33]),
            $text('email', 'Email', ['translatable' => false, 'width' => 33, 'validation' => ['email']]),
            $text('whatsapp', 'WhatsApp', [
                'translatable' => false,
                'width' => 33,
                'validation' => ['regex:/^[0-9]{8,15}$/'],
                'instructions' => 'Contoh: 6281234567890',
            ]),
        ]];
    }

    /**
     * Menu footer (Menus)
     *
     * @return array<string, mixed>
     */
    public function footerMenu(): array
    {
        return ['handle' => 'footer_menu', 'title' => 'Footer Menu', 'items' => [
            ['entry' => 'pages/home', 'labels' => ['id' => 'Home'], 'new_tab' => false, 'children' => []],
            ['entry' => 'pages/projects', 'labels' => [], 'new_tab' => false, 'children' => []],
            ['entry' => 'pages/unit', 'labels' => [], 'new_tab' => false, 'children' => []],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function globals(): array
    {
        $nav = ['kawasan' => 'Masterplan', 'peta' => 'Lokasi', 'cluster' => 'Cluster', 'fasilitas' => 'Fasilitas', 'tipe' => 'Tipe', 'unit-update' => 'Unit'];

        return [
            ['handle' => 'situs', 'title' => 'Situs', 'blueprint' => 'situs', 'values' => ['id' => [
                'nav' => collect($nav)->map(fn (string $label, string $section): array => ['label' => $label, 'section' => $section])->values()->all(),
                'status_labels' => ['sold' => 'Terjual', 'reserved' => 'Dipesan', 'available' => 'Tersedia'],
                'siteplan' => $this->media('media/placeholder/siteplan.svg', 'Siteplan cluster'),
                'siteplan_placeholder' => true,
                'protect_media' => false,
            ]]],
            // Diisi di CMS
            ['handle' => 'footer', 'title' => 'Footer', 'blueprint' => 'footer', 'values' => ['id' => [
                'logo' => $this->media('images/masterpiece-logo.png', 'Logo'),
            ]]],
        ];
    }

    /**
     * Halaman beranda
     *
     * @return array<string, mixed>
     */
    private function homepage(): array
    {
        return $this->entry('pages', 'home', 'Beranda', [
            'hero' => [
                'heading' => "Quiet Living,\nClose to Everything.",
                'description' => "A residential cluster in Jakarta,\ndesigned for growing families.",
                'media' => collect(['ruko-front-34.jpg', 'fac-lake.jpg', 'fac-boulevard.jpg'])
                    ->map(fn (string $file): int => $this->media("media/stills/{$file}", ''))
                    ->all(),
            ],
            'masterplan' => ['title' => 'Masterplan', 'description' => "A residential cluster in Jakarta,\ndesigned for growing families."],
            'lokasi' => ['title' => 'Lokasi', 'description' => 'Posisi proyek di dalam kawasan yang lebih besar.'],
            'cluster' => ['title' => 'Cluster', 'description' => 'Lebih dekat ke cluster ruko. Gulir untuk mengelilingi blok, klik penanda untuk menuju setiap titik.'],
            'fasilitas' => ['title' => 'Ruang bersama', 'description' => 'Fasilitas yang melengkapi kawasan, dari tepi danau hingga pusat komersial.'],
            'tipe' => ['title' => 'Pilih tipe', 'description' => 'Bandingkan luas tanah, luas bangunan, dan jumlah kamar. Buka satu tipe untuk menjelajahi fasad dan setiap lantainya.'],
            'unit_update' => ['title' => 'Status unit', 'description' => 'Status terbaru setiap unit di siteplan.'],
        ], blueprint: 'homepage');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function types(): array
    {
        $floorPlans = collect(['Lantai 1' => 'denah-lt1.svg', 'Lantai 2' => 'denah-lt2.svg', 'Atap' => 'denah-atap.svg'])
            ->map(fn (string $file, string $label): array => ['label' => $label, 'image' => $this->media("media/placeholder/{$file}", "Denah {$label}")])
            ->values()
            ->all();
        $gallery = collect(self::GALLERY)->map(fn (string $file): int => $this->media("media/stills/{$file}", ''))->all();

        return collect(self::TYPES)
            ->map(fn (array $type): array => $this->entry('types', "tipe-{$type[0]}", "Tipe {$type[0]}", [
                'label' => $type[1],
                'image' => $this->media("media/stills/{$type[2]}", ''),
                'lt' => $type[3],
                'lb' => $type[4],
                'bedrooms' => $type[5],
                'bathrooms' => $type[6],
                'floors' => $type[7],
                'intro' => 'Ruko dua lantai dengan area usaha di lantai dasar dan ruang hunian di atasnya. Teras depan terbuka ke jalan cluster.',
                'floor_plans' => $floorPlans,
                'gallery' => $gallery,
                'vr_url' => '/vr/placeholder/index.html',
                'placeholder' => true,
            ]))
            ->all();
    }

    /**
     * Entri terbit
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function entry(string $collection, string $slug, string $title, array $data, ?string $blueprint = null): array
    {
        return array_filter([
            'collection' => $collection,
            'slug' => $slug,
            'blueprint' => $blueprint,
            'translations' => ['id' => ['title' => $title, 'slug' => $slug, 'published' => true, 'data' => $data]],
        ], fn (mixed $value): bool => $value !== null);
    }

    /** ID media file */
    private function media(string $path, string $alt): int
    {
        $source = public_path($path);
        $existing = Media::query()->where('filename', basename($source))->where('size', filesize($source))->first();

        return ($existing ?? app(UploadMedia::class)->handle(
            new UploadedFile($source, basename($source), null, null, true),
            ['alt' => $alt !== '' ? $alt : null],
        ))->id;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function field(string $handle, string $type, array $config = [], array $extra = []): array
    {
        return ['handle' => $handle, 'type' => $type, 'config' => $config, ...$extra];
    }
}
