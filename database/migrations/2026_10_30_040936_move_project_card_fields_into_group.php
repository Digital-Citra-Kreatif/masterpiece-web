<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Kartu Beranda proyek jadi group `card` */
return new class extends Migration
{
    /** Lama ke baru: handle atas => handle di group */
    private const FIELDS = ['card_image' => 'image', 'card_description' => 'description'];

    public function up(): void
    {
        $this->each(function (array $data): array {
            $card = is_array($data['card'] ?? null) ? $data['card'] : [];
            foreach (self::FIELDS as $old => $new) {
                if (array_key_exists($old, $data)) {
                    $card[$new] ??= $data[$old];
                    unset($data[$old]);
                }
            }

            return $card === [] ? $data : ['card' => $card, ...$data];
        });
    }

    public function down(): void
    {
        $this->each(function (array $data): array {
            $card = is_array($data['card'] ?? null) ? $data['card'] : [];
            foreach (self::FIELDS as $old => $new) {
                if (array_key_exists($new, $card)) {
                    $data[$old] = $card[$new];
                }
            }
            unset($data['card']);

            return $data;
        });
    }

    /**
     * Ubah data dan draf tiap terjemahan proyek
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
                if (is_array($data)) {
                    $update[$column] = json_encode($change($data));
                }
            }
            if ($update !== []) {
                DB::table('sunrice_entry_translations')->where('id', $row->id)->update($update);
            }
        }
    }
};
