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

    $this->project = publish(app(SaveDraft::class)->handle($this->project, ['version' => $this->project->version, 'data' => ['availability' => [
        'siteplan' => $siteplan,
        'points' => [['unit' => $this->placed->entry_id, 'x' => 10, 'y' => 20]],
    ]]]));
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

    return publish($translation->fresh());
}

function publish(EntryTranslation $translation): EntryTranslation
{
    app(PublishEntry::class)->handle($translation);

    return $translation->fresh();
}

test('the unit points page shows the project\'s siteplan, its units and their points', function () {
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

test('opened from a project\'s editor, the page shows that project', function () {
    $this->actingAs($this->admin)
        ->withHeader('referer', url('/admin/cms/collections/projects/entries/'.$this->other->entry_id.'/edit'))
        ->get('/admin/unit-points')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('project.id', $this->other->entry_id));
});

test('saving points stores them in the project and publishes it', function () {
    $this->actingAs($this->admin)
        ->put('/admin/unit-points/'.$this->project->entry_id, ['points' => [
            ['id' => $this->unplaced->entry_id, 'x' => 42.123, 'y' => 58.5],
        ]])
        ->assertRedirect()
        ->assertSessionHas('success', 'Titik unit disimpan dan tayang.');

    $points = collect($this->project->fresh()->data['availability']['points'])->keyBy('unit');
    expect($points)->toHaveCount(2)
        ->and($points[$this->placed->entry_id]['x'])->toEqual(10)
        ->and($points[$this->unplaced->entry_id]['x'])->toBe(42.12)
        ->and($points[$this->unplaced->entry_id]['y'])->toBe(58.5)
        ->and($this->project->fresh()->hasUnpublishedChanges())->toBeFalse();
});

test('a project with other unpublished changes keeps the points as a draft', function () {
    $project = app(SaveDraft::class)->handle($this->project, ['version' => $this->project->version, 'data' => ['whatsapp' => ['label' => 'Belum terbit', 'contacts' => []]]]);

    $this->actingAs($this->admin)
        ->put('/admin/unit-points/'.$project->entry_id, ['points' => [['id' => $this->placed->entry_id, 'x' => null, 'y' => null]]])
        ->assertSessionHas('success', 'Titik unit disimpan sebagai draf proyek. Terbitkan dari halaman proyek.');

    $project = $project->fresh();
    expect($project->draft_data['availability']['points'])->toBe([])
        ->and($project->data['availability']['points'])->toHaveCount(1);
});

test('points of another project\'s unit are refused', function () {
    $this->actingAs($this->admin)
        ->put('/admin/unit-points/'.$this->project->entry_id, ['points' => [
            ['id' => $this->foreign->entry_id, 'x' => 50, 'y' => 50],
        ]])
        ->assertStatus(422);
});

test('people without access to projects can\'t open or save the page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/unit-points')->assertForbidden();
    $this->actingAs($user)
        ->put('/admin/unit-points/'.$this->project->entry_id, ['points' => [['id' => $this->placed->entry_id, 'x' => 1, 'y' => 1]]])
        ->assertForbidden();
});
