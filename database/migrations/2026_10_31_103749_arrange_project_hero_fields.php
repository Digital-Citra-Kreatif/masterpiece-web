<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Hero proyek: heading sebaris posisi, media sebaris video */
return new class extends Migration
{
    /** Urutan baru => lebar */
    private const LAYOUT = ['heading' => 50, 'align' => 50, 'media' => 33, 'video_file' => 33, 'video_url' => 33];

    /** Urutan dan lebar lama */
    private const BEFORE = ['heading' => null, 'media' => null, 'align' => null, 'video_file' => 50, 'video_url' => 50];

    public function up(): void
    {
        $this->arrange(self::LAYOUT);
    }

    public function down(): void
    {
        $this->arrange(self::BEFORE);
    }

    /**
     * Susun ulang field hero, field lain tetap di belakang
     *
     * @param  array<string, int|null>  $layout
     */
    private function arrange(array $layout): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'project')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        foreach ($fields as &$group) {
            if (($group['handle'] ?? null) !== 'hero' || ! is_array($group['fields'] ?? null)) {
                continue;
            }
            $byHandle = collect($group['fields'])->keyBy('handle');
            $arranged = [];
            foreach ($layout as $handle => $width) {
                if (! $byHandle->has($handle)) {
                    continue;
                }
                $field = $byHandle->pull($handle);
                if ($width === null) {
                    unset($field['width']);
                } else {
                    $field['width'] = $width;
                }
                $arranged[] = $field;
            }
            $group['fields'] = [...$arranged, ...$byHandle->values()->all()];
        }
        unset($group);

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($fields)]);
    }
};
