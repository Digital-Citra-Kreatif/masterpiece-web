<?php

use App\Jobs\ProcessOrbitFrames;
use App\Jobs\TrackProjectPoints;
use App\Models\User;
use App\Support\OrbitFrames;
use App\Support\ProjectTracks;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
    Storage::fake('local');
    $this->seed(SiteContentSeeder::class);
    app(SyncPermissions::class)->handle();

    $this->admin = User::factory()->create();
    $this->admin->assignRole(SyncPermissions::superAdminRole());
    $this->project = orbitProject();
});

/** Proyek terbit dengan satu titik */
function orbitProject(): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle(Collection::query()->where('handle', 'projects')->firstOrFail(), ['title' => 'Westlake', 'slug' => 'westlake', 'locale' => 'id', 'data' => [
        'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
        'hero' => ['heading' => 'Westlake'],
        'masterplan' => ['title' => 'Masterplan', 'points' => [['number' => '01', 'name' => 'West Lakeside', 'image' => null, 'x' => 50, 'y' => 50]]],
        'whatsapp' => ['contacts' => []],
    ]]);
    app(PublishEntry::class)->handle($translation->fresh());

    return $translation->fresh();
}

/**
 * ZIP berisi frame
 *
 * @param  list<string>  $names
 */
function frameZip(array $names, int $width = 320, int $height = 180): string
{
    $path = tempnam(sys_get_temp_dir(), 'orbit').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    foreach ($names as $name) {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $zip->addFromString($name, (string) ob_get_clean());
    }
    $zip->addFromString('__MACOSX/._f_01.jpg', 'junk');
    $zip->addFromString('catatan.txt', 'bukan gambar');
    $zip->close();

    return $path;
}

/**
 * Kirim ZIP per potongan
 *
 * @return list<TestResponse>
 */
function sendChunks(mixed $test, int $project, string $section, string $zip, int $parts = 3): array
{
    $bytes = (string) file_get_contents($zip);
    $size = (int) ceil(strlen($bytes) / $parts);
    $upload = (string) Str::uuid();
    $responses = [];
    foreach (str_split($bytes, $size) as $index => $chunk) {
        $responses[] = $test->actingAs($test->admin)->post("/admin/orbit-upload/{$project}/{$section}", [
            'upload' => $upload,
            'index' => $index,
            'total' => $parts,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $chunk),
        ], ['Accept' => 'application/json']);
    }

    return $responses;
}

test('a frames ZIP sent in chunks is joined and queued for processing', function () {
    $zip = frameZip(array_map(fn (int $n): string => "orbit/f_{$n}.jpg", range(1, 10)));

    $responses = sendChunks($this, $this->project->entry_id, 'masterplan', $zip);

    $responses[0]->assertOk()->assertJsonPath('received', 1);
    $responses[2]->assertOk()->assertJsonPath('state', 'queued')->assertJsonPath('active', false);
    expect(file_get_contents(Storage::disk('local')->path(OrbitFrames::upload($this->project->entry_id, 'masterplan').'.zip')))->toBe(file_get_contents($zip));
    Queue::assertPushed(ProcessOrbitFrames::class, fn (ProcessOrbitFrames $job): bool => $job->entryId === $this->project->entry_id && $job->section === 'masterplan');
});

test('chunks out of order are refused', function () {
    $this->actingAs($this->admin)->post('/admin/orbit-upload/'.$this->project->entry_id.'/masterplan', [
        'upload' => (string) Str::uuid(),
        'index' => 1,
        'total' => 2,
        'chunk' => UploadedFile::fake()->createWithContent('chunk', 'abc'),
    ], ['Accept' => 'application/json'])->assertStatus(409);
});

test('processing makes both sizes in file name order with a manifest, then retracks the points', function () {
    // Urut alami: f_2 sebelum f_10
    $names = array_map(fn (int $n): string => "f_{$n}.jpg", [10, 2, 1, 3, 4, 5, 6, 7, 8, 9]);
    Storage::disk('local')->put(OrbitFrames::upload($this->project->entry_id, 'masterplan').'.zip', (string) file_get_contents(frameZip($names)));

    (new ProcessOrbitFrames($this->project->entry_id, 'masterplan'))->handle();

    $base = OrbitFrames::base($this->project->entry_id, 'masterplan');
    expect($base)->toStartWith('/storage/orbits/entry-'.$this->project->entry_id.'/masterplan/v');
    $dir = OrbitFrames::directory($base);
    $manifest = json_decode((string) file_get_contents("{$dir}/manifest.json"), true);
    expect($manifest)->toMatchArray(['count' => 10, 'width' => 1280, 'height' => 720, 'sizes' => ['lg' => 1280, 'sm' => 800], 'pattern' => '{size}/f_{n}.jpg'])
        ->and(getimagesize("{$dir}/lg/f_009.jpg"))->toMatchArray([1280, 720])
        ->and(getimagesize("{$dir}/sm/f_000.jpg"))->toMatchArray([800, 450])
        ->and(OrbitFrames::status($this->project->entry_id, 'masterplan'))->toBe(['state' => 'ready', 'message' => null, 'count' => 10, 'source' => null])
        ->and(Storage::disk('local')->exists(OrbitFrames::upload($this->project->entry_id, 'masterplan').'.zip'))->toBeFalse();
    Queue::assertPushed(TrackProjectPoints::class);
});

test('a ZIP with too few or mixed size frames fails with a message for the admin', function (array $names, int $height, string $message) {
    $zip = frameZip($names);
    if ($height !== 180) {
        // Satu frame beda ukuran
        $other = new ZipArchive;
        $other->open($zip);
        $image = imagecreatetruecolor(320, $height);
        ob_start();
        imagejpeg($image);
        $other->addFromString('f_99.jpg', (string) ob_get_clean());
        $other->close();
    }
    Storage::disk('local')->put(OrbitFrames::upload($this->project->entry_id, 'cluster').'.zip', (string) file_get_contents($zip));

    (new ProcessOrbitFrames($this->project->entry_id, 'cluster'))->handle();

    expect(OrbitFrames::status($this->project->entry_id, 'cluster'))->toMatchArray(['state' => 'failed'])
        ->and(OrbitFrames::status($this->project->entry_id, 'cluster')['message'])->toContain($message)
        ->and(OrbitFrames::base($this->project->entry_id, 'cluster'))->toBeNull()
        ->and(Storage::disk('public')->allFiles('orbits'))->toBe([]);
})->with([
    'too few' => [['f_1.jpg', 'f_2.jpg'], 180, 'minimal 8 frame'],
    'mixed sizes' => [array_map(fn (int $n): string => "f_{$n}.jpg", range(1, 9)), 320, 'berbeda ukuran'],
]);

test('the project page and the point picker use the uploaded frames, without the teaser video', function () {
    Storage::disk('local')->put(OrbitFrames::upload($this->project->entry_id, 'masterplan').'.zip', (string) file_get_contents(frameZip(array_map(fn (int $n): string => "f_{$n}.jpg", range(1, 8)))));
    (new ProcessOrbitFrames($this->project->entry_id, 'masterplan'))->handle();
    $base = OrbitFrames::base($this->project->entry_id, 'masterplan');

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertSee('data-base="'.rtrim(asset($base), '/').'/"', false)
        ->assertDontSee('media/hero/teaser.mp4', false)
        ->assertSee('data-spot-open="masterplan-1"', false);
    $this->actingAs($this->admin)
        ->getJson('/admin/orbit-frames?project='.$this->project->entry_id)
        ->assertJsonPath('masterplan.src', asset(trim($base, '/').'/lg/f_000.jpg'))
        ->assertJsonPath('masterplan.height', 720)
        ->assertJsonPath('cluster.src', asset('media/scroll/kawasan/lg/f_000.jpg'));
});

test('points are tracked on the uploaded frames, and sample frame tracks are not reused for them', function () {
    Storage::disk('local')->put(OrbitFrames::upload($this->project->entry_id, 'masterplan').'.zip', (string) file_get_contents(frameZip(array_map(fn (int $n): string => "f_{$n}.jpg", range(1, 8)))));
    (new ProcessOrbitFrames($this->project->entry_id, 'masterplan'))->handle();
    $base = OrbitFrames::base($this->project->entry_id, 'masterplan');
    $placed = ['masterplan-1' => [50.0, 50.0]];
    // Jalur lama dari frame contoh
    Storage::put('tracks/entry-'.$this->project->entry_id.'.json', json_encode(['masterplan' => ['hash' => md5('lama'), 'tracker' => 2, 'points' => $placed, 'tracks' => ['masterplan-1' => [[1, 1]]]]]));

    expect(ProjectTracks::for($this->project->entry_id, 'masterplan', $placed))->toBeNull();

    Process::fake(['*' => Process::result(output: json_encode(['masterplan-1' => [[640, 360]]]))]);
    ProjectTracks::make($this->project->fresh());

    Process::assertRan(fn ($process): bool => in_array(OrbitFrames::directory($base), $process->command, true));
    expect(ProjectTracks::for($this->project->entry_id, 'masterplan', $placed))->toBe(['masterplan-1' => [[640, 360]]]);
});

test('removing the uploaded frames brings back the image or sample frames', function () {
    Storage::disk('local')->put(OrbitFrames::upload($this->project->entry_id, 'masterplan').'.zip', (string) file_get_contents(frameZip(array_map(fn (int $n): string => "f_{$n}.jpg", range(1, 8)))));
    (new ProcessOrbitFrames($this->project->entry_id, 'masterplan'))->handle();

    $this->actingAs($this->admin)
        ->deleteJson('/admin/orbit-upload/'.$this->project->entry_id.'/masterplan')
        ->assertOk()
        ->assertJsonPath('active', false)
        ->assertJsonPath('state', 'idle');
    expect(OrbitFrames::base($this->project->entry_id, 'masterplan'))->toBeNull();
    $this->get('/projects/westlake')->assertSee('data-base="'.url('media/scroll/kawasan').'/"', false);
});

test('guests and editors without update access cannot upload frames', function () {
    $this->postJson('/admin/orbit-upload/'.$this->project->entry_id.'/masterplan')->assertUnauthorized();
    $this->actingAs(User::factory()->create())
        ->postJson('/admin/orbit-upload/'.$this->project->entry_id.'/masterplan')
        ->assertForbidden();
    $this->actingAs($this->admin)
        ->getJson('/admin/orbit-upload/999/masterplan')
        ->assertNotFound();
});

/** Isi field Gambar Masterplan lalu simpan */
function saveSceneMedia(EntryTranslation $translation, ?int $mediaId): void
{
    $fresh = $translation->fresh();
    $data = $fresh->draft_data;
    $data['masterplan']['image'] = $mediaId;
    $fresh->forceFill(['draft_data' => $data])->save();
}

/** ZIP frame di media library */
function zipMedia(): Media
{
    return app(UploadMedia::class)->handle(new UploadedFile(frameZip(array_map(fn (int $n): string => "f_{$n}.jpg", range(1, 8))), 'masterplan-orbit.zip', 'application/zip', null, true), []);
}

test('a ZIP chosen in the masterplan image field is queued once as the frames source', function () {
    $zip = zipMedia();

    saveSceneMedia($this->project, $zip->id);
    saveSceneMedia($this->project, $zip->id);

    Queue::assertPushed(ProcessOrbitFrames::class, 1);
    expect(OrbitFrames::status($this->project->entry_id, 'masterplan'))->toMatchArray(['state' => 'queued', 'source' => $zip->id])
        ->and(Storage::disk('local')->size(OrbitFrames::upload($this->project->entry_id, 'masterplan').'.zip'))->toBe($zip->size);

    // Diproses, lalu tampil di halaman
    (new ProcessOrbitFrames($this->project->entry_id, 'masterplan'))->handle();
    expect(OrbitFrames::status($this->project->entry_id, 'masterplan'))->toMatchArray(['state' => 'ready', 'count' => 8, 'source' => $zip->id]);
    $this->get('/projects/westlake')->assertSee('data-base="'.rtrim(asset(OrbitFrames::base($this->project->entry_id, 'masterplan')), '/').'/"', false);
});

test('clearing the ZIP from the image field removes its frames', function () {
    saveSceneMedia($this->project, zipMedia()->id);
    (new ProcessOrbitFrames($this->project->entry_id, 'masterplan'))->handle();

    saveSceneMedia($this->project, null);

    expect(OrbitFrames::base($this->project->entry_id, 'masterplan'))->toBeNull()
        ->and(OrbitFrames::status($this->project->entry_id, 'masterplan')['source'])->toBeNull();
});

test('frames uploaded through the panel are kept when the image field has no ZIP', function () {
    Storage::disk('local')->put(OrbitFrames::upload($this->project->entry_id, 'masterplan').'.zip', (string) file_get_contents(frameZip(array_map(fn (int $n): string => "f_{$n}.jpg", range(1, 8)))));
    (new ProcessOrbitFrames($this->project->entry_id, 'masterplan'))->handle();

    saveSceneMedia($this->project, null);

    expect(OrbitFrames::base($this->project->entry_id, 'masterplan'))->not->toBeNull();
});

test('a point\'s zoom image goes to its hotspot and adds the zoom layer, points without one add nothing', function () {
    $data = fn (): array => $this->project->fresh()->data;
    $this->get('/projects/westlake')->assertOk()->assertDontSee('data-scene-zoom', false);

    $zoom = app(UploadMedia::class)->handle(UploadedFile::fake()->image('zoom-west-lakeside.jpg', 3840, 2160), []);
    $points = $data()['masterplan']['points'];
    $points[0]['zoom_image'] = $zoom->id;
    $this->project->update(['data' => [...$data(), 'masterplan' => [...$data()['masterplan'], 'points' => $points]]]);

    $html = $this->get('/projects/westlake')->assertOk()->assertSee('data-scene-zoom', false)->getContent();
    preg_match('/data-spots="([^"]+)"/', $html, $match);
    $spots = json_decode(html_entity_decode($match[1]), true);

    // File asli, bukan konversi kecil
    expect(array_values($spots)[0]['zoom'])->toBe($zoom->url());
});
