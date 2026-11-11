<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Support\SiteCache;

/** Footer per proyek, tanpa WhatsApp footer */
return new class extends Migration
{
    private const WHATSAPP = [
        'handle' => 'whatsapp',
        'type' => 'text',
        'label' => 'WhatsApp',
        'required' => false,
        'instructions' => 'Contoh: 6281234567890',
        'translatable' => false,
        'validation' => ['regex:/^[0-9]{8,15}$/'],
        'config' => [],
        'fields' => [],
        'width' => 33,
    ];

    public function up(): void
    {
        $this->fields('project', function (array $fields): array {
            if (in_array('footer', array_column($fields, 'handle'), true)) {
                return $fields;
            }
            // Setelah WhatsApp Floating
            $at = array_search('whatsapp', array_column($fields, 'handle'), true);
            array_splice($fields, $at === false ? count($fields) : $at + 1, 0, [$this->footer()]);

            return $fields;
        });
        $this->fields('footer', fn (array $fields): array => array_values(array_filter($fields, fn (array $field): bool => ($field['handle'] ?? null) !== 'whatsapp')));
    }

    public function down(): void
    {
        $this->fields('project', fn (array $fields): array => array_values(array_filter($fields, fn (array $field): bool => ($field['handle'] ?? null) !== 'footer')));
        $this->fields('footer', fn (array $fields): array => in_array('whatsapp', array_column($fields, 'handle'), true) ? $fields : [...$fields, self::WHATSAPP]);
    }

    /**
     * Grup footer proyek
     *
     * @return array<string, mixed>
     */
    private function footer(): array
    {
        $field = fn (string $handle, string $type, string $label, array $extra = []): array => [
            'handle' => $handle,
            'type' => $type,
            'label' => $label,
            'required' => false,
            'instructions' => '',
            'translatable' => null,
            'validation' => [],
            'config' => [],
            'fields' => [],
            ...$extra,
        ];

        return $field('footer', 'group', 'Footer', [
            'instructions' => 'Kosong: mengikuti Globals → Footer.',
            'fields' => [
                $field('description', 'textarea', 'Deskripsi singkat'),
                $field('address', 'textarea', 'Alamat', ['width' => 50]),
                $field('hours', 'textarea', 'Jam operasional', ['width' => 50, 'instructions' => 'Satu baris per jadwal, mis. Senin–Jumat, 09.00–17.00.']),
                $field('phone', 'text', 'Telepon', ['translatable' => false, 'width' => 50]),
                $field('email', 'text', 'Email', ['translatable' => false, 'width' => 50, 'validation' => ['email']]),
            ],
        ]);
    }

    /**
     * Ubah field blueprint
     *
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    private function fields(string $handle, callable $change): void
    {
        $blueprint = DB::table('sunrice_blueprints')->where('handle', $handle)->first(['id', 'fields']);
        $fields = json_decode((string) $blueprint?->fields, true);
        if (! is_array($fields)) {
            return;
        }

        DB::table('sunrice_blueprints')->where('id', $blueprint->id)->update(['fields' => json_encode($change($fields))]);
        // Segarkan cache
        app(SiteCache::class)->changed("blueprint {$handle} footer");
    }
};
