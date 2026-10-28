{{-- Kerangka halaman --}}
@props(['title' => null, 'description' => null, 'bodyClass' => '', 'entry' => null, 'favicon' => null])

@php($favicon ??= $site['favicon'])

<!doctype html>
<html lang="{{ $site['lang'] }}" class="no-js">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>{{ $title ? "{$title} | {$site['fullName']}" : $site['fullName'] }}</title>
    <meta name="description" content="{{ $description ?? $site['description'] }}" />
    <meta name="theme-color" content="#0b0d0f" />
    @if ($favicon)
      <link rel="icon" href="{{ $favicon['href'] }}" type="{{ $favicon['type'] }}" />
    @else
      <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' fill='%230b0d0f'/%3E%3Crect x='0' y='28' width='32' height='4' fill='%2334425e'/%3E%3Ctext x='16' y='21' text-anchor='middle' font-family='Arial,sans-serif' font-weight='700' font-size='14' fill='%23f3f4f2'%3EDR%3C/text%3E%3C/svg%3E" />
    @endif
    <script>document.documentElement.classList.remove('no-js');</script>
    @vite(array_filter(['resources/css/app.css', 'resources/js/app.js', $entry]))
    {{ $head ?? '' }}
  </head>
  <body @class([$bodyClass]) @if ($site['protectMedia']) data-protect-media @endif>
    {{ $slot }}
    <x-overlay />
  </body>
</html>
