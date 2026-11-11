<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Masterplan: kolase pembuka */
return new class extends Migration
{
    private const FIELD = [
        'handle' => 'collage',
        'type' => 'repeater',
        'label' => 'Gambar pembuka',
        'required' => false,
        'instructions' => 'Maks 4 gambar, tampil sebagai kolase sebelum masterplan.',
        'translatable' => null,
        'validation' => [],
        'config' => ['max' => 4],
        'fields' => [
            [
                'handle' => 'image',
                'type' => 'media',
                'label' => 'Gambar',
                'required' => true,
                'instructions' => '',
                'translatable' => false,
                'validation' => [],
                'config' => ['types' => ['image']],
                'fields' => [],
                'width' => 50,
            ],
            [
                'handle' => 'label',
                'type' => 'text',
                'label' => 'Label',
                'required' => false,
                'instructions' => '',
                'translatable' => null,
                'validation' => [],
                'config' => [],
                'fields' => [],
                'width' => 50,
            ],
        ],
    ];

    public function up(): void
    {
        $this->fields(function (array $fields): array {
            if (in_array(self::FIELD['handle'], array_column($fields, 'handle'), true)) {
                return $fields;
            }
            // Setelah gambar scene
            $at = array_search('image', array_column($fields, 'handle'), true);
            array_splice($fields, $at === false ? count($fields) : $at + 1, 0, [self::FIELD]);

            return $fields;
        });
    }

    public function down(): void
    {
        $this->fields(fn (array $fields): array => array_values(array_filter($fields, fn (array $field): bool => ($field['handle'] ?? null) !== self::FIELD['handle'])));
    }

    /**
     * Ubah field grup Masterplan
     *
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    private function fields(callable $change): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'project')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        foreach ($fields as &$group) {
            if (($group['handle'] ?? null) === 'masterplan') {
                $group['fields'] = $change($group['fields'] ?? []);
            }
        }
        unset($group);

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($fields)]);
        // Segarkan cache
        app(SiteCache::class)->changed('blueprint project kolase masterplan');
    }
};
