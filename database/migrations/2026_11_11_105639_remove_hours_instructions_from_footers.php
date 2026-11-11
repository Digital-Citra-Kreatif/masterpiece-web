<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Footer: petunjuk jam operasional dihapus */
return new class extends Migration
{
    private const TEXT = 'Satu baris per jadwal, mis. Senin–Jumat, 09.00–17.00.';

    public function up(): void
    {
        $this->instructions('');
    }

    public function down(): void
    {
        $this->instructions(self::TEXT);
    }

    /** Petunjuk field hours di footer global dan proyek */
    private function instructions(string $text): void
    {
        foreach (['footer', 'project'] as $handle) {
            $blueprint = DB::table('sunrice_blueprints')->where('handle', $handle)->first(['id', 'fields']);
            $fields = json_decode((string) $blueprint?->fields, true);
            if (! is_array($fields)) {
                continue;
            }

            foreach ($fields as &$field) {
                if ($handle === 'footer' && ($field['handle'] ?? null) === 'hours') {
                    $field['instructions'] = $text;
                }
                if ($handle === 'project' && ($field['handle'] ?? null) === 'footer') {
                    foreach ($field['fields'] as &$child) {
                        if (($child['handle'] ?? null) === 'hours') {
                            $child['instructions'] = $text;
                        }
                    }
                    unset($child);
                }
            }
            unset($field);

            DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($fields)]);
            // Segarkan cache
            app(SiteCache::class)->changed("blueprint {$handle} petunjuk jam");
        }
    }
};
