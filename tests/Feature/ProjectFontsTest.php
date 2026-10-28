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

function uploadFont(string $name): int
{
    return app(UploadMedia::class)->handle(UploadedFile::fake()->createWithContent($name, 'wOF2font'), [])->id;
}

test('a project\'s font files move to the media folder Fonts / {slug}', function () {
    $heading = uploadFont('heading.woff2');
    $button = uploadFont('button.woff2');

    $translation = app(CreateEntry::class)->handle($this->projects, [
        'title' => 'Project One', 'slug' => 'project-one', 'locale' => 'id',
        'data' => ['global_style' => ['active' => true, 'marker' => ['type' => 'shape', 'shape' => 'circle'], 'fonts' => ['heading' => $heading, 'button' => $button]]],
    ]);

    $root = MediaFolder::query()->whereNull('parent_id')->where('name', 'Fonts')->firstOrFail();
    $folder = MediaFolder::query()->where('parent_id', $root->id)->where('name', 'project-one')->firstOrFail();

    expect($folder->media()->pluck('id')->sort()->values()->all())->toBe([$heading, $button]);

    // Simpan draft admin
    app(SyncPermissions::class)->handle();
    $admin = User::factory()->create();
    $admin->assignRole(SyncPermissions::superAdminRole());
    $description = uploadFont('description.woff2');

    $this->actingAs($admin)->put(route('sunrice.admin.cms.entries.update', $translation->entry), [
        'locale' => 'id',
        'version' => $translation->fresh()->version,
        'title' => 'Project One',
        'slug' => 'project-one',
        'data' => ['global_style' => ['active' => true, 'marker' => ['type' => 'shape', 'shape' => 'circle'], 'fonts' => ['heading' => $heading, 'description' => $description, 'button' => $button]]],
    ])->assertSessionHasNoErrors();

    expect($folder->media()->count())->toBe(3)
        ->and(MediaFolder::query()->where('name', 'Fonts')->count())->toBe(1);
});

test('fonts of different projects get their own folders, other media stays put', function () {
    $first = uploadFont('first.woff2');
    $second = uploadFont('second.woff2');
    $other = uploadFont('other.woff2');

    app(CreateEntry::class)->handle($this->projects, ['title' => 'A', 'slug' => 'project-a', 'locale' => 'id', 'data' => ['global_style' => ['active' => true, 'marker' => ['type' => 'shape', 'shape' => 'circle'], 'fonts' => ['heading' => $first]]]]);
    app(CreateEntry::class)->handle($this->projects, ['title' => 'B', 'slug' => 'project-b', 'locale' => 'id', 'data' => ['global_style' => ['active' => true, 'marker' => ['type' => 'shape', 'shape' => 'circle'], 'fonts' => ['heading' => $second]]]]);

    expect(MediaFolder::query()->where('name', 'project-a')->firstOrFail()->media()->pluck('id')->all())->toBe([$first])
        ->and(MediaFolder::query()->where('name', 'project-b')->firstOrFail()->media()->pluck('id')->all())->toBe([$second])
        ->and(Media::find($other)->folder_id)->toBeNull();
});

test('a ZIP in a font field is unpacked: the regular font takes its place, the others wait in the folder', function () {
    $zipPath = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    $zip->addFromString('Inter/Inter-Regular.ttf', 'ttf-regular');
    $zip->addFromString('Inter/static/Inter-Bold.woff2', 'wOF2bold');
    $zip->addFromString('Inter/OFL.txt', 'licence');
    $zip->addFromString('__MACOSX/Inter/._Inter-Regular.ttf', 'resource fork');
    $zip->close();
    $zipId = app(UploadMedia::class)->handle(new UploadedFile($zipPath, 'Inter.zip', null, null, true), [])->id;

    $translation = app(CreateEntry::class)->handle($this->projects, [
        'title' => 'Project Zip', 'slug' => 'project-zip', 'locale' => 'id',
        'data' => ['global_style' => ['active' => true, 'marker' => ['type' => 'shape', 'shape' => 'circle'], 'fonts' => ['heading' => $zipId]]],
    ]);

    $heading = $translation->fresh()->draft_data['global_style']['fonts']['heading'];
    $folder = MediaFolder::query()->where('name', 'project-zip')->firstOrFail();

    expect(Media::find($heading)->filename)->toBe('Inter-Regular.ttf')
        ->and($folder->media()->pluck('filename')->sort()->values()->all())->toBe(['Inter-Bold.woff2', 'Inter-Regular.ttf'])
        ->and(Media::withTrashed()->find($zipId))->toBeNull()
        ->and(Media::query()->where('filename', 'OFL.txt')->exists())->toBeFalse();
});

test('changing a project\'s slug renames its font folder instead of making a second one', function () {
    $heading = uploadFont('heading.woff2');
    $translation = app(CreateEntry::class)->handle($this->projects, [
        'title' => 'Old', 'slug' => 'old-name', 'locale' => 'id',
        'data' => ['global_style' => ['active' => true, 'marker' => ['type' => 'shape', 'shape' => 'circle'], 'fonts' => ['heading' => $heading]]],
    ]);
    $folder = MediaFolder::query()->where('name', 'old-name')->firstOrFail();

    $translation->fresh()->forceFill(['draft_slug' => 'new-name'])->save();

    expect($folder->fresh()->name)->toBe('new-name')
        ->and(MediaFolder::query()->where('parent_id', $folder->parent_id)->pluck('name')->all())->toBe(['new-name'])
        ->and(Media::find($heading)->folder_id)->toBe($folder->id);
});

test('renaming in the admin while swapping the font still keeps one folder, renamed', function () {
    $old = uploadFont('old.woff2');
    $translation = app(CreateEntry::class)->handle($this->projects, [
        'title' => 'Ruko', 'slug' => 'ruko', 'locale' => 'id',
        'data' => ['global_style' => ['active' => true, 'marker' => ['type' => 'shape', 'shape' => 'circle'], 'fonts' => ['heading' => $old]]],
    ]);
    app(PublishEntry::class)->handle($translation->fresh());
    $folder = MediaFolder::query()->where('name', 'ruko')->firstOrFail();

    app(SyncPermissions::class)->handle();
    $admin = User::factory()->create();
    $admin->assignRole(SyncPermissions::superAdminRole());
    $new = uploadFont('new.woff2');

    $this->actingAs($admin)->put(route('sunrice.admin.cms.entries.update', $translation->entry), [
        'locale' => 'id',
        'version' => $translation->fresh()->version,
        'title' => 'Ruko 2',
        'slug' => 'ruko-2',
        'data' => ['global_style' => ['active' => true, 'marker' => ['type' => 'shape', 'shape' => 'circle'], 'fonts' => ['heading' => $new]]],
        'publish' => true,
    ])->assertSessionHasNoErrors();

    expect(MediaFolder::query()->where('parent_id', $folder->parent_id)->pluck('name')->all())->toBe(['ruko-2'])
        ->and($folder->fresh()->name)->toBe('ruko-2')
        ->and($folder->media()->pluck('filename')->sort()->values()->all())->toBe(['new.woff2', 'old.woff2']);
});

test('a font picked from another project\'s folder stays there, and that folder keeps its name', function () {
    $shared = uploadFont('shared.woff2');
    app(CreateEntry::class)->handle($this->projects, ['title' => 'A', 'slug' => 'project-a', 'locale' => 'id', 'data' => ['global_style' => ['active' => true, 'marker' => ['type' => 'shape', 'shape' => 'circle'], 'fonts' => ['heading' => $shared]]]]);
    $folderA = MediaFolder::query()->where('name', 'project-a')->firstOrFail();

    app(CreateEntry::class)->handle($this->projects, ['title' => 'B', 'slug' => 'project-b', 'locale' => 'id', 'data' => ['global_style' => ['active' => true, 'marker' => ['type' => 'shape', 'shape' => 'circle'], 'fonts' => ['heading' => $shared]]]]);

    expect($folderA->fresh()->name)->toBe('project-a')
        ->and(Media::find($shared)->folder_id)->toBe($folderA->id);
});
