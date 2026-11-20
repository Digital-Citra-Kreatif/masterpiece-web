<?php

use App\Support\ProjectPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Label background bawaan */
return new class extends Migration
{
    public function up(): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'project')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        foreach ($fields as &$group) {
            if (! in_array($group['handle'] ?? null, ProjectPage::BACKGROUND_SECTIONS, true)) {
                continue;
            }
            foreach ($group['fields'] as &$background) {
                if (($background['handle'] ?? null) !== 'background') {
                    continue;
                }
                foreach ($background['fields'] as &$type) {
                    if (($type['handle'] ?? null) === 'type') {
                        $type['config']['options'] = ProjectPage::SECTION_BACKGROUNDS;
                    }
                }
                unset($type);
            }
            unset($background);
        }
        unset($group);

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($fields)]);
        // Segarkan cache
        app(SiteCache::class)->changed('label background bawaan');
    }

    /** Tidak dikembalikan */
    public function down(): void {}
};
