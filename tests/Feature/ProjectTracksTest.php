<?php

use App\Jobs\TrackProjectPoints;
use App\Support\ProjectTracks;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
