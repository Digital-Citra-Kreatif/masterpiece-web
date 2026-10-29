<?php

use App\Models\User;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Models\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    $this->projects = Collection::query()->where('handle', 'projects')->firstOrFail();
});

function uploadImage(string $name): int
{
    return app(UploadMedia::class)->handle(UploadedFile::fake()->image($name, 40, 30), [])->id;
}

/**
 * Data section gambar
 *
 * @param  list<int|null>  $masterplanPoints
 * @param  list<int|null>  $clusterPoints
 * @return array<string, mixed>
 */
function imageSections(?int $masterplan = null, array $masterplanPoints = [], ?int $cluster = null, array $clusterPoints = []): array
{
    return [
        'masterplan' => ['image' => $masterplan, 'points' => collect($masterplanPoints)->map(fn (?int $image, int $index): array => [
            'number' => (string) ($index + 1), 'name' => 'Kawasan '.($index + 1), 'image' => $image, 'x' => null, 'y' => null,
        ])->all()],
        'cluster' => ['image' => $cluster, 'points' => collect($clusterPoints)->map(fn (?int $image, int $index): array => [
            'number' => (string) ($index + 1), 'name' => 'Cluster '.($index + 1), 'description' => '', 'image' => $image, 'x' => null, 'y' => null,
        ])->all()],
    ];
}

function imageFolder(string $root, string $slug): MediaFolder
{
    $parent = MediaFolder::query()->whereNull('parent_id')->where('name', $root)->firstOrFail();

    return MediaFolder::query()->where('parent_id', $parent->id)->where('name', $slug)->firstOrFail();
}

test('masterplan and cluster images move to Masterplan / {slug} and Cluster / {slug}', function () {
    $masterplan = uploadImage('masterplan.jpg');
    $area = uploadImage('area.jpg');
    $cluster = uploadImage('cluster.jpg');
    $other = uploadImage('other.jpg');

    $translation = app(CreateEntry::class)->handle($this->projects, [
        'title' => 'Project One', 'slug' => 'project-one', 'locale' => 'id',
        'data' => imageSections($masterplan, [$area], $cluster),
    ]);

    expect(imageFolder('Masterplan', 'project-one')->media()->pluck('id')->sort()->values()->all())->toBe([$masterplan, $area])
        ->and(imageFolder('Cluster', 'project-one')->media()->pluck('id')->all())->toBe([$cluster])
        ->and(Media::find($other)->folder_id)->toBeNull();

    // Simpan draft admin
    app(SyncPermissions::class)->handle();
    $admin = User::factory()->create();
    $admin->assignRole(SyncPermissions::superAdminRole());
    $house = uploadImage('house.jpg');

    $this->actingAs($admin)->put(route('sunrice.admin.cms.entries.update', $translation->entry), [
        'locale' => 'id',
        'version' => $translation->fresh()->version,
        'title' => 'Project One',
        'slug' => 'project-one',
        'data' => imageSections($masterplan, [$area], $cluster, [$house]),
    ])->assertSessionHasNoErrors();

    expect(imageFolder('Cluster', 'project-one')->media()->pluck('id')->sort()->values()->all())->toBe([$cluster, $house])
        ->and(MediaFolder::query()->where('name', 'Masterplan')->count())->toBe(1)
        ->and(MediaFolder::query()->where('name', 'Cluster')->count())->toBe(1);
});

test('a project without masterplan or cluster images creates no folders', function () {
    app(CreateEntry::class)->handle($this->projects, ['title' => 'Empty', 'slug' => 'empty', 'locale' => 'id', 'data' => imageSections()]);

    expect(MediaFolder::query()->whereIn('name', ['Masterplan', 'Cluster'])->exists())->toBeFalse();
});

test('an image picked from another project\'s folder stays there', function () {
    $shared = uploadImage('shared.jpg');
    app(CreateEntry::class)->handle($this->projects, ['title' => 'A', 'slug' => 'project-a', 'locale' => 'id', 'data' => imageSections($shared)]);
    $folderA = imageFolder('Masterplan', 'project-a');

    app(CreateEntry::class)->handle($this->projects, ['title' => 'B', 'slug' => 'project-b', 'locale' => 'id', 'data' => imageSections(cluster: $shared)]);

    expect($folderA->fresh()->name)->toBe('project-a')
        ->and(Media::find($shared)->folder_id)->toBe($folderA->id)
        ->and(MediaFolder::query()->where('name', 'Cluster')->exists())->toBeFalse();
});

test('changing a project\'s slug renames its image folders instead of making second ones', function () {
    $masterplan = uploadImage('masterplan.jpg');
    $cluster = uploadImage('cluster.jpg');
    $translation = app(CreateEntry::class)->handle($this->projects, [
        'title' => 'Old', 'slug' => 'old-name', 'locale' => 'id',
        'data' => imageSections($masterplan, cluster: $cluster),
    ]);
    $masterplanFolder = imageFolder('Masterplan', 'old-name');
    $clusterFolder = imageFolder('Cluster', 'old-name');

    $translation->fresh()->forceFill(['draft_slug' => 'new-name'])->save();

    expect($masterplanFolder->fresh()->name)->toBe('new-name')
        ->and($clusterFolder->fresh()->name)->toBe('new-name')
        ->and(MediaFolder::query()->where('parent_id', $masterplanFolder->parent_id)->pluck('name')->all())->toBe(['new-name'])
        ->and(MediaFolder::query()->where('parent_id', $clusterFolder->parent_id)->pluck('name')->all())->toBe(['new-name'])
        ->and(Media::find($masterplan)->folder_id)->toBe($masterplanFolder->id)
        ->and(Media::find($cluster)->folder_id)->toBe($clusterFolder->id);
});

test('renaming in the admin while swapping an image still keeps one folder, renamed', function () {
    $old = uploadImage('old.jpg');
    $translation = app(CreateEntry::class)->handle($this->projects, [
        'title' => 'Ruko', 'slug' => 'ruko', 'locale' => 'id',
        'data' => imageSections($old),
    ]);
    app(PublishEntry::class)->handle($translation->fresh());
    $folder = imageFolder('Masterplan', 'ruko');

    app(SyncPermissions::class)->handle();
    $admin = User::factory()->create();
    $admin->assignRole(SyncPermissions::superAdminRole());
    $new = uploadImage('new.jpg');

    $this->actingAs($admin)->put(route('sunrice.admin.cms.entries.update', $translation->entry), [
        'locale' => 'id',
        'version' => $translation->fresh()->version,
        'title' => 'Ruko 2',
        'slug' => 'ruko-2',
        'data' => imageSections($new),
        'publish' => true,
    ])->assertSessionHasNoErrors();

    expect(MediaFolder::query()->where('parent_id', $folder->parent_id)->pluck('name')->all())->toBe(['ruko-2'])
        ->and($folder->fresh()->name)->toBe('ruko-2')
        ->and($folder->media()->pluck('filename')->sort()->values()->all())->toBe(['new.jpg', 'old.jpg']);
});
