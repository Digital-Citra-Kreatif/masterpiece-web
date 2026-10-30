<?php

use App\Http\Controllers\Admin\MediaPickerController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    app(SyncPermissions::class)->handle();

    $this->admin = User::factory()->create();
    $this->admin->assignRole(SyncPermissions::superAdminRole());
});

test('the media picker returns the chosen files for more than 100 ids, in their order', function () {
    $first = app(UploadMedia::class)->handle(UploadedFile::fake()->image('f_001.jpg', 40, 30), []);
    $second = app(UploadMedia::class)->handle(UploadedFile::fake()->image('f_002.jpg', 40, 30), []);
    // 150 id, sebagian tidak ada
    $ids = [$second->id, ...range(900001, 900148), $first->id];

    $response = $this->actingAs($this->admin)->getJson('/admin/media/picker?'.http_build_query(['ids' => $ids]));

    $response->assertOk();
    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$second->id, $first->id]);
});

test('the media picker still refuses more ids than its limit', function () {
    $ids = range(1, MediaPickerController::MAX_IDS + 1);

    $this->actingAs($this->admin)->getJson('/admin/media/picker?'.http_build_query(['ids' => $ids]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ids');
});

test('browsing a folder in the media picker still works', function () {
    app(UploadMedia::class)->handle(UploadedFile::fake()->image('denah.jpg', 40, 30), []);

    $this->actingAs($this->admin)->getJson('/admin/media/picker')->assertOk();
});

test('the media picker needs a signed-in user who may view media', function () {
    $this->getJson('/admin/media/picker?'.http_build_query(['ids' => [1]]))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson('/admin/media/picker?'.http_build_query(['ids' => [1]]))->assertForbidden();
});
