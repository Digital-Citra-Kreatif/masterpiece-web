<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Posisi X/Y indikator per tab 360° Tipe */
return new class extends Migration
{
    private const AXES = ['x', 'y'];

    public function up(): void
    {
        $this->tabs(function (array $fields): array {
            $handles = array_column($fields, 'handle');
            foreach (self::AXES as $axis) {
                if (! in_array($axis, $handles, true)) {
                    $fields[] = [
                        'handle' => $axis,
                        'type' => 'number',
                        'label' => 'Posisi '.strtoupper($axis).' (%)',
                        'required' => false,
                        'instructions' => '',
                        'translatable' => false,
                        'validation' => [],
                        'config' => ['min' => 0, 'max' => 100],
                        'fields' => [],
                        'width' => 50,
                    ];
                }
            }

            return $fields;
        });
    }

    public function down(): void
    {
        $this->tabs(fn (array $fields): array => array_values(array_filter($fields, fn (array $field): bool => ! in_array($field['handle'] ?? null, self::AXES, true))));
    }

    /**
     * Ubah field repeater `turntable.tabs` blueprint Tipe
     *
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    private function tabs(callable $change): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', 'tipe')->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        foreach ($fields as &$group) {
            if (($group['handle'] ?? null) !== 'turntable') {
                continue;
            }
            foreach ($group['fields'] as &$repeater) {
                if (($repeater['handle'] ?? null) === 'tabs') {
                    $repeater['fields'] = $change($repeater['fields'] ?? []);
                }
            }
        }
        unset($group, $repeater);

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($fields)]);
    }
};
