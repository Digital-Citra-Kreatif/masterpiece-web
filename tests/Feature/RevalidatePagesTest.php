<?php

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
});

test('site pages tell the browser and LiteSpeed to check again on every visit', function () {
    $response = $this->get('/')->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-cache')->toContain('must-revalidate')->toContain('private')
        ->and($response->headers->get('X-LiteSpeed-Cache-Control'))->toBe('no-cache')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

test('an unchanged page answers 304 to the ETag the browser kept, a changed one sends the page again', function () {
    $etag = $this->get('/')->headers->get('ETag');

    $this->get('/', ['If-None-Match' => $etag])->assertStatus(304)->assertContent('');
    $this->get('/', ['If-None-Match' => '"stale"'])->assertOk()->assertSee('<html', false);
});

test('responses that are not html keep their own cache headers', function () {
    Route::middleware('web')->get('/revalidate-json', fn () => response()->json(['ok' => true])->setPublic()->setMaxAge(60));

    $response = $this->get('/revalidate-json')->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('max-age=60')
        ->and($response->headers->has('X-LiteSpeed-Cache-Control'))->toBeFalse()
        ->and($response->headers->has('ETag'))->toBeFalse();
});
