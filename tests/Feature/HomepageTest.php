<?php

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
});

test('the homepage shows every section from the CMS', function () {
    $response = $this->get('/')->assertOk();

    $response
        // Judul per kata, baris tetap
        ->assertSeeInOrder(['>Quiet</span>', '>Living,</span>', '<br>', '>Close</span>', '>Everything.</span>'], false)
        ->assertSee("A residential cluster in Jakarta,<br>\ndesigned for growing families.", false)
        ->assertSee('<section class="hero"', false)
        ->assertSee('<h2 class="display mp__title" data-mp-title>Masterplan</h2>', false)
        ->assertSee('data-zoom-end', false)
        ->assertSee('class="mp__img scene__video"', false)
        ->assertDontSee('scene__meter', false)
        ->assertDontSee('Gulir untuk mengelilingi kawasan')
        ->assertSee('<h2 class="display scene__area-title" id="kawasan-title">Masterplan</h2>', false)
        ->assertSee('id="peta-title"', false)
        ->assertSee('id="cluster-title"', false)
        ->assertSee('Danau &amp; promenade', false)
        ->assertSee('Plaza &amp; boulevard ruko', false)
        ->assertSee(url('/-/tipe/tipe-1'))
        ->assertSee(url('/-/tipe/tipe-6'));

    expect(substr_count($response->getContent(), '<li class="fac__slide">'))->toBe(6)
        ->and(substr_count($response->getContent(), 'class="card"'))->toBe(6)
        // Unit kini milik proyek
        ->and(substr_count($response->getContent(), 'style="left: '))->toBe(0);
});

test('the hero banner shows the heading, description and media from the CMS', function () {
    $home = EntryTranslation::query()->where('slug', 'home')->firstOrFail();
    $home->update(['data' => array_replace_recursive($home->data, ['hero' => ['heading' => 'Judul dari CMS', 'description' => 'Deskripsi dari CMS']])]);

    $response = $this->get('/')
        ->assertSeeInOrder(['>Judul</span>', '>dari</span>', '>CMS</span>'], false)
        ->assertSee('Deskripsi dari CMS')
        ->assertSee('data-hero-slides', false)
        ->assertDontSee('class="hero__video"', false);

    expect($home->entry->blueprintHandle())->toBe('homepage')
        ->and(substr_count($response->getContent(), 'class="hero__media"'))->toBe(3);
});

test('a hero banner without media keeps the hardcoded slides', function () {
    $home = EntryTranslation::query()->where('slug', 'home')->firstOrFail();
    $home->update(['data' => array_replace($home->data, ['hero' => [...$home->data['hero'], 'media' => []]])]);

    $response = $this->get('/')
        ->assertSee('preload="none" data-src="'.asset('media/hero/teaser.mp4').'"', false);

    expect(substr_count($response->getContent(), 'class="hero__media"'))->toBe(4);
});

test('each section shows its title and description from the CMS, or the defaults without a title', function () {
    $home = EntryTranslation::query()->where('slug', 'home')->firstOrFail();
    $home->update(['data' => array_replace($home->data, [
        'masterplan' => ['title' => 'Rencana induk', 'description' => 'Teks masterplan dari CMS'],
        'lokasi' => ['title' => 'Lokasi proyek', 'description' => 'Intro lokasi dari CMS'],
        'cluster' => ['title' => 'Cluster ruko', 'description' => 'Intro cluster dari CMS'],
        'fasilitas' => ['title' => 'Fasilitas kawasan', 'description' => 'Intro fasilitas dari CMS'],
        'tipe' => ['title' => 'Tipe unit', 'description' => 'Intro tipe dari CMS'],
        'unit_update' => ['title' => '', 'description' => 'Diabaikan tanpa judul'],
    ])]);

    $this->get('/')
        ->assertSee('<h2 class="display mp__title" data-mp-title>Rencana induk</h2>', false)
        ->assertSee('Teks masterplan dari CMS')
        ->assertSee('Lokasi proyek')
        ->assertSee('Intro lokasi dari CMS')
        ->assertSee('<h2 class="display scene__area-title" id="cluster-title">Cluster ruko</h2>', false)
        ->assertSee('Intro cluster dari CMS')
        ->assertSee('Fasilitas kawasan')
        ->assertSee('Intro fasilitas dari CMS')
        ->assertSee('Intro tipe dari CMS')
        ->assertDontSee('class="units"', false)
        ->assertDontSee('Diabaikan tanpa judul');
});

test('a type page without a project shows its title, specs, floor plans, gallery and VR in one sheet, without the 360° viewer', function () {
    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSee('<h1 class="display tph__name" id="type-name">Tipe 6</h1>', false)
        ->assertDontSee('data-turntable', false)
        ->assertDontSee('data-parts', false)
        ->assertSeeInOrder(['<dl class="tps__facts">', '<dt>LT</dt>', '140 m²', '<dt>LB</dt>', '240 m²'], false)
        ->assertSee('<dl class="tps__specs" aria-label="Spesifikasi">', false)
        ->assertSee('<section class="tpd" aria-label="Denah">', false)
        ->assertSee('Denah Lantai 1')
        ->assertSee('Tipe 6: Galeri')
        ->assertSee('>Gallery</button>', false)
        ->assertSee('href="'.asset('/vr/placeholder/index.html').'" target="_blank" rel="noopener"', false)
        ->assertDontSee('tpg__run', false);
});

test('a type page shows its size under the label and its floors in the summary', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-6')->firstOrFail();
    $type->update(['data' => [...$type->data, 'label' => 'Ruko Hook', 'dimensions' => '4,5 x 10 m']]);

    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSeeInOrder(['<p class="eyebrow tps__label">Ruko Hook</p>', '<p class="display tps__size">4,5 x 10 m</p>', '<dl class="tps__facts">'], false)
        ->assertSeeInOrder(['<dt>LB</dt>', '<dt>Lantai</dt>', '<dd>3</dd>', '<dt>KT</dt>'], false);
});

test('a type page lists the businesses its view suits, under the intro', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-6')->firstOrFail();
    $type->update(['data' => [...$type->data, 'suitable_title' => 'Cocok untuk usaha yang butuh eksposur maksimal', 'suitable_for' => [['name' => 'Klinik'], ['name' => 'Apotek']]]]);

    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSeeInOrder(['class="tps__intro"', '<p class="tps__uses-title" id="uses-title">Cocok untuk usaha yang butuh eksposur maksimal</p>', '<li>Klinik</li>', '<li>Apotek</li>', 'class="tps__specs"'], false);
});

test('a type page without businesses has no suitable list', function () {
    $this->get('/-/tipe/tipe-6')->assertOk()->assertDontSee('tps__uses', false);
});

test('an unknown address shows the site\'s not found page', function () {
    $this->get('/-/tipe/tidak-ada')
        ->assertNotFound()
        ->assertSee('<h1 class="nf__title">Halaman tidak ditemukan</h1>', false)
        ->assertSee('href="/"', false)
        ->assertSee('Kembali ke beranda');
});

test('the not found page shows the title, description and link from Globals → 404', function () {
    app(SaveGlobals::class)->handle(GlobalSet::query()->where('handle', 'not_found')->firstOrFail(), [
        'heading' => 'Ups, salah alamat',
        'description' => 'Coba lihat proyek kami.',
        'link' => ['type' => 'url', 'url' => '/projects', 'label' => 'Lihat proyek', 'new_tab' => false],
    ], 'id');

    $this->get('/tidak-ada')
        ->assertNotFound()
        ->assertSee('<h1 class="nf__title">Ups, salah alamat</h1>', false)
        ->assertSee('Coba lihat proyek kami.')
        ->assertSee('href="/projects"', false)
        ->assertSee('Lihat proyek')
        ->assertDontSee('Kembali ke beranda');
});

test('the home header has no menu while the CMS menu has no links', function () {
    $this->get('/')
        ->assertDontSee('class="hd__nav"', false)
        ->assertDontSee('data-menu-toggle', false)
        ->assertDontSee('id="hd-menu"', false);
});

test('the home header shows the CMS menu', function () {
    $menu = Menu::query()->create(['handle' => 'primary_menu', 'title' => 'Primary Menu']);
    app(SaveMenu::class)->handle($menu, [
        ['url' => '/kontak', 'labels' => ['id' => 'Kontak']],
        ['url' => 'https://example.com', 'labels' => ['id' => 'Brosur'], 'new_tab' => true],
    ]);

    $this->get('/')
        ->assertSee('Kontak')
        ->assertSee('href="/kontak"', false)
        ->assertSee('target="_blank" rel="noopener"', false)
        ->assertSee('aria-controls="hd-menu"', false);
});

test('the browser icon is the built-in one while Settings → Branding has none', function () {
    $this->get('/')->assertSee('<link rel="icon" href="data:image/svg+xml,', false);
});

test('the browser icon is the one chosen in Settings → Branding', function () {
    $favicon = app(UploadMedia::class)->handle(UploadedFile::fake()->image('favicon.png', 32, 32));
    app(BrandingSettings::class)->fill(['favicon' => $favicon->id])->save();

    $this->get('/')
        ->assertSee('<link rel="icon" href="'.e($favicon->url()).'" type="image/png" />', false)
        ->assertDontSee('<link rel="icon" href="data:image/svg+xml,', false);
});
