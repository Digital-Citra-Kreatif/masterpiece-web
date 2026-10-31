<?php

use App\Models\User;
use Carbon\Carbon;
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

test('an active maintenance mode shows its title, description and a countdown on every site page with a 503', function () {
    $this->travelTo(Carbon::parse('2026-11-01 10:00:00', 'Asia/Jakarta'));
    saveMaintenance(['heading' => 'Kami sedang berbenah', 'description' => 'Situs dibuka lagi segera.', 'date' => '2026-11-02T14:30', 'active' => true]);

    foreach (['/', '/projects/westlake', '/tidak-ada'] as $path) {
        $this->get($path)
            ->assertStatus(503)
            ->assertSee('<main class="nf nf--mt">', false)
            ->assertSee('<h1 class="nf__title">Kami sedang berbenah</h1>', false)
            ->assertSee('Situs dibuka lagi segera.')
            // 1 hari 4 jam 30 menit, jam CMS dalam WIB
            ->assertSee('aria-label="Dibuka kembali 2 November 2026 pukul 14.30 WIB" data-countdown="102600"', false)
            ->assertSeeInOrder(['>01<', 'Hari', '>04<', 'Jam', '>30<', 'Menit', '>00<', 'Detik'], false)
            ->assertDontSee('Perkiraan kembali')
            ->assertDontSee('<footer class="ft">', false);
    }
});

test('a return date without a time counts down to the start of that day', function () {
    $this->travelTo(Carbon::parse('2026-11-01 23:59:00', 'Asia/Jakarta'));
    saveMaintenance(['date' => '2026-11-02', 'active' => true]);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('aria-label="Dibuka kembali 2 November 2026" data-countdown="60"', false);
});

test('a return time that has passed leaves the countdown out', function () {
    $this->travelTo(Carbon::parse('2026-11-03 08:00:00', 'Asia/Jakarta'));
    saveMaintenance(['date' => '2026-11-02T14:30', 'active' => true]);

    $this->get('/')
        ->assertStatus(503)
        ->assertDontSee('data-countdown', false);
});

test('maintenance mode without a title or date falls back to a default title and leaves the date out', function () {
    saveMaintenance(['active' => true]);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('<h1 class="nf__title">Situs sedang dalam perbaikan</h1>', false)
        ->assertDontSee('data-countdown', false);
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
