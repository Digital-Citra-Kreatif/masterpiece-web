{{--
  Unit update: siteplan image with a status dot per unit (red sold, yellow
  reserved, green available). Status display only; dots do not open units.
  Clicking a legend status shows only that status's dots; clicking it again
  shows all (resources/js/unit-filter.js).
  Dot positions are percentages of the siteplan image (Units in the CMS).
  `siteplan` ({ image, width, height, placeholder }): siteplan proyek

  Unlike the other sections, the heading sits in the side column above the
  legend, so the siteplan starts at the top and is the first thing read.
--}}
@props(['units', 'counts', 'heading', 'id' => 'unit-update', 'siteplan' => null])

@php($siteplan ??= $site['siteplan'])

<section id="{{ $id }}" class="units" data-unit-filter aria-labelledby="unit-title">
  <div class="wrap">
    <div class="units__layout">
      <header class="units__head" data-reveal>
        <h2 class="display units__title" id="unit-title">{{ $heading['title'] }}</h2>
        @if ($heading['intro'])
          <p class="units__intro">{{ $heading['intro'] }}</p>
        @endif
      </header>

      <div class="units__plan" data-reveal>
        <div class="units__frame" style="aspect-ratio: {{ $siteplan['width'] }} / {{ $siteplan['height'] }}">
          <img src="{{ asset($siteplan['image']) }}" alt="Siteplan cluster" loading="lazy" />
          <ul class="units__dots" aria-label="Status unit">
            @foreach ($units as $u)
              <li
                class="dot dot--{{ $u['status'] }}"
                data-status="{{ $u['status'] }}"
                style="left: {{ $u['x'] }}%; top: {{ $u['y'] }}%"
                title="{{ $u['id'] }} · {{ $site['statusLabels'][$u['status']] }}"
              >
                <span class="visually-hidden">{{ $u['id'] }}: {{ $site['statusLabels'][$u['status']] }}</span>
              </li>
            @endforeach
          </ul>
          @if ($site['showPlaceholderBadges'] && $siteplan['placeholder'])
            <span class="badge units__badge">Siteplan contoh</span>
          @endif
        </div>
      </div>

      <aside class="units__legend" data-reveal>
        <ul>
          @foreach ($counts as $status => $count)
            <li>
              <button class="units__filter" type="button" data-filter="{{ $status }}" aria-pressed="false">
                <span class="dot dot--{{ $status }} dot--static" aria-hidden="true"></span>
                <span class="units__label">{{ $site['statusLabels'][$status] }}</span>
                <span class="units__count display">{{ $count }}</span>
              </button>
            </li>
          @endforeach
        </ul>
        <div class="units__bar" aria-hidden="true">
          @foreach ($counts as $status => $count)
            <span class="units__seg units__seg--{{ $status }}" style="flex: {{ $count }}"></span>
          @endforeach
        </div>
      </aside>
    </div>
  </div>
</section>
