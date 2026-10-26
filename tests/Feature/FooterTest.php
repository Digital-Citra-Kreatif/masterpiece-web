<?php

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Structure\StructureImporter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
});

test('the footer shows its logo, the footer menu and the copyright', function () {
    app(StructureImporter::class)->import(['menus' => [['handle' => 'footer_menu', 'title' => 'Footer Menu', 'items' => [
        ['url' => '/projects', 'labels' => ['id' => 'Projects'], 'new_tab' => false, 'children' => []],
    ]]]]);

    $html = $this->get('/')
        ->assertOk()
        ->assertSee('class="ft__logo"', false)
        ->assertSee('aria-label="Menu footer"', false)
        ->assertSee('>Projects</a>', false)
        ->assertSee('© '.date('Y').' PT Masterpiece Visual Production')
        ->assertDontSee('>Kontak</h2>', false)
        ->getContent();

    expect(substr_count($html, '<footer class="ft room">'))->toBe(1);
});

test('the footer shows the contacts filled in under Globals → Footer', function () {
    $set = GlobalSet::query()->where('handle', 'footer')->firstOrFail();
    app(SaveGlobals::class)->handle($set, [
        'description' => 'Pengembang kawasan ruko.',
        'address' => "Jl. BSD Raya\nTangerang",
        'phone' => '+62 21 555 0100',
        'email' => 'info@example.com',
        'whatsapp' => '6281234567890',
    ], 'id');

    $this->get('/')
        ->assertOk()
        ->assertSee('Pengembang kawasan ruko.')
        ->assertSee("Jl. BSD Raya<br>\nTangerang", false)
        ->assertSee('href="tel:+62215550100"', false)
        ->assertSee('href="mailto:info@example.com"', false)
        ->assertSee('href="https://wa.me/6281234567890"', false);
});
