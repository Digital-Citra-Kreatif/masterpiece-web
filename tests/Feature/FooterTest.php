<?php

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Structure\StructureImporter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
});

test('the footer shows its logo and the copyright, without a menu', function () {
    app(StructureImporter::class)->import(['menus' => [['handle' => 'footer_menu', 'title' => 'Footer Menu', 'items' => [
        ['url' => '/projects', 'labels' => ['id' => 'Projects'], 'new_tab' => false, 'children' => []],
    ]]]]);

    $html = $this->get('/')
        ->assertOk()
        ->assertSee('class="ft__logo"', false)
        ->assertDontSee('aria-label="Menu footer"', false)
        ->assertSee('© '.date('Y').' PT Masterpiece Visual Production')
        ->assertDontSee('>Kontak</h2>', false)
        ->getContent();

    expect(substr_count($html, '<footer class="ft">'))->toBe(1);
});

test('the footer shows the contacts and opening hours filled in under Globals → Footer', function () {
    $set = GlobalSet::query()->where('handle', 'footer')->firstOrFail();
    app(SaveGlobals::class)->handle($set, [
        'description' => 'Pengembang kawasan ruko.',
        'address' => "Jl. BSD Raya\nTangerang",
        'hours' => "Senin–Jumat, 09.00–17.00\nSabtu, 10.00–14.00",
        'phone' => '+62 21 555 0100',
        'email' => 'info@example.com',
    ], 'id');

    $this->get('/')
        ->assertOk()
        ->assertSee('Pengembang kawasan ruko.')
        ->assertSee("Jl. BSD Raya<br>\nTangerang", false)
        ->assertSee('href="tel:+62215550100"', false)
        ->assertSee('href="mailto:info@example.com"', false)
        ->assertSee('<h2 class="ft__head">Jam operasional</h2>', false)
        ->assertSee("Senin–Jumat, 09.00–17.00<br>\nSabtu, 10.00–14.00", false)
        ->assertDontSee('wa.me')
        ->assertDontSee('ft__cta', false);
});

test('the footer column headings come from Globals → Footer, or the defaults without them', function () {
    $set = GlobalSet::query()->where('handle', 'footer')->firstOrFail();
    app(SaveGlobals::class)->handle($set, [
        'address' => 'Jl. BSD Raya',
        'hours' => 'Senin–Jumat, 09.00–17.00',
        'contact_title' => 'Hubungi kami',
        'hours_title' => 'Jam buka',
    ], 'id');

    $this->get('/')
        ->assertOk()
        ->assertSee('<h2 class="ft__head">Hubungi kami</h2>', false)
        ->assertSee('<h2 class="ft__head">Jam buka</h2>', false)
        ->assertDontSee('<h2 class="ft__head">Kontak</h2>', false);
});

test('a project page footer carries the project name in its copyright', function () {
    $projects = Collection::query()->where('handle', 'projects')->firstOrFail();
    $translation = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => 'westlake', 'locale' => 'id', 'data' => [
        'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
        'hero' => ['heading' => 'Westlake'],
    ]]);
    app(PublishEntry::class)->handle($translation->fresh());

    $this->get('/projects/westlake')
        ->assertOk()
        ->assertSee('<p class="ft__meta">© '.date('Y').' Westlake</p>', false);
});

test('a project footer uses the fields filled in on the project and falls back to Globals → Footer for the empty ones', function () {
    app(SaveGlobals::class)->handle(GlobalSet::query()->where('handle', 'footer')->firstOrFail(), [
        'description' => 'Deskripsi global.',
        'address' => 'Alamat global',
        'hours' => 'Jam global',
        'phone' => '+62 21 555 0100',
        'email' => 'global@example.com',
    ], 'id');
    $projects = Collection::query()->where('handle', 'projects')->firstOrFail();
    $publish = function (string $slug, array $footer) use ($projects): void {
        $translation = app(CreateEntry::class)->handle($projects, ['title' => 'Westlake', 'slug' => $slug, 'locale' => 'id', 'data' => [
            'global_style' => ['active' => false, 'marker' => ['type' => 'shape', 'shape' => 'circle']],
            'hero' => ['heading' => 'Westlake'],
            'footer' => $footer,
        ]]);
        app(PublishEntry::class)->handle($translation->fresh());
    };

    $publish('sebagian', ['description' => 'Deskripsi proyek.', 'phone' => '+62 811 000 111']);
    $this->get('/projects/sebagian')
        ->assertOk()
        ->assertSee('Deskripsi proyek.')
        ->assertDontSee('Deskripsi global.')
        ->assertSee('href="tel:+62811000111"', false)
        ->assertDontSee('href="tel:+62215550100"', false)
        // Kosong di proyek: global
        ->assertSee('Alamat global')
        ->assertSee('Jam global')
        ->assertSee('href="mailto:global@example.com"', false);

    $publish('penuh', ['description' => 'Deskripsi proyek.', 'address' => 'Alamat proyek', 'hours' => 'Jam proyek', 'phone' => '+62 811 000 111', 'email' => 'proyek@example.com']);
    $this->get('/projects/penuh')
        ->assertOk()
        ->assertSee('Alamat proyek')
        ->assertSee('Jam proyek')
        ->assertSee('href="mailto:proyek@example.com"', false)
        ->assertDontSee('Alamat global')
        ->assertDontSee('Jam global')
        ->assertDontSee('global@example.com');

    // Beranda tetap global
    $this->get('/')
        ->assertOk()
        ->assertSee('Deskripsi global.')
        ->assertSee('Alamat global')
        ->assertDontSee('Deskripsi proyek.');
});
