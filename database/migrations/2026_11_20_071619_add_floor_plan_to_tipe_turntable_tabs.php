<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Denah tab 360° */
return new class extends Migration
{
    private const FIELD = [
        'handle' => 'floor_plan',
        'type' => 'media',
        'label' => 'Denah',
        'required' => false,
        'instructions' => 'Tampil di kanan saat tab ini aktif. Kosong: denah tipe.',
        'translatable' => false,
        'validation' => [],
        'config' => ['types' => ['image']],
        'fields' => [],
    ];

    public function up(): void
    {
        $this->tabFields(function (array $fields): array {
            if (in_array(self::FIELD['handle'], array_column($fields, 'handle'), true)) {
                return $fields;
            }
            // Setelah gambar zoom
            $at = array_search('zoom_image', array_column($fields, 'handle'), true);
            array_splice($fields, $at === false ? count($fields) : $at + 1, 0, [self::FIELD]);

            return $fields;
        });
    }

    public function down(): void
    {
        $this->tabFields(fn (array $fields): array => array_values(array_filter($fields, fn (array $field): bool => ($field['handle'] ?? null) !== self::FIELD['handle'])));
    }

    /**
     * Ubah tab 360°
     *
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    private function tabFields(callable $change): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'tipe')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        $found = false;
        foreach ($fields as &$group) {
            if (($group['handle'] ?? null) !== 'turntable') {
                continue;
            }
            foreach ($group['fields'] as &$tabs) {
                if (($tabs['handle'] ?? null) === 'tabs') {
                    $tabs['fields'] = $change($tabs['fields'] ?? []);
                    $found = true;
                }
            }
            unset($tabs);
        }
        unset($group);
        if (! $found) {
            return;
        }

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($fields)]);
        // Segarkan cache
        app(SiteCache::class)->changed('blueprint tipe denah per tab');
    }
};
