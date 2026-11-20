<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Tata form CMS */
final class CmsLayout
{
    /**
     * Label, petunjuk, lebar
     *
     * @var array<string, array<string, array{label?: string, instructions?: string, width?: int}>>
     */
    public const FIELDS = [
        'page' => [
            'heading' => ['label' => 'Judul'],
            'short_description' => ['label' => 'Deskripsi singkat'],
        ],
        'homepage' => [
            'hero' => ['label' => 'Hero'],
            'hero.heading' => ['label' => 'Judul'],
            'hero.video' => ['label' => 'Video latar'],
            'hero.image' => ['label' => 'Gambar latar'],
            'hero.css_id' => ['instructions' => 'Contoh: beranda', 'width' => 50],
            'projects.css_id' => ['instructions' => 'Contoh: proyek', 'width' => 50],
        ],
        'tipe' => [
            'project' => ['width' => 50],
            'label' => ['label' => 'Label', 'instructions' => 'Contoh: Ruko Hook', 'width' => 50],
            'dimensions' => ['label' => 'Ukuran', 'instructions' => 'Contoh: 4,5x10 m', 'width' => 50],
            'image' => ['label' => 'Gambar kartu', 'instructions' => 'Rasio 6:5', 'width' => 50],
            'bedrooms' => ['label' => 'Kamar tidur'],
            'bathrooms' => ['label' => 'Kamar mandi'],
            'floors' => ['label' => 'Jumlah lantai', 'width' => 25],
            'summary_extra' => ['label' => 'Ringkasan tambahan'],
            'summary_extra.label' => ['label' => 'Label'],
            'info_rows' => ['label' => 'Info unit', 'instructions' => 'Selain luas, kamar'],
            'info_rows.label' => ['label' => 'Label'],
            'info_rows.value' => ['label' => 'Nilai'],
            'building_specs_title' => ['label' => 'Judul spesifikasi', 'width' => 50],
            'building_specs_note' => ['label' => 'Catatan spesifikasi', 'width' => 50],
            'floor_plans' => ['label' => 'Denah', 'instructions' => 'Cadangan denah tab'],
            'floor_plans.label' => ['label' => 'Label'],
            'floor_plans.image' => ['label' => 'Gambar'],
            'gallery' => ['label' => 'Galeri'],
            'vr_url' => ['label' => 'Link VR'],
            'turntable.tabs.zoom_image' => ['instructions' => 'Tampil saat diklik', 'width' => 50],
            'turntable.tabs.floor_plan' => ['instructions' => 'Kosong: denah tipe', 'width' => 50],
        ],
        'situs' => [
            'logo_light' => ['instructions' => 'Di atas hero'],
            'logo_dark' => ['instructions' => 'Header latar terang'],
            'status_labels' => ['label' => 'Label status'],
            'status_labels.sold' => ['label' => 'Terjual'],
            'status_labels.reserved' => ['label' => 'Dipesan'],
            'status_labels.available' => ['label' => 'Tersedia'],
            'protect_media' => ['label' => 'Lindungi media', 'instructions' => 'Blokir simpan gambar', 'width' => 33],
            'preload_active' => ['label' => 'Preload', 'instructions' => 'Animasi pembuka', 'width' => 33],
            'preload_hours' => ['label' => 'Ulang preload (jam)', 'instructions' => 'Kosong: 6 jam', 'width' => 33],
            'scroll_top_active' => ['instructions' => 'Kanan bawah'],
            'card_cursor' => ['instructions' => 'Kartu proyek, tipe'],
            'card_cursor_icon' => ['instructions' => 'Tampil putih'],
            'card_cursor_text' => ['instructions' => 'Bila ikon kosong'],
        ],
        'project' => [
            'hero.heading' => ['label' => 'Judul'],
            'hero.media' => ['instructions' => 'Gambar/video 1920×1080'],
            'hero.video_file' => ['label' => 'Video popup'],
            'hero.video_url' => ['instructions' => 'Bila video kosong'],
            'card' => ['label' => 'Kartu proyek'],
            'card.description' => ['label' => 'Deskripsi singkat'],
            'masterplan.image' => ['label' => 'Gambar / ZIP 360°', 'instructions' => 'ZIP urut nama'],
            'masterplan.collage' => ['label' => 'Kolase pembuka', 'instructions' => 'Maks 4 gambar'],
            'masterplan.points.zoom_image' => ['instructions' => 'Resolusi tinggi'],
            'cluster.image' => ['label' => 'Gambar / ZIP 360°', 'instructions' => 'ZIP urut nama'],
            'cluster.points.zoom_image' => ['instructions' => 'Resolusi tinggi'],
            'facilities.items' => ['label' => 'Daftar fasilitas'],
            'types.title' => ['width' => 50],
            'types.others_title' => ['label' => 'Judul tipe lain', 'width' => 50],
            'whatsapp' => ['label' => 'WhatsApp'],
            'whatsapp.heading' => ['label' => 'Judul'],
            'whatsapp.contacts' => ['label' => 'Kontak'],
            'whatsapp.contacts.number' => ['label' => 'Nomor WhatsApp', 'instructions' => 'Contoh: 6281234567890'],
            'footer' => ['instructions' => 'Kosong: Footer global'],
            'global_style.active' => ['instructions' => 'Pakai gaya proyek'],
            // Warna 2 kolom
            'global_style.palette' => ['width' => 100],
            'global_style.palette.color_1' => ['width' => 50],
            'global_style.palette.color_2' => ['width' => 50],
            'global_style.palette.color_3' => ['width' => 50],
            'global_style.palette.color_4' => ['width' => 50],
            'global_style.palette.color_5' => ['width' => 50],
            'global_style.palette.color_6' => ['width' => 50],
            'global_style.palette.color_7' => ['width' => 50],
            'global_style.palette.color_8' => ['width' => 50],
            'global_style.fonts.heading_embed' => ['instructions' => 'Tempel kode @import'],
            'global_style.fonts.description_embed' => ['instructions' => 'Tempel kode @import'],
            'global_style.fonts.button_embed' => ['instructions' => 'Tempel kode @import'],
            'global_style.corner.size' => ['label' => 'Ukuran (px)', 'width' => 50],
            'page_background.image' => ['instructions' => 'Diam saat digulir'],
        ],
        'footer' => [
            'address' => ['width' => 50],
            'hours' => ['width' => 50],
            'phone' => ['width' => 50],
            'email' => ['width' => 50],
        ],
        'not_found' => [
            'link' => ['instructions' => 'Teks dan tujuan'],
        ],
    ];

    /** Terapkan ke blueprint */
    public static function apply(): void
    {
        $changed = false;
        foreach (self::FIELDS as $handle => $paths) {
            $blueprint = DB::table('sunrice_blueprints')->where('handle', $handle)->first(['id', 'fields']);
            $fields = json_decode((string) $blueprint?->fields, true);
            if (! is_array($fields)) {
                continue;
            }

            $tidy = $fields;
            foreach ($paths as $path => $set) {
                $tidy = self::set($tidy, explode('.', $path), $set);
            }
            if ($tidy !== $fields) {
                DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($tidy)]);
                $changed = true;
            }
        }

        if ($changed) {
            // Segarkan cache
            app(SiteCache::class)->changed('tata form CMS');
        }
    }

    /**
     * Ubah satu field
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  list<string>  $path
     * @param  array{label?: string, instructions?: string, width?: int}  $set
     * @return list<array<string, mixed>>
     */
    private static function set(array $fields, array $path, array $set): array
    {
        $handle = array_shift($path);
        foreach ($fields as $index => $field) {
            if (($field['handle'] ?? null) !== $handle) {
                continue;
            }
            $fields[$index] = $path === []
                ? [...$field, ...$set]
                : [...$field, 'fields' => self::set($field['fields'] ?? [], $path, $set)];
        }

        return $fields;
    }
}
