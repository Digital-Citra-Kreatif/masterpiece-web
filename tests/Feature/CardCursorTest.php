<?php

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\GlobalSet;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);

    $project = app(CreateEntry::class)->handle(Collection::query()->where('handle', 'projects')->firstOrFail(), ['title' => 'Westlake', 'slug' => 'westlake', 'locale' => 'id', 'data' => [
        'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
        'hero' => ['heading' => 'Westlake'],
    ]]);
    app(PublishEntry::class)->handle($project->fresh());
});

/**
 * Simpan kursor kartu di Globals → Situs
 */
function saveCardCursor(string $cursor): void
{
    app(SaveGlobals::class)->handle(GlobalSet::query()->where('handle', 'situs')->firstOrFail(), [
        'status_labels' => ['sold' => 'Terjual', 'reserved' => 'Dipesan', 'available' => 'Tersedia'],
        'card_cursor' => $cursor,
    ], 'id');
}

test('project cards show a magnifier inside the view circle by default', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-card-cursor="zoom"', false)
        ->assertSee('data-view-cursor', false)
        ->assertSeeInOrder(['<span class="view-cursor view-cursor--icon" data-view-cursor-el aria-hidden="true">', '<svg', '</span>'], false)
        ->assertDontSee('Lihat');
});

test('project cards show the view circle when chosen in Globals → Situs', function () {
    saveCardCursor('view');

    $this->get('/')
        ->assertOk()
        ->assertSee('data-card-cursor="view"', false)
        ->assertSee('data-view-cursor', false)
        ->assertSeeInOrder(['<span class="view-cursor" data-view-cursor-el aria-hidden="true">', 'Lihat', '</span>'], false)
        ->assertDontSee('view-cursor--icon', false);
});

test('project cards keep the normal cursor when chosen in Globals → Situs', function () {
    saveCardCursor('default');

    $this->get('/')
        ->assertOk()
        ->assertSee('data-card-cursor="default"', false)
        ->assertDontSee('data-view-cursor', false);
});
