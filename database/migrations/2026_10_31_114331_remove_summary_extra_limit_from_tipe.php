<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Tipe: Data ringkasan lain tanpa batas */
return new class extends Migration
{
    public function up(): void
    {
        $this->summaryExtra(function (array $config): array {
            unset($config['max']);

            return $config;
        });
    }

    public function down(): void
    {
        $this->summaryExtra(fn (array $config): array => [...$config, 'max' => 2]);
    }

    /**
     * Ubah config field `summary_extra` blueprint Tipe
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function summaryExtra(callable $change): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'tipe')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        $fields = array_map(function (mixed $field) use ($change): mixed {
            if (! is_array($field) || ($field['handle'] ?? null) !== 'summary_extra') {
                return $field;
            }
            $field['config'] = $change(is_array($field['config'] ?? null) ? $field['config'] : []);

            return $field;
        }, $fields);

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($fields)]);
        // Form CMS membaca blueprint dari cache situs
        app(SiteCache::class)->changed('blueprint tipe summary_extra');
    }
};
