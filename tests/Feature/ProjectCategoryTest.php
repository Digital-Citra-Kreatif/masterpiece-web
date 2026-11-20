<?php

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Structure\StructureImporter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    app(StructureImporter::class)->import(['terms' => [
        ['taxonomy' => 'project_categories', 'slug' => 'residential', 'translations' => ['id' => ['title' => 'Residential', 'slug' => 'residential']]],
    ]]);
});

/**
 * Proyek berkategori
 *
 * @param  string|list<string>|null  $category
 */
function publishCategorizedProject(string $title, string|array|null $category): void
{
    $entry = app(CreateEntry::class)->handle(Collection::query()->where('handle', 'projects')->firstOrFail(), [
        'title' => $title,
        'slug' => str($title)->slug()->toString(),
        'locale' => 'id',
        'data' => [
            'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
            'hero' => ['heading' => $title],
        ],
        'terms' => ['project_categories' => $category],
    ]);
    app(PublishEntry::class)->handle($entry->fresh());
}

test('the homepage groups project cards under each category in CMS order', function () {
    publishCategorizedProject('Westlake', 'commercial');
    publishCategorizedProject('Green Park', 'residential');
    publishCategorizedProject('Tanpa Kategori', null);
    publishCategorizedProject('Lake Plaza', 'commercial');

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder([
            '<h3 class="prj__cat">Commercial</h3>', '<h4 class="prj__title">Westlake</h4>', '<h4 class="prj__title">Lake Plaza</h4>',
            '<h3 class="prj__cat">Residential</h3>', '<h4 class="prj__title">Green Park</h4>',
            '<h3 class="prj__cat">Lainnya</h3>', '<h4 class="prj__title">Tanpa Kategori</h4>',
        ], false);
});

test('a category without projects is not shown', function () {
    publishCategorizedProject('Westlake', 'commercial');

    $this->get('/')
        ->assertOk()
        ->assertSee('<h3 class="prj__cat">Commercial</h3>', false)
        ->assertDontSee('Residential')
        ->assertDontSee('Lainnya');
});

test('projects without any category show without a category heading', function () {
    publishCategorizedProject('Westlake', null);

    $this->get('/')
        ->assertOk()
        ->assertSee('<h3 class="prj__title">Westlake</h3>', false)
        ->assertDontSee('prj__cat', false);
});

test('a project in two categories shows under both', function () {
    publishCategorizedProject('Westlake', ['commercial', 'residential']);
    publishCategorizedProject('Green Park', 'residential');

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder([
            '<h3 class="prj__cat">Commercial</h3>', '<h4 class="prj__title">Westlake</h4>',
            '<h3 class="prj__cat">Residential</h3>', '<h4 class="prj__title">Westlake</h4>', '<h4 class="prj__title">Green Park</h4>',
        ], false)
        ->assertDontSee('Lainnya');
});

test('project categories allow more than one term in the CMS', function () {
    expect(DB::table('sunrice_collection_taxonomy')->value('single'))->toBe(0);
});
