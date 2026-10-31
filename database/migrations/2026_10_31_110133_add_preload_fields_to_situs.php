<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Situs: pengaturan preload */
return new class extends Migration
{
    private const FIELDS = [
        [
            'handle' => 'preload_active',
            'type' => 'toggle',
            'label' => 'Preload aktif',
            'required' => false,
            'instructions' => 'Animasi pembuka saat pengunjung pertama masuk.',
            'translatable' => false,
            'validation' => [],
            'config' => ['default' => true],
            'fields' => [],
            'width' => 50,
        ],
        [
            'handle' => 'preload_hours',
            'type' => 'number',
            'label' => 'Tampil lagi setelah (jam)',
            'required' => false,
            'instructions' => 'Kosong: 6 jam.',
            'translatable' => false,
            'validation' => [],
            'config' => ['min' => 1, 'max' => 720],
            'fields' => [],
            'width' => 50,
        ],
    ];

    public function up(): void
    {
        $this->fields(function (array $fields): array {
            $handles = array_column($fields, 'handle');
            foreach (self::FIELDS as $field) {
                if (! in_array($field['handle'], $handles, true)) {
                    $fields[] = $field;
                }
            }

            return $fields;
        });
    }

    public function down(): void
    {
        $handles = array_column(self::FIELDS, 'handle');
        $this->fields(fn (array $fields): array => array_values(array_filter($fields, fn (array $field): bool => ! in_array($field['handle'] ?? null, $handles, true))));
    }

    /**
     * Ubah field blueprint Situs
     *
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    private function fields(callable $change): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'situs')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($change($fields))]);
        // Form CMS membaca blueprint dari cache situs
        app(SiteCache::class)->changed('blueprint situs preload');
    }
};
