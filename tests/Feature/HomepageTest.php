<?php

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
});

test('the homepage shows only the hero and the projects grid', function () {
    $this->get('/')
        ->assertOk()
        // Judul per kata
        ->assertSeeInOrder(['>Hidup</span>', '>Tenang,</span>', '>Dekat</span>', '>Saja.</span>'], false)
        ->assertSee("Kawasan hunian di Jakarta,<br>\ndirancang untuk keluarga yang terus bertumbuh.", false)
        ->assertSee('<section class="hero"', false)
        ->assertDontSee('id="kawasan"', false)
        ->assertDontSee('id="peta"', false)
        ->assertDontSee('id="cluster"', false)
        ->assertDontSee('fac__slide', false)
        ->assertDontSee('id="tipe"', false)
        ->assertDontSee('mp__title', false);
});

test('the header uses the config logos while Globals → Situs has none', function () {
    $this->get('/')->assertOk()->assertSee('src="'.asset('images/masterpiece-logo.png').'"', false);
});

test('the header logos come from Globals → Situs', function () {
    $light = app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo-terang.png', 300, 60), []);
    $dark = app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo-gelap.png', 300, 60), []);
    $set = GlobalSet::query()->where('handle', 'situs')->firstOrFail();
    app(SaveGlobals::class)->handle($set, ['logo_light' => $light->id, 'logo_dark' => $dark->id, 'status_labels' => ['sold' => 'Terjual', 'reserved' => 'Dipesan', 'available' => 'Tersedia']], 'id');

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['class="hd__logo hd__logo--light"', 'src="'.asset($light->url('medium')).'"', 'width="300"', 'height="60"'], false)
        ->assertSeeInOrder(['class="hd__logo hd__logo--dark"', 'src="'.asset($dark->url('medium')).'"'], false)
        ->assertDontSee('images/masterpiece-logo.png', false);
});

test('the hero banner shows the heading, description and background image from the CMS', function () {
    $home = EntryTranslation::query()->where('slug', 'home')->firstOrFail();
    $home->update(['data' => array_replace_recursive($home->data, ['hero' => ['heading' => 'Judul dari CMS', 'description' => 'Deskripsi dari CMS']])]);

    $response = $this->get('/')
        ->assertSeeInOrder(['>Judul</span>', '>dari</span>', '>CMS</span>'], false)
        ->assertSee('Deskripsi dari CMS')
        ->assertSee('data-hero-slides', false)
        ->assertDontSee('class="hero__video"', false);

    expect($home->entry->blueprintHandle())->toBe('homepage')
        ->and(substr_count($response->getContent(), 'class="hero__media"'))->toBe(1);
});

test('a hero video plays over the background image, which becomes its poster', function () {
    $home = EntryTranslation::query()->where('slug', 'home')->firstOrFail();
    $video = app(UploadMedia::class)->handle(UploadedFile::fake()->createWithContent('hero.mp4', "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom"), []);
    $image = Media::query()->findOrFail($home->data['hero']['image']);
    $home->update(['data' => array_replace_recursive($home->data, ['hero' => ['video' => $video->id]])]);

    $this->get('/')
        ->assertSee('class="hero__video"', false)
        ->assertSee('poster="'.asset($image->url()).'"', false)
        ->assertSee('data-src="'.asset($video->url()).'"', false)
        ->assertDontSee('data-hero-slides', false);
});

test('a hero banner without video or image keeps the hardcoded slides', function () {
    $home = EntryTranslation::query()->where('slug', 'home')->firstOrFail();
    $home->update(['data' => array_replace($home->data, ['hero' => [...$home->data['hero'], 'video' => null, 'image' => null]])]);

    $response = $this->get('/')
        ->assertSee('preload="none" data-src="'.asset('media/hero/teaser.mp4').'"', false);

    expect(substr_count($response->getContent(), 'class="hero__media"'))->toBe(4);
});

test('the homepage lists every published project with its card image and hero description', function () {
    $projects = Collection::query()->where('handle', 'projects')->firstOrFail();
    $card = app(UploadMedia::class)->handle(UploadedFile::fake()->image('kartu.jpg', 40, 30), []);
    $logo = app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo.png', 40, 40), []);
    $project = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => 'westlake', 'locale' => 'id', 'data' => [
        'card' => ['image' => $card->id, 'description' => 'Ruko di tepi danau.'],
        'global_style' => ['active' => false, 'logo' => $logo->id, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
        'hero' => ['heading' => "A New Landmark\nby the Lake"],
    ]]);
    app(PublishEntry::class)->handle($project->fresh());

    $this->get('/')
        ->assertOk()
        ->assertSee('<body class="is-home"', false)
        ->assertSeeInOrder(['<a class="hero__scroll" href="#projects" aria-label="Gulir ke bagian berikutnya">', 'class="hero__lead"'], false)
        ->assertSeeInOrder(['<section class="hero"', '<section id="projects" class="prj"', '>Proyek</h2>'], false)
        ->assertSee('<a class="prj__card" href="'.url('/projects/westlake').'">', false)
        ->assertSee($card->url('large'), false)
        ->assertSee('<span class="prj__logo"><img src="'.$logo->url('medium').'" alt=""', false)
        ->assertSee('<h3 class="prj__title">Westlake</h3>', false)
        ->assertDontSee('A New Landmark')
        ->assertSee('<p class="prj__desc">Ruko di tepi danau.</p>', false);
});

test('the hero and projects grid take their CSS IDs from the CMS, with or without #', function () {
    $home = EntryTranslation::query()->where('slug', 'home')->firstOrFail();
    $home->update(['data' => array_replace_recursive($home->data, ['hero' => ['css_id' => 'Beranda Utama'], 'projects' => ['css_id' => '#proyek']])]);
    $projects = Collection::query()->where('handle', 'projects')->firstOrFail();
    $project = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => 'westlake', 'locale' => 'id', 'data' => [
        'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
        'hero' => ['heading' => 'Westlake'],
    ]]);
    app(PublishEntry::class)->handle($project->fresh());

    $this->get('/')
        ->assertOk()
        ->assertSee('<section class="hero"  id="beranda-utama"', false)
        ->assertSee('<section id="proyek" class="prj" aria-labelledby="proyek-title">', false)
        ->assertSee('href="#proyek" aria-label="Gulir ke bagian berikutnya"', false)
        ->assertDontSee('id="projects"', false);
});

test('the homepage has no projects grid before a project is published', function () {
    $this->get('/')->assertOk()->assertDontSee('id="projects"', false)->assertDontSee('hero__scroll', false);
});

test('the projects grid takes its title and description from the CMS', function () {
    $home = EntryTranslation::query()->where('slug', 'home')->firstOrFail();
    $home->update(['data' => array_replace($home->data, ['projects' => ['title' => 'Proyek kami', 'description' => 'Intro proyek dari CMS']])]);
    $projects = Collection::query()->where('handle', 'projects')->firstOrFail();
    $project = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => 'westlake', 'locale' => 'id', 'data' => [
        'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
        'hero' => ['heading' => 'Westlake'],
    ]]);
    app(PublishEntry::class)->handle($project->fresh());

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['id="projects-title"', '>Proyek kami</h2>'], false)
        ->assertSee('<p class="sheet__intro">Intro proyek dari CMS</p>', false)
        ->assertSee('<h3 class="prj__title">Westlake</h3>', false)
        ->assertDontSee('prj__desc', false)
        ->assertDontSee('prj__logo', false);
});

test('a type page without a project shows its title, specs, floor plans, gallery and VR in one sheet, without the 360° viewer', function () {
    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSee('<h1 class="display tph__name" id="type-name">Tipe 6</h1>', false)
        ->assertDontSee('data-turntable', false)
        ->assertDontSee('data-parts', false)
        ->assertSeeInOrder(['<dl class="tps__facts">', '<dt>LT</dt>', '140 m²', '<dt>LB</dt>', '240 m²'], false)
        ->assertSee('<dl class="tps__specs" aria-label="Spesifikasi">', false)
        ->assertSee('<section class="tpd" aria-label="Denah" data-view-cursor>', false)
        ->assertSee('data-view-target', false)
        ->assertSee('Denah Lantai 1')
        ->assertSee('Tipe 6: Galeri')
        ->assertSee('>Galeri</button>', false)
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

test('a type page without land and building area leaves them out instead of showing 0 m²', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-6')->firstOrFail();
    $type->update(['data' => [...$type->data, 'lt' => null, 'lb' => null]]);

    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSeeInOrder(['<dl class="tps__facts">', '<dt>Lantai</dt>'], false)
        ->assertDontSee('<dt>LT</dt>', false)
        ->assertDontSee('<dt>LB</dt>', false)
        ->assertDontSee('Luas tanah')
        ->assertDontSee('0 m²');
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

test('a type page shows its building specs and note in their own section after the floor plans', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-6')->firstOrFail();
    $type->update(['data' => [...$type->data, 'building_specs' => [['label' => 'Pondasi', 'value' => 'Mini pile'], ['label' => 'Listrik', 'value' => '3500 VA']], 'building_specs_note' => '*Spesifikasi dapat berubah.']]);

    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSeeInOrder(['<section class="tpd"', '<section class="tpb" aria-labelledby="build-title">', '>Spesifikasi bangunan</h2>', '<dt>Pondasi</dt>', '<dd>Mini pile</dd>', '<dt>Listrik</dt>', '<dd>3500 VA</dd>', '<p class="tpb__note">*Spesifikasi dapat berubah.</p>'], false);
});

test('the building specs heading comes from the CMS, or Spesifikasi bangunan without one', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-6')->firstOrFail();
    $type->update(['data' => [...$type->data, 'building_specs' => [['label' => 'Pondasi', 'value' => 'Mini pile']], 'building_specs_title' => 'Material bangunan']]);
    $this->get('/-/tipe/tipe-6')->assertOk()->assertSee('id="build-title">Material bangunan</h2>', false);

    $type->update(['data' => [...$type->fresh()->data, 'building_specs_title' => '']]);
    $this->get('/-/tipe/tipe-6')->assertOk()->assertSee('id="build-title">Spesifikasi bangunan</h2>', false);
});

test('a type page without building specs has no specs section', function () {
    $this->get('/-/tipe/tipe-6')->assertOk()->assertDontSee('class="tpb"', false);
});

test('a type page slides its floor plans when it has more than one', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-6')->firstOrFail();
    expect(count($type->data['floor_plans']))->toBeGreaterThan(1);

    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSee('class="tpd__frame swiper"', false)
        ->assertSee('data-plans-swiper', false)
        ->assertSee('<li class="swiper-slide">', false)
        ->assertSee('data-plans-prev aria-label="Denah sebelumnya"', false)
        ->assertSee('data-plans-next aria-label="Denah berikutnya"', false);
});

test('a type page with one floor plan shows it without a slider', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-6')->firstOrFail();
    $type->update(['data' => [...$type->data, 'floor_plans' => array_slice($type->data['floor_plans'], 0, 1)]]);

    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSee('class="tpd__frame"', false)
        ->assertDontSee('data-plans-swiper', false)
        ->assertDontSee('data-plans-prev', false);
});

test('a type page renders its intro as rich text with headings and lists, without scripts', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-6')->firstOrFail();
    $type->update(['data' => [...$type->data, 'intro' => '<h2>Keunggulan</h2><ul><li>Dua akses</li></ul><ol><li>Satu</li></ol><script>alert(1)</script>']]);

    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSee('<div class="tps__intro"><h2>Keunggulan</h2><ul><li>Dua akses</li></ul><ol><li>Satu</li></ol></div>', false)
        ->assertDontSee('alert(1)', false);
});

test('a type page shows an old plain text intro as a paragraph', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-6')->firstOrFail();
    $type->update(['data' => [...$type->data, 'intro' => "Baris satu\nBaris dua & tiga"]]);

    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSee('<div class="tps__intro"><p>Baris satu<br />', false)
        ->assertSee('Baris dua &amp; tiga</p></div>', false);
});

test('a type page adds the extra summary data after the rooms', function () {
    $type = EntryTranslation::query()->where('slug', 'tipe-6')->firstOrFail();
    $type->update(['data' => [...$type->data, 'summary_extra' => [['label' => 'Carport', 'value' => '1']]]]);

    $this->get('/-/tipe/tipe-6')
        ->assertOk()
        ->assertSeeInOrder(['<dl class="tps__facts">', '<dt>KM</dt>', '<dt>Carport</dt>', '<dd>1</dd>', '</dl>'], false);
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
