<?php

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Models\GlobalSet;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
});

/**
 * Simpan pengaturan preload di Globals → Situs
 *
 * @param  array<string, mixed>  $values
 */
function savePreload(array $values): void
{
    app(SaveGlobals::class)->handle(GlobalSet::query()->where('handle', 'situs')->firstOrFail(), [
        'status_labels' => ['sold' => 'Terjual', 'reserved' => 'Dipesan', 'available' => 'Tersedia'],
        ...$values,
    ], 'id');
}

test('the preload is on by default and shows again after 6 hours', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('localStorage.getItem(key)) || 0) < 6 * 3600000', false)
        ->assertSeeInOrder(['<body', '<div class="pl" aria-hidden="true" data-preload>', 'class="pl__house"', 'class="pl__line pl__line--right"'], false);
});

test('the preload interval comes from Globals → Situs', function () {
    savePreload(['preload_active' => true, 'preload_hours' => 12]);

    $this->get('/')
        ->assertOk()
        ->assertSee('< 12 * 3600000', false);
});

test('a switched off preload leaves no script or markup', function () {
    savePreload(['preload_active' => false, 'preload_hours' => 12]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('mp-preload', false)
        ->assertDontSee('data-preload', false);
});

test('the maintenance page has no preload', function () {
    app(SaveGlobals::class)->handle(GlobalSet::query()->where('handle', 'maintenance_mode')->firstOrFail(), ['active' => true], 'id');

    $this->get('/')
        ->assertStatus(503)
        ->assertDontSee('data-preload', false);
});
