<?php

use App\Http\Middleware\AdminProjectMap;
use App\Models\UnitPoint;
use App\Models\User;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

test('the project form gets the first orbit frame of masterplan and cluster for placing points', function () {
    $this->actingAs($this->admin)
        ->getJson('/admin/orbit-frames')
        ->assertOk()
        ->assertJsonPath('masterplan.src', asset('media/scroll/kawasan/lg/f_000.jpg'))
        ->assertJsonPath('masterplan.width', 1280)
        ->assertJsonPath('cluster.height', 720);
});

test('the picker places points on the project\'s saved masterplan image, and on the orbit frame for a section without one', function () {
    $image = app(UploadMedia::class)->handle(UploadedFile::fake()->image('masterplan.jpg', 1600, 900), []);
    $project = publishedEntry('projects', 'Lakeside', ['masterplan' => ['image' => $image->id], 'whatsapp' => ['contacts' => []]]);

    $this->actingAs($this->admin)
        ->getJson('/admin/orbit-frames?project='.$project->entry_id)
        ->assertOk()
        ->assertJsonPath('masterplan.src', $image->url('large'))
        ->assertJsonPath('masterplan.width', 1600)
        ->assertJsonPath('masterplan.height', 900)
        ->assertJsonPath('cluster.src', asset('media/scroll/kawasan/lg/f_000.jpg'));
});

test('the type form gets each 360° tab\'s first frame in repeater order for placing its indicator', function () {
    $image = fn (string $name, int $width = 400, int $height = 300): int => app(UploadMedia::class)->handle(UploadedFile::fake()->image($name, $width, $height), [])->id;
    $type = EntryTranslation::query()->where('slug', 'tipe-1')->firstOrFail();
    $data = [...$type->data, 'turntable' => ['tabs' => [
        ['name' => 'Fasad', 'images' => [$image('b.jpg'), $image('a.jpg')]],
        ['name' => '', 'images' => [$image('lantai.jpg', 800, 600)]],
        ['name' => 'Atap', 'images' => []],
    ]]];
    $type->update(['data' => $data, 'draft_data' => $data]);

    $frames = $this->actingAs($this->admin)
        ->getJson('/admin/type-frames?type='.$type->entry_id)
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonPath('1.width', 800)
        ->assertJsonPath('1.height', 600)
        ->json();

    // Urut nama file; tab kosong pakai gambar tab pertama di sudutnya sendiri
    expect($frames[0]['src'])->toContain('-a.jpg')
        ->and($frames[2]['src'])->toContain('-b.jpg');
});

test('guests cannot read the type frames', function () {
    $this->getJson('/admin/type-frames')->assertUnauthorized();
});

test('guests cannot read the orbit frames', function () {
    $this->getJson('/admin/orbit-frames')->assertUnauthorized();
});
