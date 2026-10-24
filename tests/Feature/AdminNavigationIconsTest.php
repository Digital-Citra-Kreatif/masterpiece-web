<?php

use App\Models\User;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;

uses(RefreshDatabase::class);

test('the site\'s collections have their own icons in the admin sidebar', function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    app(SyncPermissions::class)->handle();
    $admin = User::factory()->create();
    $admin->assignRole(SyncPermissions::superAdminRole());

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $icons = collect($page->toArray()['props']['navigation'])
                ->flatMap(fn (array $group): array => $group['items'])
                ->pluck('icon', 'key');

            expect($icons['cms-collection-types'])->toBe('home')
                ->and($icons['cms-collection-facilities'])->toBe('map-pin')
                ->and($icons['cms-collection-units'])->toBe('clipboard-list')
                ->and($icons['cms-collection-pages'])->toBe('file-text');
        });
});
