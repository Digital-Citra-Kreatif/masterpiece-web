<?php

use Illuminate\Database\Migrations\Migration;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Structure\StructureImporter;

/** Projects: kategori proyek */
return new class extends Migration
{
    public function up(): void
    {
        // Sudah ada atau belum ada Projects: lewati
        if (Taxonomy::withTrashed()->where('handle', 'project_categories')->exists() || ! Collection::query()->where('handle', 'projects')->exists()) {
            return;
        }

        app(StructureImporter::class)->import([
            'taxonomies' => [[
                'handle' => 'project_categories',
                'title' => 'Kategori Proyek',
                'collections' => ['projects'],
                // Satu kategori, tanpa halaman kategori
                'collection_pages' => ['projects' => ['single' => true, 'pages' => false, 'index' => false]],
            ]],
            'terms' => [
                ['taxonomy' => 'project_categories', 'slug' => 'commercial', 'translations' => ['id' => ['title' => 'Commercial', 'slug' => 'commercial']]],
            ],
        ]);
    }

    /** Kategori milik CMS, tidak dihapus */
    public function down(): void {}
};
