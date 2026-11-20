<?php

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
});

/**
 * Proyek data lama
 *
 * @param  array<string, mixed>  $data
 */
function oldThemeProject(string $slug, array $data): EntryTranslation
{
    $entry = app(CreateEntry::class)->handle(Collection::query()->where('handle', 'projects')->firstOrFail(), ['title' => $slug, 'slug' => $slug, 'locale' => 'id', 'data' => []]);
    DB::table('sunrice_entry_translations')->where('id', $entry->id)->update(['data' => json_encode($data)]);

    return $entry;
}

function runThemeBackgroundMigration(): void
{
    (require database_path('migrations/2026_11_20_073457_move_theme_background_to_page_background.php'))->up();
}

test('an old theme background moves into the page background image', function () {
    $image = app(UploadMedia::class)->handle(UploadedFile::fake()->image('tema.jpg', 40, 30), []);
    $entry = oldThemeProject('lama', ['global_style' => ['active' => true, 'background' => $image->id]]);

    runThemeBackgroundMigration();

    $data = json_decode(DB::table('sunrice_entry_translations')->where('id', $entry->id)->value('data'), true);
    expect($data['page_background'])->toBe(['type' => 'image', 'image' => $image->id])
        ->and($data['global_style'])->not->toHaveKey('background')
        ->and($data['global_style']['active'])->toBeTrue();
});

test('a page background already chosen is kept over the old theme background', function () {
    $entry = oldThemeProject('baru', ['global_style' => ['background' => 99], 'page_background' => ['type' => 'color', 'color' => '#F3EFE6']]);

    runThemeBackgroundMigration();

    $data = json_decode(DB::table('sunrice_entry_translations')->where('id', $entry->id)->value('data'), true);
    expect($data['page_background'])->toBe(['type' => 'color', 'color' => '#F3EFE6'])
        ->and($data['global_style'])->not->toHaveKey('background');
});

test('the theme background field is gone from Global Style', function () {
    runThemeBackgroundMigration();

    $style = collect(Blueprint::query()->where('handle', 'project')->firstOrFail()->fields)->firstWhere('handle', 'global_style');
    expect(array_column($style['fields'], 'handle'))->not->toContain('background');
});
