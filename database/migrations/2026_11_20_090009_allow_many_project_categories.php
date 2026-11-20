<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Banyak kategori proyek */
return new class extends Migration
{
    public function up(): void
    {
        $this->single(false);
    }

    public function down(): void
    {
        $this->single(true);
    }

    /** Atur satu kategori */
    private function single(bool $single): void
    {
        $taxonomy = DB::table('sunrice_taxonomies')->where('handle', 'project_categories')->value('id');
        $collection = DB::table('sunrice_collections')->where('handle', 'projects')->value('id');
        if ($taxonomy === null || $collection === null) {
            return;
        }

        DB::table('sunrice_collection_taxonomy')->where('taxonomy_id', $taxonomy)->where('collection_id', $collection)->update(['single' => $single]);
        // Segarkan cache
        app(SiteCache::class)->changed('kategori proyek banyak');
    }
};
