<?php

use App\Support\ProjectPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Isi background bawaan */
return new class extends Migration
{
    public function up(): void
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
                if (! is_array($data)) {
                    continue;
                }
                $filled = $data;
                foreach (ProjectPage::BACKGROUND_SECTIONS as $handle) {
                    $section = is_array($filled[$handle] ?? null) ? $filled[$handle] : [];
                    $background = is_array($section['background'] ?? null) ? $section['background'] : [];
                    // Kosong jadi bawaan
                    if (blank($background['type'] ?? null)) {
                        $section['background'] = ['type' => 'default', ...array_diff_key($background, ['type' => true])];
                        $filled[$handle] = $section;
                    }
                }
                if ($filled !== $data) {
                    $update[$column] = json_encode($filled);
                }
            }
            if ($update !== []) {
                DB::table('sunrice_entry_translations')->where('id', $row->id)->update($update);
            }
        }
    }

    /** Tidak dikembalikan */
    public function down(): void {}
};
