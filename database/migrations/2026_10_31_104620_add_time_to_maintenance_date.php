<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Maintenance Mode: tanggal dengan jam */
return new class extends Migration
{
    public function up(): void
    {
        $this->date(function (array $field): array {
            $field['config'] = [...(is_array($field['config'] ?? null) ? $field['config'] : []), 'time' => true];
            if (($field['label'] ?? null) === 'Tanggal') {
                $field['label'] = 'Tanggal dan jam';
            }

            return $field;
        });
    }

    public function down(): void
    {
        $this->date(function (array $field): array {
            $config = is_array($field['config'] ?? null) ? $field['config'] : [];
            unset($config['time']);
            $field['config'] = $config;
            if (($field['label'] ?? null) === 'Tanggal dan jam') {
                $field['label'] = 'Tanggal';
            }

            return $field;
        });
    }

    /**
     * Ubah field `date` blueprint Maintenance Mode
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function date(callable $change): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'maintenance_mode')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        $fields = array_map(fn (mixed $field): mixed => is_array($field) && ($field['handle'] ?? null) === 'date' ? $change($field) : $field, $fields);

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($fields)]);
        // Form CMS membaca blueprint dari cache situs
        app(SiteCache::class)->changed('blueprint maintenance_mode date');
    }
};
