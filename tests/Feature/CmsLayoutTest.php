<?php

use App\Support\CmsLayout;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Sunrice\Cms\Models\Blueprint;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
});

/**
 * Field blueprint per jalur
 *
 * @return array<string, mixed>
 */
function blueprintField(string $blueprint, string $path): array
{
    $fields = Blueprint::query()->where('handle', $blueprint)->firstOrFail()->fields;
    foreach (explode('.', $path) as $handle) {
        $field = collect($fields)->firstWhere('handle', $handle);
        $fields = $field['fields'] ?? [];
    }

    return $field;
}

test('the seeded CMS forms get short labels, hints and tidy widths', function () {
    expect(blueprintField('tipe', 'label'))->toMatchArray(['label' => 'Label', 'instructions' => 'Contoh: Ruko Hook', 'width' => 50])
        ->and(blueprintField('tipe', 'turntable.tabs.zoom_image'))->toMatchArray(['instructions' => 'Tampil saat diklik', 'width' => 50])
        ->and(blueprintField('situs', 'status_labels.sold')['label'])->toBe('Terjual')
        ->and(blueprintField('footer', 'email')['width'])->toBe(50);
});

test('every CMS hint is three words or fewer', function () {
    foreach (CmsLayout::FIELDS as $paths) {
        foreach ($paths as $path => $set) {
            if (isset($set['instructions'])) {
                expect(count(preg_split('/\s+/', $set['instructions'])))->toBeLessThanOrEqual(3, $path);
            }
        }
    }
});

test('tidying the CMS forms keeps field handles, order and types', function () {
    $before = DB::table('sunrice_blueprints')->pluck('fields', 'handle')->map(fn (string $fields): array => json_decode($fields, true));
    $shape = function (array $fields) use (&$shape): array {
        return array_map(fn (array $field): array => [$field['handle'], $field['type'], $shape($field['fields'] ?? [])], $fields);
    };

    CmsLayout::apply();

    $after = DB::table('sunrice_blueprints')->pluck('fields', 'handle')->map(fn (string $fields): array => json_decode($fields, true));
    expect($after->map($shape)->all())->toBe($before->map($shape)->all())
        ->and($after->all())->toBe($before->all());
});
