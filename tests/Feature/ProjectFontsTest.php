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
        'data' => ['global_style' => ['active' => true, 'font_heading' => $heading, 'font_button' => $button]],
    ]);

    $root = MediaFolder::query()->whereNull('parent_id')->where('name', 'Fonts')->firstOrFail();
    $folder = MediaFolder::query()->where('parent_id', $root->id)->where('name', 'project-one')->firstOrFail();

    expect($folder->media()->pluck('id')->sort()->values()->all())->toBe([$heading, $button]);

    // Saving a draft in the admin, as an editor does.
    app(SyncPermissions::class)->handle();
    $admin = User::factory()->create();
    $admin->assignRole(SyncPermissions::superAdminRole());
    $description = uploadFont('description.woff2');

    $this->actingAs($admin)->put(route('sunrice.admin.cms.entries.update', $translation->entry), [
        'locale' => 'id',
        'version' => $translation->fresh()->version,
        'title' => 'Project One',
        'slug' => 'project-one',
        'data' => ['global_style' => ['active' => true, 'font_heading' => $heading, 'font_description' => $description, 'font_button' => $button]],
    ])->assertSessionHasNoErrors();

    expect($folder->media()->count())->toBe(3)
        ->and(MediaFolder::query()->where('name', 'Fonts')->count())->toBe(1);
});

test('fonts of different projects get their own folders, other media stays put', function () {
    $first = uploadFont('first.woff2');
    $second = uploadFont('second.woff2');
    $other = uploadFont('other.woff2');

    app(CreateEntry::class)->handle($this->projects, ['title' => 'A', 'slug' => 'project-a', 'locale' => 'id', 'data' => ['global_style' => ['font_heading' => $first]]]);
    app(CreateEntry::class)->handle($this->projects, ['title' => 'B', 'slug' => 'project-b', 'locale' => 'id', 'data' => ['global_style' => ['font_heading' => $second]]]);

    expect(MediaFolder::query()->where('name', 'project-a')->firstOrFail()->media()->pluck('id')->all())->toBe([$first])
        ->and(MediaFolder::query()->where('name', 'project-b')->firstOrFail()->media()->pluck('id')->all())->toBe([$second])
        ->and(Media::find($other)->folder_id)->toBeNull();
});
