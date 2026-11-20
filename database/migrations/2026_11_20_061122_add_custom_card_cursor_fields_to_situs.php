<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Situs: kursor kartu custom */
return new class extends Migration
{
    private const CUSTOM = ['value' => 'custom', 'label' => 'Custom (ikon atau teks)'];

    private const WHEN_CUSTOM = ['field' => 'card_cursor', 'operator' => 'equals', 'value' => 'custom'];

    public function up(): void
    {
        $this->fields(function (array $fields): array {
            $handles = array_column($fields, 'handle');
            $at = array_search('card_cursor', $handles, true);
            if ($at === false) {
                return $fields;
            }

            // Pilihan custom
            $options = $fields[$at]['config']['options'] ?? [];
            if (! in_array('custom', array_column($options, 'value'), true)) {
                $fields[$at]['config']['options'] = [...$options, self::CUSTOM];
            }

            $added = array_values(array_filter([
                in_array('card_cursor_icon', $handles, true) ? null : [
                    'handle' => 'card_cursor_icon',
                    'type' => 'media',
                    'label' => 'Ikon kursor',
                    'required' => false,
                    'instructions' => 'SVG atau PNG transparan, tampil putih. Diisi: teks diabaikan.',
                    'translatable' => false,
                    'validation' => [],
                    'config' => ['types' => ['image']],
                    'fields' => [],
                    'condition' => self::WHEN_CUSTOM,
                    'width' => 50,
                ],
                in_array('card_cursor_text', $handles, true) ? null : [
                    'handle' => 'card_cursor_text',
                    'type' => 'text',
                    'label' => 'Teks kursor',
                    'required' => false,
                    'instructions' => 'Dipakai bila ikon kosong.',
                    'translatable' => false,
                    'validation' => [],
                    'config' => [],
                    'fields' => [],
                    'condition' => self::WHEN_CUSTOM,
                    'width' => 50,
                ],
            ]));
            array_splice($fields, $at + 1, 0, $added);

            return $fields;
        });
    }

    public function down(): void
    {
        $this->fields(function (array $fields): array {
            $fields = array_values(array_filter($fields, fn (array $field): bool => ! in_array($field['handle'] ?? null, ['card_cursor_icon', 'card_cursor_text'], true)));
            foreach ($fields as &$field) {
                if (($field['handle'] ?? null) === 'card_cursor') {
                    $field['config']['options'] = array_values(array_filter($field['config']['options'] ?? [], fn (array $option): bool => $option['value'] !== 'custom'));
                }
            }

            return $fields;
        });
    }

    /**
     * Ubah field blueprint Situs
     *
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    private function fields(callable $change): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'situs')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($change($fields))]);
        // Segarkan cache
        app(SiteCache::class)->changed('blueprint situs kursor kartu custom');
    }
};
