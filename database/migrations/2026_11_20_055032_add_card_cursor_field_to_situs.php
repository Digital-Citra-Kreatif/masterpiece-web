<?php

use App\Support\Content;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Situs: kursor kartu */
return new class extends Migration
{
    public function up(): void
    {
        $this->fields(function (array $fields): array {
            if (! in_array('card_cursor', array_column($fields, 'handle'), true)) {
                $fields[] = [
                    'handle' => 'card_cursor',
                    'type' => 'select',
                    'label' => 'Kursor kartu',
                    'required' => false,
                    'instructions' => 'Kursor di atas kartu proyek dan tipe unit.',
                    'translatable' => false,
                    'validation' => [],
                    'config' => ['options' => Content::CARD_CURSOR_OPTIONS, 'default' => 'zoom'],
                    'fields' => [],
                    'width' => 50,
                ];
            }

            return $fields;
        });
    }

    public function down(): void
    {
        $this->fields(fn (array $fields): array => array_values(array_filter($fields, fn (array $field): bool => ($field['handle'] ?? null) !== 'card_cursor')));
    }

    /**
     * Ubah field Situs
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
        app(SiteCache::class)->changed('blueprint situs kursor kartu');
    }
};
