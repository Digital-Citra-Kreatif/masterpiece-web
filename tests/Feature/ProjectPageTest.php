<?php

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    $this->projects = Collection::query()->where('handle', 'projects')->firstOrFail();
});

/**
 * A published Projects entry at /projects/{slug}.
 *
 * @param  array<string, mixed>  $data
 */
function publishProject(Collection $projects, array $data, string $slug = 'westlake'): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => $slug, 'locale' => 'id', 'data' => $data]);
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

test('the masterplan is the homepage Kawasan scene as it is, with its opening and sample hotspots', function () {
    publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'Westlake'],
        'masterplan' => ['title' => 'Masterplan', 'description' => 'Around the district.', 'points' => [
            ['number' => '01', 'name' => 'West Lakeside', 'image' => null, 'gallery' => []],
        ]],
    ]);

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertDontSee('scene--project', false)
        ->assertSee('data-base="'.url('media/scroll/kawasan').'/"', false)
        ->assertSee('data-spot-open="area-plaza"', false)
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
            ['number' => '01', 'name' => 'West Lakeside', 'image' => null, 'gallery' => [], 'x' => 50, 'y' => 52],
            ['number' => '06', 'name' => 'JX Hometown', 'image' => null, 'gallery' => [], 'x' => 31, 'y' => null],
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
        // Pins are the active Global Style's Penanda (a circle), not numbered squares.
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
        // A point without a position isn't a hotspot, so it isn't in the scene's list either.
        ->assertDontSee('data-spot-open="cluster-2"', false)
        ->assertDontSee('Lakefront Pods');
});

test('the location map shows its spots and not the area figures', function () {
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
        ->assertSee('<button class="pj-loc__pin" type="button" style="left: 41%; top: 40%">', false)
        ->assertSee('West Lakeside')
        ->assertDontSee('Unplaced')
        ->assertDontSee('500K+');
});

test('a project page shows each section with content and skips the empty ones', function () {
    $facility = EntryTranslation::query()->whereHas('entry.collection', fn ($query) => $query->where('handle', 'facilities'))->firstOrFail();
    $type = EntryTranslation::query()->where('slug', 'tipe-1')->firstOrFail();
    $type->update(['data' => [...$type->data, 'view' => 'lakeview', 'dimensions' => '4,5 x 10 m', 'lt' => 45, 'lb' => 93, 'floors' => 2]]);

    publishProject($this->projects, [
        'global_style' => inactiveStyle(),
        'hero' => ['heading' => 'A New Landmark by the Lake', 'description' => 'By the lake in BSD City.', 'media' => [projectImage('hero.jpg')]],
        'masterplan' => ['title' => 'Masterplan', 'description' => 'Around the district.', 'points' => [
            ['number' => '01', 'name' => 'West Lakeside', 'image' => projectImage('point.jpg'), 'gallery' => []],
            ['number' => '05', 'name' => 'Office & Hotel', 'image' => null, 'gallery' => []],
        ]],
        'cluster' => ['title' => 'Cluster', 'points' => [
            ['number' => '01', 'name' => 'Lakeview Units', 'description' => 'Lake views.', 'image' => projectImage('cluster.jpg')],
            ['number' => '06', 'name' => 'Lakefront Pods', 'description' => null, 'image' => null],
        ]],
        'facilities' => ['title' => 'Facilities', 'items' => [$facility->entry_id]],
        'types' => [
            'title' => 'Type',
            'views' => [['view' => 'lakeview', 'name' => 'Lake View', 'description' => 'Facing the lake.', 'businesses' => "Cafe\nRestaurant"]],
            'units' => [$type->entry_id],
            'specifications' => [['label' => 'Foundation', 'value' => "Mini pile\nBored pile"]],
        ],
        'availability' => ['title' => 'Unit Availability', 'siteplan' => projectImage('siteplan.jpg'), 'blocks' => [
            ['name' => 'Blok A', 'view' => 'streetview', 'image' => projectImage('blok-a.jpg')],
            ['name' => 'Blok B', 'view' => 'streetview', 'image' => null],
        ]],
        'whatsapp' => ['contacts' => []],
    ]);

    $response = $this->get('/projects/westlake')->assertOk();

    $response
        // The homepage's hero banner, with the project's Hero from the CMS; no full video, so no "Play Full Video".
        ->assertSee('<h1 class="hero__title" id="hero-title">A New Landmark by the Lake</h1>', false)
        ->assertDontSee('class="hero__lead"', false)
        ->assertDontSee('class="hero__rule"', false)
        ->assertSee('data-hero-slides', false)
        ->assertDontSee('Play Full Video')
        ->assertSee('scene scene--masterplan', false)
        // No placed point: the homepage Cluster scene with its sample hotspots.
        ->assertSee('data-spot-open="cluster-plaza"', false)
        ->assertDontSee('Lakefront Pods')
        ->assertSee('<section id="facility" class="fac fac--split"', false)
        ->assertSee('<section id="type" class="types types--project"', false)
        ->assertSee('href="'.url('/tipe/tipe-1').'"', false)
        ->assertDontSee('data-sheet=', false)
        ->assertSee($facility->title)
        ->assertSee($type->title)
        ->assertSee('4,5 x 10 m')
        ->assertSee('93 m²')
        ->assertDontSee('id="type-lakeview"', false)
        ->assertDontSee('Bored pile')
        ->assertSee('Blok A')
        ->assertDontSee('Blok B')
        ->assertDontSee('id="location"', false)
        ->assertDontSee('href="#location"', false);

    $html = $response->getContent();
    expect(substr_count($html, 'class="ov is-info pj-sheet"'))->toBe(0)
        ->and(substr_count($html, 'class="roll-host"'))->toBe(5);
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
