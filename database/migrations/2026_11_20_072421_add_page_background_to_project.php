<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Proyek: background halaman */
return new class extends Migration
{
    public function up(): void
    {
        $this->fields(function (array $fields): array {
            if (in_array('page_background', array_column($fields, 'handle'), true)) {
                return $fields;
            }
            $field = fn (string $handle, string $type, string $label, array $config = [], array $extra = []): array => [
                'handle' => $handle,
                'type' => $type,
                'label' => $label,
                'required' => false,
                'instructions' => '',
                'translatable' => false,
                'validation' => [],
                'config' => $config,
                'fields' => [],
                ...$extra,
            ];
            $when = fn (string $value): array => ['condition' => ['field' => 'type', 'operator' => 'equals', 'value' => $value], 'width' => 33];

            $fields[] = $field('page_background', 'group', 'Background halaman', [], ['fields' => [
                $field('type', 'select', 'Jenis', [
                    'options' => [
                        ['value' => 'default', 'label' => 'Bawaan (putih)'],
                        ['value' => 'color', 'label' => 'Warna'],
                        ['value' => 'image', 'label' => 'Gambar'],
                    ],
                    'default' => 'default',
                ], ['width' => 33]),
                $field('color', 'text', 'Warna', ['placeholder' => '#000000'], ['validation' => ['regex:/^#[0-9A-Fa-f]{6}$/'], ...$when('color')]),
                $field('image', 'media', 'Gambar', ['types' => ['image']], ['instructions' => 'Menutupi layar, diam saat digulir.', ...$when('image')]),
            ]]);

            return $fields;
        });
    }

    public function down(): void
    {
        $this->fields(fn (array $fields): array => array_values(array_filter($fields, fn (array $field): bool => ($field['handle'] ?? null) !== 'page_background')));
    }

    /**
     * Ubah field Proyek
     *
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    private function fields(callable $change): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'project')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($change($fields))]);
        // Segarkan cache
        app(SiteCache::class)->changed('blueprint proyek background halaman');
    }
};
