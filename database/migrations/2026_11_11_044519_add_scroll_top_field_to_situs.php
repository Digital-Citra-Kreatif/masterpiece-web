<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Situs: tombol ke atas */
return new class extends Migration
{
    private const FIELD = [
        'handle' => 'scroll_top_active',
        'type' => 'toggle',
        'label' => 'Tombol ke atas',
        'required' => false,
        'instructions' => 'Tombol kembali ke atas di kanan bawah.',
        'translatable' => false,
        'validation' => [],
        'config' => ['default' => false],
        'fields' => [],
        'width' => 50,
    ];

    public function up(): void
    {
        $this->fields(function (array $fields): array {
            if (! in_array(self::FIELD['handle'], array_column($fields, 'handle'), true)) {
                $fields[] = self::FIELD;
            }

            return $fields;
        });
    }

    public function down(): void
    {
        $this->fields(fn (array $fields): array => array_values(array_filter($fields, fn (array $field): bool => ($field['handle'] ?? null) !== self::FIELD['handle'])));
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
        // Segarkan cache
        app(SiteCache::class)->changed('blueprint situs tombol ke atas');
    }
};
