<?php

use App\Support\ProjectPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Background per section */
return new class extends Migration
{
    /** Section putih lama */
    private const FROM_PAGE = ['location', 'facilities', 'types'];

    public function up(): void
    {
        // Data ke section
        $this->each(function (array $data): array {
            if (! array_key_exists('page_background', $data)) {
                return $data;
            }
            $page = is_array($data['page_background']) ? $data['page_background'] : [];
            if (in_array($page['type'] ?? 'default', ['color', 'image'], true)) {
                foreach (self::FROM_PAGE as $handle) {
                    $section = is_array($data[$handle] ?? null) ? $data[$handle] : [];
                    // Isian section menang
                    if (in_array($section['background']['type'] ?? 'default', ['default', null, ''], true)) {
                        $section['background'] = $page;
                    }
                    $data[$handle] = $section;
                }
            }
            unset($data['page_background']);

            return $data;
        });

        // Field ke blueprint
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'project')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }
        $fields = array_values(array_filter($fields, fn (array $field): bool => ($field['handle'] ?? null) !== 'page_background'));
        foreach ($fields as &$group) {
            if (in_array($group['handle'] ?? null, ProjectPage::BACKGROUND_SECTIONS, true)
                && ! in_array('background', array_column($group['fields'] ?? [], 'handle'), true)) {
                $group['fields'][] = ProjectPage::backgroundField();
            }
        }
        unset($group);

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($fields)]);
        // Segarkan cache
        app(SiteCache::class)->changed('blueprint proyek background section');
    }

    /** Tidak dikembalikan */
    public function down(): void {}

    /**
     * Ubah terjemahan proyek
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function each(callable $change): void
    {
        $rows = DB::table('sunrice_entry_translations as t')
            ->join('sunrice_entries as e', 'e.id', '=', 't.entry_id')
            ->join('sunrice_collections as c', 'c.id', '=', 'e.collection_id')
            ->where('c.handle', 'projects')
            ->get(['t.id', 't.data', 't.draft_data']);

        foreach ($rows as $row) {
            $update = [];
            foreach (['data', 'draft_data'] as $column) {
                $data = json_decode((string) $row->{$column}, true);
                // Tulis bila berubah
                if (is_array($data) && ($changed = $change($data)) !== $data) {
                    $update[$column] = json_encode($changed);
                }
            }
            if ($update !== []) {
                DB::table('sunrice_entry_translations')->where('id', $row->id)->update($update);
            }
        }
    }
};
