<?php

use App\Jobs\TrackProjectPoints;
use App\Support\ProjectTracks;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Models\Collection;

uses(RefreshDatabase::class);

test('the placed points are keyed as the page keys them, skipping unnamed and unplaced rows', function () {
    $placed = ProjectTracks::placed([
        ['name' => 'West Lakeside', 'x' => 50, 'y' => 52],
        ['name' => '', 'x' => 10, 'y' => 10],
        ['name' => 'JX Hometown', 'x' => 31, 'y' => null],
        ['name' => 'The Barn', 'x' => 120, 'y' => 4],
    ], 'masterplan');

    expect($placed)->toBe(['masterplan-1' => [50.0, 52.0], 'masterplan-3' => [100.0, 4.0]]);
});

test('stored paths are used only while they were made for the same places', function () {
    Storage::fake('local');
    $placed = ['masterplan-1' => [50.0, 52.0]];
    $hash = (new ReflectionMethod(ProjectTracks::class, 'hash'))->invoke(null, 'masterplan', $placed);
    Storage::put('tracks/entry-7.json', json_encode(['masterplan' => ['hash' => $hash, 'tracks' => ['masterplan-1' => [[640, 374], null]]]]));

    expect(ProjectTracks::for(7, 'masterplan', $placed))->toBe(['masterplan-1' => [[640, 374], null]])
        ->and(ProjectTracks::for(7, 'masterplan', ['masterplan-1' => [51.0, 52.0]]))->toBeNull()
        ->and(ProjectTracks::for(8, 'masterplan', $placed))->toBeNull();
});

test('paths are kept by entry, so renaming the project does not lose them, and old files by slug still count', function () {
    Storage::fake('local');
    $placed = ['masterplan-1' => [50.0, 52.0]];
    $hash = (new ReflectionMethod(ProjectTracks::class, 'hash'))->invoke(null, 'masterplan', $placed);
    Storage::put('tracks/westlake.json', json_encode(['masterplan' => ['hash' => $hash, 'tracks' => ['masterplan-1' => [[640, 374]]]]]));

    expect(ProjectTracks::for(7, 'masterplan', $placed, 'westlake'))->toBe(['masterplan-1' => [[640, 374]]])
        ->and(ProjectTracks::for(7, 'masterplan', $placed, 'westlake-baru'))->toBeNull();
});

test('publishing a project queues its point tracking', function () {
    Queue::fake();
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    $projects = Collection::query()->where('handle', 'projects')->firstOrFail();

    $translation = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => 'westlake', 'locale' => 'id', 'data' => [
        'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
        'masterplan' => ['title' => 'Masterplan', 'points' => [['number' => '01', 'name' => 'West Lakeside', 'x' => 50, 'y' => 52]]],
    ]]);
    app(PublishEntry::class)->handle($translation->fresh());

    Queue::assertPushed(TrackProjectPoints::class, fn (TrackProjectPoints $job): bool => $job->translationId === $translation->id);
});

test('while a moved point waits for tracking, unchanged points keep their path and the moved one follows its old path shifted', function () {
    Storage::fake('local');
    $old = ['masterplan-1' => [50.0, 50.0], 'masterplan-2' => [10.0, 10.0]];
    $hash = (new ReflectionMethod(ProjectTracks::class, 'hash'))->invoke(null, 'masterplan', $old);
    Storage::put('tracks/entry-7.json', json_encode(['masterplan' => ['hash' => $hash, 'points' => $old, 'tracks' => [
        'masterplan-1' => [[640, 360], null],
        'masterplan-2' => [[128, 72], [130, 72]],
    ]]]));

    // Manifest Kawasan 1280 x 720: 10% = 128 x 72 px
    $tracks = ProjectTracks::for(7, 'masterplan', ['masterplan-1' => [60.0, 60.0], 'masterplan-2' => [10.0, 10.0], 'masterplan-3' => [5.0, 5.0]]);

    expect($tracks)->toBe([
        'masterplan-1' => [[768, 432], null],
        'masterplan-2' => [[128, 72], [130, 72]],
    ]);
});

test('retracking runs the tracker only for the points that moved or are new', function () {
    Storage::fake('local');
    Queue::fake();
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    $old = ['masterplan-1' => [50.0, 50.0], 'masterplan-2' => [10.0, 10.0]];
    $hash = (new ReflectionMethod(ProjectTracks::class, 'hash'))->invoke(null, 'masterplan', $old);
    Process::fake(['*' => Process::result(output: json_encode(['masterplan-2' => [[200, 100]]]))]);

    $projects = Collection::query()->where('handle', 'projects')->firstOrFail();
    $translation = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => 'westlake', 'locale' => 'id', 'data' => [
        'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
        'masterplan' => ['title' => 'Masterplan', 'points' => [
            ['number' => '01', 'name' => 'Tetap', 'x' => 50, 'y' => 50],
            ['number' => '02', 'name' => 'Pindah', 'x' => 20, 'y' => 20],
        ]],
    ]]);
    app(PublishEntry::class)->handle($translation->fresh());
    Storage::put("tracks/entry-{$translation->entry_id}.json", json_encode(['masterplan' => ['hash' => $hash, 'tracker' => 2, 'points' => $old, 'tracks' => ['masterplan-1' => [[640, 360]], 'masterplan-2' => [[128, 72]]]]]));

    ProjectTracks::make($translation->fresh());

    Process::assertRan(fn ($process): bool => json_decode($process->input, true) === ['points' => ['masterplan-2' => [256, 144]]]);
    $stored = json_decode(Storage::get("tracks/entry-{$translation->entry_id}.json"), true)['masterplan'];
    expect($stored['tracks'])->toBe(['masterplan-1' => [[640, 360]], 'masterplan-2' => [[200, 100]]])
        ->and($stored['points'])->toEqual(['masterplan-1' => [50, 50], 'masterplan-2' => [20, 20]]);
});

test('tracks from an older tracker version are tracked again for every point', function () {
    Storage::fake('local');
    Queue::fake();
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    $placed = ['masterplan-1' => [50.0, 50.0]];
    $hash = (new ReflectionMethod(ProjectTracks::class, 'hash'))->invoke(null, 'masterplan', $placed);
    Process::fake(['*' => Process::result(output: json_encode(['masterplan-1' => [[641.5, 360.2]]]))]);

    $projects = Collection::query()->where('handle', 'projects')->firstOrFail();
    $translation = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => 'westlake', 'locale' => 'id', 'data' => [
        'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
        'masterplan' => ['title' => 'Masterplan', 'points' => [['number' => '01', 'name' => 'Tetap', 'x' => 50, 'y' => 50]]],
    ]]);
    app(PublishEntry::class)->handle($translation->fresh());
    // Tanpa versi pelacak
    Storage::put("tracks/entry-{$translation->entry_id}.json", json_encode(['masterplan' => ['hash' => md5('lama'), 'points' => $placed, 'tracks' => ['masterplan-1' => [[640, 360]]]]]));

    ProjectTracks::make($translation->fresh());

    Process::assertRan(fn ($process): bool => json_decode($process->input, true) === ['points' => ['masterplan-1' => [640, 360]]]);
    $stored = json_decode(Storage::get("tracks/entry-{$translation->entry_id}.json"), true)['masterplan'];
    expect($stored['hash'])->toBe($hash)
        ->and($stored['tracker'])->toBe(2)
        ->and($stored['tracks'])->toBe(['masterplan-1' => [[641.5, 360.2]]]);
});

test('a section with a still image from the CMS is not tracked', function () {
    Storage::fake('local');
    Queue::fake();
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    Process::fake();
    $image = app(UploadMedia::class)->handle(UploadedFile::fake()->image('masterplan.jpg', 1600, 900), []);

    $projects = Collection::query()->where('handle', 'projects')->firstOrFail();
    $translation = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => 'westlake', 'locale' => 'id', 'data' => [
        'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
        'masterplan' => ['title' => 'Masterplan', 'image' => $image->id, 'points' => [['number' => '01', 'name' => 'Tetap', 'x' => 50, 'y' => 50]]],
    ]]);
    app(PublishEntry::class)->handle($translation->fresh());

    ProjectTracks::make($translation->fresh());

    Process::assertNothingRan();
});

test('a save while tracking runs still queues a new tracking job', function () {
    expect(new TrackProjectPoints(1))->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class);
});
