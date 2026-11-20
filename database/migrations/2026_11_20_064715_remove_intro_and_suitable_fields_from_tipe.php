<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Hapus field Tipe */
return new class extends Migration
{
    private const HANDLES = ['intro', 'suitable_title', 'suitable_for'];

    public function up(): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'tipe')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        $kept = array_values(array_filter($fields, fn (array $field): bool => ! in_array($field['handle'] ?? null, self::HANDLES, true)));
        if (count($kept) === count($fields)) {
            return;
        }

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($kept)]);
        // Segarkan cache
        app(SiteCache::class)->changed('blueprint tipe tanpa deskripsi');
    }

    /** Tidak dikembalikan */
    public function down(): void {}
};
