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
 * Simpan tombol ke atas di Globals → Situs
 */
function saveScrollTop(bool $active): void
{
    app(SaveGlobals::class)->handle(GlobalSet::query()->where('handle', 'situs')->firstOrFail(), [
        'status_labels' => ['sold' => 'Terjual', 'reserved' => 'Dipesan', 'available' => 'Tersedia'],
        'scroll_top_active' => $active,
    ], 'id');
}

test('the scroll top button is hidden by default', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('data-to-top', false);
});

test('the scroll top button shows when switched on in Globals → Situs', function () {
    saveScrollTop(true);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-to-top', false);
});

test('a switched off scroll top button leaves no markup', function () {
    saveScrollTop(false);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('data-to-top', false);
});
