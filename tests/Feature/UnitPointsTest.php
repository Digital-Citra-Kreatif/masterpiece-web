<?php

use App\Models\User;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    app(SyncPermissions::class)->handle();

    $this->admin = User::factory()->create();
    $this->admin->assignRole(SyncPermissions::superAdminRole());

    $siteplan = app(UploadMedia::class)->handle(UploadedFile::fake()->image('siteplan.jpg', 400, 300), [])->id;
    $this->project = publishedEntry('projects', 'Westlake', ['availability' => ['siteplan' => $siteplan], 'whatsapp' => ['contacts' => []]]);
    $this->other = publishedEntry('projects', 'Lain', ['whatsapp' => ['contacts' => []]]);
    $this->placed = publishedEntry('units', 'A-1', ['project' => $this->project->entry_id, 'sale_status' => 'sold', 'x' => 10, 'y' => 20]);
    $this->unplaced = publishedEntry('units', 'A-2', ['project' => $this->project->entry_id, 'sale_status' => 'available', 'x' => null, 'y' => null]);
    $this->foreign = publishedEntry('units', 'Z-9', ['project' => $this->other->entry_id, 'sale_status' => 'reserved', 'x' => 5, 'y' => 5]);
});

/**
 * Entri terbit
 *
 * @param  array<string, mixed>  $data
 */
function publishedEntry(string $collection, string $title, array $data): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle(Collection::query()->where('handle', $collection)->firstOrFail(), [
        'title' => $title, 'slug' => str($title)->slug()->toString(), 'locale' => 'id', 'data' => $data,
    ]);
    app(PublishEntry::class)->handle($translation->fresh());

    return $translation->fresh();
}

test('the unit points page shows the chosen project\'s siteplan and only its units', function () {
    $this->actingAs($this->admin)
        ->get('/admin/unit-points?project='.$this->project->entry_id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('App/UnitPoints', false)
            ->where('project.id', $this->project->entry_id)
            ->where('project.siteplan.width', 400)
            ->has('projects', 2)
            ->has('units', 2)
            ->where('units.0.code', 'A-1')
            ->where('units.0.x', 10)
            ->where('units.1.code', 'A-2')
            ->where('units.1.x', null)
            ->where('canUpdate', true));
});

test('saving points moves the project\'s units and publishes them', function () {
    $this->actingAs($this->admin)
        ->put('/admin/unit-points/'.$this->project->entry_id, ['points' => [
            ['id' => $this->placed->entry_id, 'x' => 10, 'y' => 20],
            ['id' => $this->unplaced->entry_id, 'x' => 42.123, 'y' => 58.5],
        ]])
        ->assertRedirect()
        ->assertSessionHas('success', '1 titik unit disimpan.');

    $unit = $this->unplaced->fresh();
    expect($unit->data['x'])->toBe(42.12)
        ->and($unit->data['y'])->toBe(58.5)
        ->and($unit->hasUnpublishedChanges())->toBeFalse();
});

test('points of another project\'s unit are refused', function () {
    $this->actingAs($this->admin)
        ->put('/admin/unit-points/'.$this->project->entry_id, ['points' => [
            ['id' => $this->foreign->entry_id, 'x' => 50, 'y' => 50],
        ]])
        ->assertStatus(422);

    expect($this->foreign->fresh()->data['x'])->toBe(5);
});

test('people without access to units can\'t open or save the page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/unit-points')->assertForbidden();
    $this->actingAs($user)
        ->put('/admin/unit-points/'.$this->project->entry_id, ['points' => [['id' => $this->placed->entry_id, 'x' => 1, 'y' => 1]]])
        ->assertForbidden();
});
