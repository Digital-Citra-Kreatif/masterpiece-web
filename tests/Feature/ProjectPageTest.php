<?php

use App\Models\UnitPoint;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake(); // Tanpa pelacakan titik
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    $this->projects = Collection::query()->where('handle', 'projects')->firstOrFail();
});

/**
 * Entri proyek terbit
 *
 * @param  array<string, mixed>  $data
 */
function publishProject(Collection $projects, array $data, string $slug = 'westlake'): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => $slug, 'locale' => 'id', 'data' => $data]);
    app(PublishEntry::class)->handle($translation->fresh());

    return $translation->fresh();
}

/**
 * Entri unit terbit
 *
 * @param  array<string, mixed>  $data
 */
function publishUnit(string $code, array $data): EntryTranslation
{
    $units = Collection::query()->where('handle', 'units')->firstOrFail();
    $translation = app(CreateEntry::class)->handle($units, ['title' => $code, 'slug' => str($code)->slug()->toString(), 'locale' => 'id', 'data' => $data]);
    app(PublishEntry::class)->handle($translation->fresh());

    return $translation->fresh();
}

function projectImage(string $name): int
{
    return app(UploadMedia::class)->handle(UploadedFile::fake()->image($name, 40, 30), [])->id;
}

function projectFont(string $name): int
{
    return app(UploadMedia::class)->handle(UploadedFile::fake()->createWithContent($name, 'wOF2font'), [])->id;
}

/** @return array<string, mixed> */
function inactiveStyle(): array
{
    return ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']];
}

test('the masterplan is the Kawasan scene with its opening, and points without a position add no sample hotspots', function () {
    publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'Westlake'],
        'masterplan' => ['title' => 'Masterplan', 'description' => 'Around the district.', 'points' => [
            ['number' => '01', 'name' => 'West Lakeside', 'image' => null],
        ]],
    ]);

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertDontSee('scene--project', false)
        ->assertSee('data-base="'.url('media/scroll/kawasan').'/"', false)
        ->assertDontSee('area-plaza', false)
        ->assertDontSee('Plaza &amp; boulevard ruko', false)
        ->assertSee('scene--masterplan', false)
        ->assertSee('data-src="'.asset('media/hero/teaser.mp4').'"', false)
        ->assertSee('data-mp-title>Masterplan</h2>', false)
        ->assertSee('<p class="mp__text" data-mp-text>Around the district.</p>', false)
        ->assertDontSee('class="scene__loc-img"', false);
});

test('the masterplan orbit carries the CMS points with a position as its hotspots', function () {
    publishProject($this->projects, [
        'global_style' => [...inactiveStyle(), 'active' => true],
        'hero' => ['heading' => 'Westlake'],
        'masterplan' => ['title' => 'Masterplan', 'points' => [
            ['number' => '01', 'name' => 'West Lakeside', 'image' => null, 'x' => 50, 'y' => 52],
            ['number' => '06', 'name' => 'JX Hometown', 'image' => null, 'x' => 31, 'y' => null],
        ]],
    ]);

    $html = $this->get('/projects/westlake')
        ->assertOk()
        ->assertSee('scene scene--masterplan', false)
        ->assertSee('data-base="'.url('media/scroll/kawasan').'/"', false)
        ->assertSee('data-src="'.asset('media/hero/teaser.mp4').'"', false)
        ->assertSee('data-spot-open="masterplan-1"', false)
        ->assertDontSee('data-spot-open="masterplan-2"', false)
        ->assertDontSee('data-spot-open="area-plaza"', false)
        // Pin penanda
        ->assertSee('class="spot spot--marker"', false)
        ->assertSee('pj-marker pj-marker--circle', false)
        ->getContent();

    preg_match('/data-spots="([^"]+)"/', $html, $match);
    expect(json_decode(html_entity_decode($match[1]), true)['masterplan-1']['point'])->toBe([50, 52]);
});

test('the cluster is the homepage Cluster scene with the CMS points that have a position as hotspots', function () {
    publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'Westlake'],
        'cluster' => ['title' => 'Cluster', 'points' => [
            ['number' => '01', 'name' => 'Lakeview Units', 'description' => 'Lake views.', 'image' => null, 'x' => 45, 'y' => 56],
            ['number' => '06', 'name' => 'Lakefront Pods', 'description' => null, 'image' => null],
        ]],
    ]);

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertSee('data-base="'.url('media/scroll/kawasan').'/"', false)
        ->assertDontSee('data-still=', false)
        ->assertDontSee('scene--project', false)
        ->assertSee('data-spot-open="cluster-1"', false)
        // Titik tanpa posisi
        ->assertDontSee('data-spot-open="cluster-2"', false)
        ->assertDontSee('Lakefront Pods');
});

test('the location shows only the map, without spots or area figures', function () {
    publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'Westlake'],
        'location' => [
            'title' => 'Location',
            'maps' => [projectImage('map.png')],
            'pins' => [['name' => 'West Lakeside', 'x' => 41, 'y' => 40], ['name' => 'Unplaced', 'x' => null, 'y' => 10]],
            'stats' => [['value' => '500K+', 'label' => 'Population in BSD City']],
        ],
    ]);

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertSee('<div class="pj-loc__map is-main">', false)
        ->assertDontSee('aria-label="Perbesar peta"', false)
        ->assertDontSee('pj-loc__pin', false)
        ->assertDontSee('West Lakeside')
        ->assertDontSee('500K+');
});

test('a project page shows each section with content and skips the empty ones', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-1')->firstOrFail();
    $type->update(['data' => [...$type->data, 'view' => 'lakeview', 'dimensions' => '4,5 x 10 m', 'lt' => 45, 'lb' => 93, 'floors' => 2]]);

    $project = publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'A New Landmark by the Lake', 'description' => 'By the lake in BSD City.', 'media' => [projectImage('hero.jpg')]],
        'masterplan' => ['title' => 'Masterplan', 'description' => 'Around the district.', 'points' => [
            ['number' => '01', 'name' => 'West Lakeside', 'image' => projectImage('point.jpg')],
            ['number' => '05', 'name' => 'Office & Hotel', 'image' => null],
        ]],
        'cluster' => ['title' => 'Cluster', 'points' => [
            ['number' => '01', 'name' => 'Lakeview Units', 'description' => 'Lake views.', 'image' => projectImage('cluster.jpg')],
            ['number' => '06', 'name' => 'Lakefront Pods', 'description' => null, 'image' => null],
        ]],
        'facilities' => ['title' => 'Facilities', 'items' => [
            ['name' => 'Lakeside Social Deck', 'image' => projectImage('deck.jpg')],
        ]],
        'types' => [
            'title' => 'Type',
            'units' => [$type->entry_id],
        ],
        'availability' => ['title' => 'Unit Availability', 'siteplan' => projectImage('siteplan.jpg')],
        'whatsapp' => ['contacts' => []],
    ]);
    // Slug proyek URL
    $type->update(['data' => [...$type->fresh()->data, 'project' => $project->entry_id]]);
    $a1 = publishUnit('A-1', ['project' => $project->entry_id, 'sale_status' => 'sold']);
    publishUnit('A-2', ['project' => $project->entry_id, 'sale_status' => 'available']);
    $other = publishProject($this->projects, ['whatsapp' => ['contacts' => []]], 'lain');
    $z9 = publishUnit('Z-9', ['project' => $other->entry_id, 'sale_status' => 'reserved']);
    UnitPoint::factory()->create(['project_id' => $project->entry_id, 'unit_id' => $a1->entry_id, 'x' => 5.97, 'y' => 66]);
    UnitPoint::factory()->create(['project_id' => $other->entry_id, 'unit_id' => $z9->entry_id, 'x' => 10, 'y' => 10]);

    $response = $this->get('/projects/westlake')->assertOk();

    $response
        // Hero proyek
        ->assertSeeInOrder(['<h1 class="hero__title" id="hero-title">', '<span class="hero__word" style="--i: 0">A</span>', '>Landmark</span>', '>Lake</span>', '</h1>'], false)
        ->assertDontSee('class="hero__lead"', false)
        ->assertDontSee('class="hero__rule"', false)
        ->assertSee('data-hero-slides', false)
        ->assertDontSee('Play Full Video')
        ->assertSee('scene scene--masterplan', false)
        // Tanpa hotspot contoh
        ->assertDontSee('cluster-plaza', false)
        ->assertDontSee('Lakefront Pods')
        ->assertSee('<section id="facility" class="fac fac--split"', false)
        ->assertSee('<section id="type" class="types types--project"', false)
        ->assertSee('href="'.url('/westlake/tipe/tipe-1').'"', false)
        ->assertDontSee('data-sheet=', false)
        ->assertSee('Lakeside Social Deck')
        ->assertSee($type->title)
        ->assertSee('4,5 x 10 m')
        ->assertSee('93 m²')
        // Titik status unit
        ->assertSee('<section id="unit" class="units"', false)
        ->assertSee('style="left: 5.97%; top: 66%"', false)
        ->assertSee('title="A-1 · Terjual"', false)
        ->assertDontSee('title="A-2', false)
        ->assertDontSee('title="Z-9', false)
        ->assertDontSee('id="location"', false)
        ->assertDontSee('href="#location"', false);

    $html = $response->getContent();
    expect(substr_count($html, 'class="ov is-info pj-sheet"'))->toBe(0)
        ->and(substr_count($html, 'class="roll-host"'))->toBe(5);
});

test('the hero slides through every image and video of its media', function () {
    $video = app(UploadMedia::class)->handle(UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'), [])->id;
    publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'Westlake', 'media' => [projectImage('one.jpg'), $video, projectImage('two.jpg')]],
    ]);

    $html = $this->get('/projects/westlake')->assertOk()->getContent();

    expect(preg_match_all('/class="hero__slide[ "]/', $html))->toBe(3)
        ->and(substr_count($html, '<video class="hero__media"'))->toBe(1)
        ->and(preg_match_all('/<img\s+class="hero__media"/', $html))->toBe(2);
});

test('the 360° section sits above Type, with spinning, still and shared tabs', function () {
    $frames = collect(['frame_10.jpg', 'frame_2.jpg', 'frame_1.jpg'])->map(fn (string $name): int => projectImage($name))->all();
    publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'Westlake'],
        'turntable' => ['title' => 'Explore the unit', 'tabs' => [
            ['name' => 'Fasad', 'description' => 'Tampak depan.', 'images' => $frames, 'frame' => null],
            ['name' => 'Lantai 1', 'description' => 'Denah aksonometri.', 'images' => [projectImage('floor.jpg')], 'frame' => null],
            ['name' => 'Lantai 2', 'description' => 'Pakai gambar Fasad.', 'images' => [], 'frame' => 2],
        ]],
        'types' => ['title' => 'Type', 'hero_image' => projectImage('cutaway.jpg')],
    ]);

    $html = $this->get('/projects/westlake')->assertOk()
        ->assertSeeInOrder(['<section', 'id="view-360"', 'class="tph tph--section"', '<h2 class="pj-title display" id="view-360-title">Explore the unit</h2>', 'id="type"'], false)
        ->assertSee('href="#view-360"', false)
        ->assertSee('<strong>Fasad.</strong> Tampak depan.', false)
        ->getContent();

    preg_match('/data-parts="([^"]+)"/', $html, $match);
    $parts = json_decode(html_entity_decode($match[1]), true);
    $names = fn (array $urls): array => array_map(fn (string $url): string => preg_replace('/.*-(frame-\d+)\.jpg.*/', '$1', $url), $urls);
    expect($parts)->toHaveCount(3)
        ->and($names($parts[0]['frames']))->toBe(['frame-1', 'frame-2', 'frame-10'])
        ->and($parts[1]['frames'])->toHaveCount(1)
        // Tab tanpa gambar memutar gambar Fasad
        ->and($parts[2]['frames'])->toBe($parts[0]['frames'])
        ->and($parts[2]['config'])->toBe($parts[0]['config'])
        ->and($parts[2]['frame'])->toBe(2)
        ->and($html)->toContain('data-part-spin')
        ->and($html)->toContain('data-config="'.url('/turntable/'));
});

test('the Type cutaway image sits inside the section, above the title', function () {
    publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'Westlake'],
        'types' => ['title' => 'Type', 'hero_image' => projectImage('cutaway.jpg')],
    ]);

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertSeeInOrder(['<section id="type" class="types types--project"', '<div class="types__top">', 'class="pj-band"', 'id="type-title"'], false);
});

test('an active Global Style sets the project\'s fonts and colours over the homepage tokens', function () {
    $style = [
        'active' => true,
        'palette' => ['color_1' => '#1F4D3A', 'color_4' => '#F5EFE3'],
        'fonts' => ['heading' => projectFont('heading.woff2'), 'description' => projectFont('body.ttf')],
        'marker' => ['type' => 'shape', 'shape' => 'diamond', 'color' => '#C9A227'],
        'corner' => ['style' => 'rounded', 'size' => 6],
    ];
    publishProject($this->projects, ['global_style' => $style, 'hero' => ['heading' => 'Westlake']]);

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertSee("@font-face{font-family:'project-heading'", false)
        ->assertSee("format('woff2')", false)
        ->assertSee("format('truetype')", false)
        ->assertSee("--display:'project-heading', 'DM Sans Variable'", false)
        ->assertSee("--sans:'project-description', 'DM Sans Variable'", false)
        ->assertDontSee('--button:', false)
        ->assertSee('--accent:#1f4d3a;--paper:#f5efe3;', false)
        ->assertDontSee('--ink:', false)
        ->assertSee('--marker:#c9a227;--radius:6px;', false);
});

test('a project page uses the project logo as its browser icon while the Global Style is active', function () {
    $logo = projectImage('logo.png');
    publishProject($this->projects, ['global_style' => [...inactiveStyle(), 'active' => true, 'logo' => $logo], 'hero' => ['heading' => 'Westlake']]);
    publishProject($this->projects, ['global_style' => [...inactiveStyle(), 'logo' => $logo], 'hero' => ['heading' => 'Westlake']], 'off');

    $url = Media::query()->findOrFail($logo)->url();
    $this->get('/projects/westlake')->assertOk()->assertSee('<link rel="icon" href="'.$url.'" type="image/png" />', false);
    $this->get('/projects/off')->assertOk()->assertDontSee('<link rel="icon" href="'.$url.'"', false);
});

test('an inactive Global Style leaves the homepage look', function () {
    publishProject($this->projects, [
        'global_style' => [...inactiveStyle(), 'palette' => ['color_1' => '#1F4D3A'], 'fonts' => ['heading' => projectFont('heading.woff2')]],
        'hero' => ['heading' => 'Westlake'],
    ]);

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertDontSee('@font-face', false)
        ->assertDontSee('--accent:#1f4d3a', false)
        ->assertSee('masterpiece-logo.png', false);
});

test('the WhatsApp button shows only with contacts', function () {
    publishProject($this->projects, ['global_style' => inactiveStyle(), 'hero' => ['heading' => 'Westlake'], 'whatsapp' => ['contacts' => []]]);

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertDontSee('data-wa', false)
        ->assertDontSee('wa.me');

    publishProject($this->projects, ['global_style' => inactiveStyle(), 'hero' => ['heading' => 'Westlake'], 'whatsapp' => [
        'label' => 'Chat with us',
        'show_button' => true,
        'contacts' => [['title' => 'Marketing', 'number' => '6281234567890', 'message' => 'Halo, saya tertarik']],
    ]], 'westlake-wa');

    $this->get('/projects/westlake-wa')
        ->assertOk()
        ->assertSee('Chat with us')
        ->assertSee('https://wa.me/6281234567890?text=Halo%2C%20saya%20tertarik', false);
});

test('a project\'s type page has the project\'s logo, a button back to the project and lists the other types', function () {
    $types = EntryTranslation::query()->whereIn('slug', ['tipe-1', 'tipe-2'])->get()->keyBy('slug');
    $project = publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'Westlake'],
        'types' => ['title' => 'Type', 'others_title' => 'Tipe lainnya di Westlake', 'units' => [$types['tipe-1']->entry_id, $types['tipe-2']->entry_id]],
        'whatsapp' => ['contacts' => [['title' => 'Marketing', 'number' => '6281234567890']]],
    ]);
    foreach ($types as $type) {
        $type->update(['data' => [...$type->fresh()->data, 'project' => $project->entry_id]]);
    }

    $this->get('/westlake/tipe/tipe-1')
        ->assertOk()
        ->assertSee('<header class="hd hd--type">', false)
        ->assertDontSee('hd__nav', false)
        ->assertSee('<a class="hd__mark" href="'.url('/projects/westlake').'" aria-label="Westlake, halaman proyek">', false)
        ->assertSee('<a class="btn hd__btn" href="'.url('/projects/westlake').'">', false)
        ->assertSee('<span>Kembali <span class="hd__btn-more">ke Westlake</span></span>', false)
        ->assertDontSee('Lihat Proyek')
        ->assertSee('<h1 class="display tph__name" id="type-name">'.$types['tipe-1']->title.'</h1>', false)
        ->assertDontSee('tph__tabs', false)
        ->assertDontSee('data-turntable', false)
        ->assertSee('<section id="tipe-lain" class="types types--project"', false)
        ->assertSee('Tipe lainnya di Westlake')
        ->assertSee('href="'.url('/westlake/tipe/tipe-2').'"', false)
        ->assertSee('data-wa', false);
});

test('a project\'s type page shows the project\'s 360° tabs beside the type sheet', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-1')->firstOrFail();
    $frames = collect(['frame_1.jpg', 'frame_2.jpg'])->map(fn (string $name): int => projectImage($name))->all();
    $project = publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'Westlake'],
        'turntable' => ['title' => 'Explore the unit', 'tabs' => [
            ['name' => 'Fasad', 'description' => 'Tampak depan.', 'images' => $frames, 'frame' => null],
            ['name' => 'Lantai 1', 'description' => 'Area usaha.', 'images' => [projectImage('floor.jpg')], 'frame' => null],
        ]],
    ]);
    $type->update(['data' => [...$type->fresh()->data, 'project' => $project->entry_id]]);

    $html = $this->get('/westlake/tipe/tipe-1')
        ->assertOk()
        ->assertSee('<h1 class="display tph__name" id="type-name">'.$type->title.'</h1>', false)
        ->assertSee('<h2 class="display tps__part" aria-live="polite" data-part-name>Fasad</h2>', false)
        ->assertSee('<p class="tps__desc" data-part-text >Tampak depan.</p>', false)
        ->assertSeeInOrder(['data-part-tab', 'Fasad', 'data-part-tab', 'Lantai 1'], false)
        ->assertDontSee('tps__no', false)
        ->assertSee('aria-pressed="true"', false)
        ->getContent();

    preg_match('/data-parts="([^"]+)"/', $html, $match);
    $parts = json_decode(html_entity_decode($match[1]), true);

    expect($parts)->toHaveCount(2)
        ->and(array_column($parts, 'text'))->toBe(['Tampak depan.', 'Area usaha.'])
        ->and($html)->toContain('data-turntable')
        ->and($html)->toContain('data-config="'.url('/turntable/'));
});

test('the logo on a project page reloads that project page', function () {
    publishProject($this->projects, ['global_style' => inactiveStyle(), 'hero' => ['heading' => 'Westlake']]);

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertSee('<a class="hd__mark" href="'.url('/projects/westlake').'" aria-label="Westlake, muat ulang halaman proyek">', false);
});

test('the project header menu uses each section title from the CMS, or the default label without one', function () {
    publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'Westlake'],
        'masterplan' => ['title' => 'Rencana Induk', 'points' => [['number' => '01', 'name' => 'West Lakeside', 'image' => null]]],
        'turntable' => ['title' => '', 'tabs' => [['name' => 'Fasad', 'description' => '', 'images' => [projectImage('frame_1.jpg')], 'frame' => null]]],
    ]);

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertSeeInOrder(['class="hd__nav"', 'href="#masterplan"', 'Rencana Induk', 'href="#view-360"', '360°'], false)
        ->assertDontSee('>Masterplan</a>', false);
});
