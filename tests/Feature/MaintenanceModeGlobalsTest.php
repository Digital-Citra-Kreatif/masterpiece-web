<?php

use Database\Seeders\MaintenanceModeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Support\Locales;

uses(RefreshDatabase::class);

test('maintenance globals save the content and can be switched off again', function () {
    $this->seed(MaintenanceModeSeeder::class);
    $set = GlobalSet::query()->where('handle', 'maintenance_mode')->firstOrFail();
    $locale = Locales::default();
    expect($set->data($locale)['active'])->toBeFalse();

    app(SaveGlobals::class)->handle($set, [
        'heading' => 'Pemeliharaan website',
        'description' => 'Website sedang diperbarui.',
        'date' => '2026-11-01T09:00:00',
        'active' => true,
    ], $locale);

    expect($set->fresh()->data($locale))->toMatchArray([
        'heading' => 'Pemeliharaan website',
        'description' => 'Website sedang diperbarui.',
        'date' => '2026-11-01T09:00:00',
        'active' => true,
    ]);

    app(SaveGlobals::class)->handle($set, ['active' => false], $locale);

    expect($set->fresh()->data($locale))->toMatchArray([
        'heading' => 'Pemeliharaan website',
        'date' => '2026-11-01T09:00:00',
        'active' => false,
    ]);
});

test('seeding maintenance globals again preserves saved content and activation', function () {
    $this->seed(MaintenanceModeSeeder::class);
    $set = GlobalSet::query()->where('handle', 'maintenance_mode')->firstOrFail();
    $values = [
        'heading' => 'Pembaruan terjadwal',
        'description' => 'Silakan kembali setelah pembaruan selesai.',
        'date' => '2026-11-02T08:30:00',
        'active' => true,
    ];
    app(SaveGlobals::class)->handle($set, $values, Locales::default());

    $this->seed(MaintenanceModeSeeder::class);

    expect($set->fresh()->data(Locales::default()))->toMatchArray($values);
    $this->assertDatabaseCount('sunrice_global_sets', 1);
});

test('maintenance globals reject an invalid date without saving the activation', function () {
    $this->seed(MaintenanceModeSeeder::class);
    $set = GlobalSet::query()->where('handle', 'maintenance_mode')->firstOrFail();

    try {
        app(SaveGlobals::class)->handle($set, ['date' => 'invalid-date', 'active' => true], Locales::default());
        $this->fail('An invalid date should be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.date');
    }

    expect($set->fresh()->data(Locales::default())['active'])->toBeFalse();
});
