<?php

use App\Http\Middleware\AdminProjectMap;
use App\Models\UnitPoint;
use App\Models\User;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
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
    $this->placed = publishedEntry('units', 'A-1', ['project' => $this->project->entry_id, 'sale_status' => 'sold']);
    $this->unplaced = publishedEntry('units', 'A-2', ['project' => $this->project->entry_id, 'sale_status' => 'available']);
    $this->foreign = publishedEntry('units', 'Z-9', ['project' => $this->other->entry_id, 'sale_status' => 'reserved']);
    UnitPoint::factory()->create(['project_id' => $this->project->entry_id, 'unit_id' => $this->placed->entry_id, 'x' => 10, 'y' => 20]);
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

test('the map gives the project\'s siteplan and its units with their points', function () {
    $this->actingAs($this->admin)
        ->getJson('/admin/unit-points/'.$this->project->entry_id)
        ->assertOk()
        ->assertJsonPath('siteplan.width', 400)
        ->assertJsonCount(2, 'units')
        ->assertJsonPath('units.0.code', 'A-1')
        ->assertJsonPath('units.0.status', 'sold')
        ->assertJsonPath('units.0.x', 10)
        ->assertJsonPath('units.1.code', 'A-2')
        ->assertJsonPath('units.1.x', null)
        ->assertJsonPath('canUpdate', true);
});

test('saving points places, moves and clears units of the project', function () {
    $this->actingAs($this->admin)
        ->putJson('/admin/unit-points/'.$this->project->entry_id, ['points' => [
            ['id' => $this->unplaced->entry_id, 'x' => 42.123, 'y' => 58.5],
            ['id' => $this->placed->entry_id, 'x' => null, 'y' => null],
        ]])
        ->assertOk()
        ->assertJsonPath('message', '2 titik unit disimpan.');

    expect(UnitPoint::query()->where('unit_id', $this->placed->entry_id)->exists())->toBeFalse();
    $point = UnitPoint::query()->where('unit_id', $this->unplaced->entry_id)->sole();
    expect($point->project_id)->toBe($this->project->entry_id)
        ->and($point->x)->toBe(42.12)
        ->and($point->y)->toBe(58.5);
});

test('points of another project\'s unit are refused', function () {
    $this->actingAs($this->admin)
        ->putJson('/admin/unit-points/'.$this->project->entry_id, ['points' => [
            ['id' => $this->unplaced->entry_id, 'x' => 1, 'y' => 1],
            ['id' => $this->foreign->entry_id, 'x' => 50, 'y' => 50],
        ]])
        ->assertStatus(422);

    expect(UnitPoint::query()->count())->toBe(1);
});

test('people without access to projects can\'t read or save the map', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/admin/unit-points/'.$this->project->entry_id)->assertForbidden();
    $this->actingAs($user)
        ->putJson('/admin/unit-points/'.$this->project->entry_id, ['points' => [['id' => $this->placed->entry_id, 'x' => 1, 'y' => 1]]])
        ->assertForbidden();
});

test('admin pages load the map script on full page loads only', function () {
    $script = asset(AdminProjectMap::SCRIPT);

    $this->actingAs($this->admin)->get('/admin/cms/collections/projects/entries/'.$this->project->entry_id.'/edit')
        ->assertOk()
        ->assertSee('<script type="module" src="'.$script, false);
    $this->actingAs($this->admin)->get('/admin', ['X-Inertia' => 'true'])
        ->assertDontSee($script, false);
});

test('a unit entry needs a title, on create and on save', function () {
    $units = Collection::query()->where('handle', 'units')->firstOrFail();
    $data = ['project' => $this->project->entry_id, 'sale_status' => 'available'];

    expect(fn () => app(CreateEntry::class)->handle($units, ['title' => '', 'locale' => 'id', 'data' => $data]))
        ->toThrow(ValidationException::class);
    expect(fn () => app(SaveDraft::class)->handle($this->placed->fresh(), ['title' => '  ', 'data' => $data, 'version' => $this->placed->fresh()->version]))
        ->toThrow(ValidationException::class);
});
