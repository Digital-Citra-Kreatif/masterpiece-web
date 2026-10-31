<?php

use App\Models\User;
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
 * Simpan global Maintenance Mode
 *
 * @param  array<string, mixed>  $values
 */
function saveMaintenance(array $values): void
{
    app(SaveGlobals::class)->handle(GlobalSet::query()->where('handle', 'maintenance_mode')->firstOrFail(), $values, 'id');
}

test('an active maintenance mode shows its title, description and return date on every site page with a 503', function () {
    saveMaintenance(['heading' => 'Kami sedang berbenah', 'description' => 'Situs dibuka lagi segera.', 'date' => '2026-11-02', 'active' => true]);

    foreach (['/', '/projects/westlake', '/tidak-ada'] as $path) {
        $this->get($path)
            ->assertStatus(503)
            ->assertSee('<main class="nf nf--mt">', false)
            ->assertSee('<h1 class="nf__title">Kami sedang berbenah</h1>', false)
            ->assertSee('Situs dibuka lagi segera.')
            ->assertSee('<time datetime="2026-11-02">2 November 2026</time>', false)
            ->assertDontSee('<footer class="ft">', false);
    }
});

test('maintenance mode without a title or date falls back to a default title and leaves the date out', function () {
    saveMaintenance(['active' => true]);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('<h1 class="nf__title">Situs sedang dalam perbaikan</h1>', false)
        ->assertDontSee('nf__date', false);
});

test('the site opens normally while maintenance mode is off', function () {
    saveMaintenance(['heading' => 'Kami sedang berbenah', 'active' => false]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('nf--mt', false);
});

test('signed-in users and the admin panel are not blocked by maintenance mode', function () {
    saveMaintenance(['active' => true]);

    $this->get('/admin/login')->assertOk();
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertDontSee('nf--mt', false);
});
