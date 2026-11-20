<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Pindah background tema */
return new class extends Migration
{
    public function up(): void
    {
        // Data ke Background halaman
        $this->each(function (array $data): array {
            $style = is_array($data['global_style'] ?? null) ? $data['global_style'] : [];
            if (! array_key_exists('background', $style)) {
                return $data;
            }
            $page = is_array($data['page_background'] ?? null) ? $data['page_background'] : [];
            // Isian baru menang
            if (filled($style['background']) && in_array($page['type'] ?? 'default', ['default', null, ''], true)) {
                $data['page_background'] = [...$page, 'type' => 'image', 'image' => $style['background']];
            }
            unset($style['background']);
            $data['global_style'] = $style;

            return $data;
        });

        // Field dari Global Style
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'project')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }
        foreach ($fields as &$field) {
            if (($field['handle'] ?? null) === 'global_style') {
                $field['fields'] = array_values(array_filter($field['fields'] ?? [], fn (array $child): bool => ($child['handle'] ?? null) !== 'background'));
            }
        }
        unset($field);

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($fields)]);
        // Segarkan cache
        app(SiteCache::class)->changed('blueprint proyek tanpa background tema');
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
