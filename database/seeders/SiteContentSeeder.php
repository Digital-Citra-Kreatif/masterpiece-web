<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\SetHomepage;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Structure\StructureImporter;

/**
 * The site's CMS structure and its first content: the homepage, Tipe, Fasilitas,
 * Unit and the Situs and Tipe: bersama globals, following the client's content model.
 *
 * Images and videos editors may replace are uploaded to the media library from
 * public/media. Copy marked as stand-in in the original project still is.
 *
 * Safe to run again: the importer only adds what is missing, and files already
 * in the media library are not uploaded twice.
 */
class SiteContentSeeder extends Seeder
{
    /** @var list<array{int, string, string, int|float, int|float, int, int, int, bool}> number, label, card image, LT, LB, bedrooms, bathrooms, floors, real facade */
    private const TYPES = [
        [1, 'Ruko Hook', 'ruko-front-34.jpg', 120, 180, 2, 2, 2, true],
        [2, '', 'ruko-34.jpg', 90, 150, 2, 2, 2, false],
        [3, '', 'ruko-side-2.jpg', 100, 165, 3, 2, 2, false],
        [4, '', 'ruko-front.jpg', 110, 170, 3, 3, 2, false],
        [5, '', 'ruko-back-34.jpg', 126, 190, 3, 3, 2, false],
        [6, '', 'ruko-side.jpg', 140, 240, 4, 3, 3, false],
    ];

    /** @var array<string, string> image => title */
    private const FACILITIES = [
        'fac-lake.jpg' => 'Danau & promenade',
        'fac-sport.jpg' => 'Lapangan olahraga & kolam renang',
        'fac-commercial.jpg' => 'Pusat komersial',
        'fac-boulevard.jpg' => 'Boulevard utama',
        'fac-plaza.jpg' => 'Deret ruko tepi air',
        'fac-green.jpg' => 'Ruang hijau',
    ];

    private const GALLERY = ['ruko-front.jpg', 'ruko-34.jpg', 'ruko-side.jpg', 'ruko-back.jpg', 'ruko-back-34.jpg', 'ruko-side-2.jpg', 'ruko-front-34.jpg'];

    /**
     * Run the database seeds.
     */
    public function run(StructureImporter $importer): void
    {
        $importer->import($this->structure());
        $importer->import([
            'globals' => $this->globals(),
            'entries' => [...$this->facilities(), ...$this->types(), ...$this->units(), $this->homepage()],
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
                // The other homepage sections are still hardcoded in resources/data/home.php.
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
                    $this->field('real_facade', 'toggle', [], ['instructions' => 'Sekuens 360° sudah render tipe ini sendiri.']),
                    $this->field('placeholder', 'toggle', [], ['instructions' => 'Data contoh, belum dari klien.']),
                ]],
                ['handle' => 'fasilitas', 'title' => 'Fasilitas', 'fields' => [$image('image', ['required' => true]), $textarea('description')]],
                ['handle' => 'unit', 'title' => 'Unit', 'fields' => [
                    $this->field('x', 'number', ['min' => 0, 'max' => 100], ['required' => true, 'width' => 33, 'instructions' => '% dari lebar siteplan.']),
                    $this->field('y', 'number', ['min' => 0, 'max' => 100], ['required' => true, 'width' => 33, 'instructions' => '% dari tinggi siteplan.']),
                    $this->field('sale_status', 'select', ['options' => [
                        ['value' => 'available', 'label' => 'Tersedia'],
                        ['value' => 'reserved', 'label' => 'Dipesan'],
                        ['value' => 'sold', 'label' => 'Terjual'],
                    ], 'default' => 'available'], ['required' => true, 'width' => 33]),
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
                ['handle' => 'tipe_bersama', 'title' => 'Tipe: bersama', 'fields' => [
                    $text('facade_name', ['required' => true]),
                    $textarea('facade_text', ['required' => true]),
                    $this->field('floor_texts', 'repeater', [], [
                        'instructions' => 'Teks untuk Lantai 1, 2, 3 … di setiap halaman tipe; lantai tanpa teks memakai teks terakhir.',
                        'fields' => [$textarea('text', ['required' => true])],
                    ]),
                ]],
                $this->projectBlueprint(),
            ],
            'collections' => [
                ['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'blueprints' => ['homepage'], 'hierarchical' => true, 'ordered' => true, 'route' => '/{slug}'],
                ['handle' => 'types', 'title' => 'Types', 'blueprint' => 'tipe', 'ordered' => true, 'route' => '/tipe/{slug}'],
                ['handle' => 'facilities', 'title' => 'Facilities', 'blueprint' => 'fasilitas', 'ordered' => true],
                ['handle' => 'units', 'title' => 'Units', 'blueprint' => 'unit'],
                ['handle' => 'projects', 'title' => 'Projects', 'blueprint' => 'project', 'ordered' => true, 'route' => '/projects/{slug}'],
            ],
        ];
    }

    /**
     * One blueprint's definition, to import it alone (other blueprints may have been changed in the admin).
     *
     * @return array<string, mixed>
     */
    public function blueprint(string $handle): array
    {
        return collect($this->structure()['blueprints'])->firstOrFail(fn (array $blueprint): bool => $blueprint['handle'] === $handle);
    }

    /**
     * The Project blueprint (title and slug are the entry's own): the project's
     * Global Style, its page sections and its WhatsApp floating button.
     *
     * Font files (.woff2, .woff, .ttf, .otf, or a ZIP of a family: unpacked, the regular one chosen) uploaded here are moved to the media folder Fonts / {slug}
     * (App\Support\ProjectFonts). Colours are hex codes: the CMS has no colour field.
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

        return ['handle' => 'project', 'title' => 'Project', 'fields' => [
            $this->field('global_style', 'group', [], ['label' => 'Global Style', 'fields' => [
                $this->field('active', 'toggle', ['default' => false], ['label' => 'Aktif', 'translatable' => false, 'instructions' => 'Aktif: tampilan project mengikuti Global Style.']),
                $image('logo', 'Logo', ['width' => 25, ...$whenActive]),
                $this->field('palette', 'group', [], [
                    'label' => 'Warna',
                    'width' => 75,
                    ...$whenActive,
                    // Each colour replaces one of the site's tokens (App\Support\ProjectPage::PALETTE); empty keeps the homepage's.
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
                // A marker is drawn (colour and shape) or an uploaded image/SVG: one or the other.
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
            ]]),
            ...$this->projectSections(),
            $this->field('whatsapp', 'group', [], ['label' => 'WhatsApp Floating', 'fields' => [
                $image('image', 'Gambar'),
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
                        'instructions' => 'Angka saja, diawali kode negara: 6281234567890.',
                    ]),
                    $this->field('message', 'textarea', [], ['label' => 'Pesan']),
                ]]),
            ]]),
        ]];
    }

    /**
     * The project's own content, one group per section of its page, in the
     * client's order: Hero, Masterplan, Lokasi, Cluster, Fasilitas, Tipe, Ketersediaan.
     *
     * Every section starts with its title and description; rows read the same way
     * everywhere (number and name, then image and description). Facilities and
     * unit types are entries of the Facilities and Types collections, picked here.
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
        $entries = fn (string $handle, string $label, string $collection, string $menu): array => $this->field($handle, 'entries', ['collections' => [$collection], 'multiple' => true], [
            'label' => $label,
            'translatable' => false,
            'instructions' => "Pilih dari {$menu}; urutan pilihan jadi urutan tampil.",
        ]);
        $view = fn (array $extra = []): array => $this->field('view', 'select', ['options' => [
            ['value' => 'lakeview', 'label' => 'Lake View'],
            ['value' => 'streetview', 'label' => 'Street View'],
        ]], ['label' => 'View', 'required' => true, 'translatable' => false, ...$extra]);
        $section = fn (string $handle, string $label, array $fields): array => $this->field($handle, 'group', [], ['label' => $label, 'fields' => [
            $text('title', 'Judul'),
            $textarea('description', 'Deskripsi'),
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
                    'instructions' => 'Gambar/video 1920×1080. Lebih dari 1 file = slider.',
                ]),
            ]]),
            $section('masterplan', 'Masterplan', [
                $image('map', 'Peta', ['instructions' => 'Foto aerial kawasan; titik di bawah ditempatkan di atas gambar ini.']),
                $points('Titik kawasan', [
                    $image('image', 'Gambar', ['width' => 50]),
                    $gallery('gallery', 'Gallery', ['width' => 50, 'instructions' => 'Opsional.']),
                    $percent('x', 'Posisi X (%)', 'Dari kiri gambar pertama orbit Masterplan. Kosong: titik hanya tampil di daftar.'),
                    $percent('y', 'Posisi Y (%)', 'Dari atas gambar pertama orbit Masterplan.'),
                ]),
            ]),
            $section('location', 'Lokasi', [
                $gallery('maps', 'Peta', ['instructions' => 'Peta lokasi; tandai akses jalan tol.']),
                $this->field('pins', 'repeater', [], ['label' => 'Titik peta', 'instructions' => 'Titik di peta pertama; namanya tampil saat titik disorot.', 'fields' => [
                    $text('name', 'Nama', ['required' => true]),
                    $percent('x', 'Posisi X (%)', 'Dari kiri peta.'),
                    $percent('y', 'Posisi Y (%)', 'Dari atas peta.'),
                ]]),
                $this->field('stats', 'repeater', [], ['label' => 'Angka kawasan', 'fields' => [
                    $text('value', 'Angka', ['required' => true, 'width' => 25]),
                    $text('label', 'Keterangan', ['required' => true, 'width' => 75]),
                ]]),
            ]),
            $section('cluster', 'Cluster', [
                $points('Titik cluster', [
                    $image('image', 'Gambar', ['width' => 50]),
                    $textarea('description', 'Deskripsi', ['width' => 50]),
                    $percent('x', 'Posisi X (%)', 'Dari kiri gambar pertama orbit Cluster. Kosong: titik hanya tampil di daftar.'),
                    $percent('y', 'Posisi Y (%)', 'Dari atas gambar pertama orbit Cluster.'),
                ]),
            ]),
            $section('facilities', 'Fasilitas', [
                $entries('items', 'Fasilitas', 'facilities', 'Facilities'),
            ]),
            $section('types', 'Tipe', [
                $image('hero_image', 'Gambar potongan', ['instructions' => 'Gambar hero di atas keunggulan unit.']),
                $this->field('advantages', 'repeater', [], ['label' => 'Keunggulan', 'fields' => [
                    $text('name', 'Nama', ['required' => true]),
                    $image('image', 'Gambar', ['width' => 50]),
                    $textarea('description', 'Deskripsi', ['width' => 50]),
                ]]),
                $this->field('views', 'repeater', [], ['label' => 'Pilihan view', 'fields' => [
                    $view(['width' => 25]),
                    $text('name', 'Nama', ['required' => true, 'width' => 75]),
                    $textarea('description', 'Deskripsi', ['width' => 50]),
                    $textarea('businesses', 'Cocok untuk usaha', ['width' => 50, 'instructions' => 'Satu jenis usaha per baris.']),
                ]]),
                $entries('units', 'Tipe unit', 'types', 'Types'),
                $this->field('specifications', 'repeater', [], ['label' => 'Spesifikasi bangunan', 'fields' => [
                    $text('label', 'Bagian', ['required' => true, 'width' => 25]),
                    $textarea('value', 'Spesifikasi', ['required' => true, 'width' => 75]),
                ]]),
            ]),
            $section('availability', 'Ketersediaan unit', [
                $image('siteplan', 'Siteplan'),
                $this->field('blocks', 'repeater', [], ['label' => 'Blok', 'fields' => [
                    $text('name', 'Nama', ['required' => true, 'width' => 25]),
                    $view(['width' => 25]),
                    $image('image', 'Blockplan', ['width' => 50]),
                ]]),
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function globals(): array
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
            ['handle' => 'tipe_bersama', 'title' => 'Tipe: bersama', 'blueprint' => 'tipe_bersama', 'values' => ['id' => [
                'facade_name' => 'Fasad',
                'facade_text' => 'Fasad dengan rangka kayu dan teras terbuka ke jalan cluster. Geser gambar untuk melihat setiap sisi.',
                'floor_texts' => [
                    ['text' => 'Area usaha menghadap jalan, dengan dapur, kamar mandi, gudang, dan tangga ke lantai atas.'],
                    ['text' => 'Ruang hunian: kamar tidur, ruang keluarga, dan kamar mandi, dengan bukaan ke arah jalan.'],
                    ['text' => 'Lantai tambahan untuk ruang kerja atau kamar tidur, dengan akses ke atap.'],
                ],
            ]]],
        ];
    }

    /**
     * The homepage: the hero banner and each section's title and description from the CMS, the rest hardcoded in resources/data/home.php.
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
    private function facilities(): array
    {
        return collect(self::FACILITIES)
            ->map(fn (string $title, string $image): array => $this->entry('facilities', pathinfo($image, PATHINFO_FILENAME), $title, [
                'image' => $this->media("media/stills/{$image}", $title),
            ]))
            ->values()
            ->all();
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
                'real_facade' => $type[8],
                'placeholder' => true,
            ]))
            ->all();
    }

    /**
     * The siteplan dots, from the stand-in positions in resources/data/units.json.
     *
     * @return list<array<string, mixed>>
     */
    private function units(): array
    {
        return collect(json_decode((string) file_get_contents(resource_path('data/units.json')), true))
            ->map(fn (array $unit): array => $this->entry('units', strtolower($unit['id']), $unit['id'], [
                'x' => $unit['x'],
                'y' => $unit['y'],
                'sale_status' => $unit['status'],
            ]))
            ->all();
    }

    /**
     * A published entry in the site's language (Indonesian).
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

    /**
     * The media library id of a file under public/, uploading it the first time.
     */
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
