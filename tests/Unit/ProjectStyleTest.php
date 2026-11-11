<?php

use App\Support\ProjectPage;

/**
 * Global Style aktif
 *
 * @param  array<string, mixed>  $style
 * @return array{css: string, logo: ?array<string, mixed>, favicon: ?array{href: string, type: string}, marker: ?array{shape: ?string, image: ?string}}
 */
function activeStyle(array $style = []): array
{
    return ProjectPage::style(['active' => true, ...$style]);
}

test('an inactive global style adds no CSS, logo, favicon or marker', function () {
    expect(ProjectPage::style(['active' => false, 'palette' => ['color_1' => '#112233']]))
        ->toBe(['css' => '', 'logo' => null, 'favicon' => null, 'marker' => null])
        ->and(ProjectPage::style([]))->toBe(['css' => '', 'logo' => null, 'favicon' => null, 'marker' => null]);
});

test('each palette color lands on its own CSS token, in lower case', function () {
    $css = activeStyle(['palette' => ['color_1' => '#AABBCC', 'color_7' => '#0F3D34', 'color_8' => '#ffffff']])['css'];

    expect($css)
        ->toContain('--accent:#aabbcc;')
        ->toContain('--room:#0f3d34;')
        ->toContain('--room-ink:#ffffff;')
        ->not->toContain('--paper:');
});

test('a palette value that is not a six digit hex color is left out', function (mixed $value) {
    expect(activeStyle(['palette' => ['color_1' => $value]])['css'])->not->toContain('--accent');
})->with([
    'named color' => 'red',
    'short hex' => '#abc',
    'without hash' => 'aabbcc',
    'css injection' => '#aabbcc;}body{display:none',
    'number' => 123456,
    'null' => null,
]);

test('a rounded corner writes its size as the radius, without a chamfer', function () {
    $css = activeStyle(['corner' => ['style' => 'rounded', 'size' => 12]])['css'];

    expect($css)
        ->toContain('--radius:12px;')
        ->not->toContain('--frame-clip')
        ->not->toContain('--chamfer');
});

test('a chamfered corner has no radius and cuts frames by its size, at most 24px', function (int $size, string $cut) {
    $css = activeStyle(['corner' => ['style' => 'chamfer', 'size' => $size]])['css'];

    expect($css)
        ->toContain('--radius:0px;')
        ->toContain("--chamfer:{$cut};")
        ->toContain("--frame-clip:polygon({$cut} 0, calc(100% - {$cut}) 0,")
        ->toContain("--logo-clip:polygon(0 0, 100% 0, 100% calc(100% - {$cut}),");
})->with([
    'small' => [8, '8px'],
    'at the cap' => [24, '24px'],
    'above the cap' => [40, '24px'],
]);

test('a missing, negative or non numeric corner size means no radius and no chamfer', function (mixed $size) {
    $css = activeStyle(['corner' => ['style' => 'chamfer', 'size' => $size]])['css'];

    expect($css)->toContain('--radius:0px;')->not->toContain('--frame-clip');
})->with(['missing' => null, 'negative' => -5, 'text' => 'besar', 'zero' => 0]);

test('a Google Font embed sets the font token and puts its @import before everything else', function () {
    $css = activeStyle([
        'palette' => ['color_1' => '#112233'],
        'fonts' => ['heading_embed' => '<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&amp;display=swap" rel="stylesheet">'],
    ])['css'];

    expect($css)
        ->toStartWith('@import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap");')
        ->toContain('--display:"Playfair Display", ')
        ->and(strpos($css, '@import'))->toBeLessThan(strpos($css, ':root{'));
});

test('each font field has its own token', function () {
    $embed = fn (string $family): string => "https://fonts.googleapis.com/css2?family={$family}&display=swap";
    $css = activeStyle(['fonts' => [
        'heading_embed' => $embed('Lora'),
        'description_embed' => $embed('Inter'),
        'button_embed' => $embed('Manrope'),
    ]])['css'];

    expect($css)
        ->toContain('--display:"Lora", ')
        ->toContain('--sans:"Inter", ')
        ->toContain('--button:"Manrope", ');
});

test('a font embed from another host, or with an unsafe family name, is ignored', function (mixed $embed) {
    $css = activeStyle(['fonts' => ['heading_embed' => $embed]])['css'];

    expect($css)->not->toContain('@import')->not->toContain('--display');
})->with([
    'other host' => 'https://example.com/css2?family=Lora',
    'lookalike host' => 'https://fonts.googleapis.com.evil.test/css2?family=Lora',
    'no family' => 'https://fonts.googleapis.com/css2?display=swap',
    'unsafe family' => 'https://fonts.googleapis.com/css2?family=Lora%22%3B%7D',
    'not a string' => [['https://fonts.googleapis.com/css2?family=Lora']],
    'empty' => '',
]);

test('the marker keeps a known shape and drops an unknown one', function () {
    expect(activeStyle(['marker' => ['type' => 'shape', 'shape' => 'diamond']])['marker'])->toBe(['shape' => 'diamond', 'image' => null])
        ->and(activeStyle(['marker' => ['type' => 'shape', 'shape' => 'pin']])['marker'])->toBe(['shape' => 'pin', 'image' => null])
        ->and(activeStyle(['marker' => ['type' => 'shape', 'shape' => 'star']])['marker'])->toBeNull()
        ->and(activeStyle()['marker'])->toBeNull();
});

test('the palette and font maps cover the tokens the stylesheet reads', function () {
    expect(ProjectPage::PALETTE)->toHaveCount(8)
        ->and(array_values(ProjectPage::PALETTE))->toBe(['--accent', '--accent-ink', '--accent-light', '--paper', '--paper-2', '--ink', '--room', '--room-ink'])
        ->and(ProjectPage::FONTS)->toBe(['heading' => '--display', 'description' => '--sans', 'button' => '--button']);
});
