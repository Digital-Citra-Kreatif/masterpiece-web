{{--
  Unit types: 3 cards per row. A card links to its type page (`url`, the
  homepage: /tipe/<slug>) or opens its sheet on the same page (`sheet`, a
  <dialog> id, project pages). Its facts are `specs` ([[label, value], …]) or,
  without them, the homepage's LT, LB and rooms.

  variant 'project' (after design-project.pdf): the title in capitals, white
  card bodies with the size beside the name and the facts in one row.
  Slots `before` and `after` hold the section's other content around the grid.
--}}
@props(['types', 'heading', 'id' => 'tipe', 'variant' => 'default', 'before' => null, 'after' => null])

@php($project = $variant === 'project')

<section id="{{ $id }}" @class(['types', 'types--project' => $project]) aria-labelledby="{{ $id }}-title">
  <div class="wrap">
    <x-sheet-header
      id="{{ $id }}-title"
      :title="$heading['title']"
      :intro="$heading['intro']"
    />

    {{ $before }}

    <ul class="types__grid">
      @foreach ($types as $t)
        @php($specs = $t['specs'] ?? [['LT', "{$t['lt']} m²"], ['LB', "{$t['lb']} m²"], ['K. tidur', $t['bedrooms']], ['K. mandi', $t['bathrooms']]])
        <li data-reveal style="transition-delay: {{ ($loop->index % 3) * 90 }}ms">
          <{{ isset($t['sheet']) ? 'article' : 'a' }} class="card" @unless (isset($t['sheet'])) href="{{ $t['url'] }}" @endunless>
            <div class="card__img">
              @if ($t['image'])
                <img src="{{ asset($t['image']) }}" alt="{{ $t['name'] }}{{ $t['label'] ? ", {$t['label']}" : '' }}" loading="lazy" />
              @endif
              <span class="card__open" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M7 17L17 7M9 7h8v8" /></svg>
              </span>
              @if ($site['showPlaceholderBadges'] && ($t['placeholder'] ?? false))
                <span class="badge card__badge">Data contoh</span>
              @endif
            </div>
            <div class="card__body">
              <div class="card__head">
                <h3 class="card__name display">
                  @isset($t['sheet'])
                    <button class="card__sheet" type="button" data-sheet="{{ $t['sheet'] }}" aria-haspopup="dialog">{{ $t['name'] }}</button>
                  @else
                    {{ $t['name'] }}
                  @endisset
                </h3>
                @if ($project && ($t['size'] ?? null))
                  <span class="card__size">{{ $t['size'] }}</span>
                @elseif ($t['label'])
                  <span class="card__label eyebrow">{{ $t['label'] }}</span>
                @endif
              </div>
              @if ($specs !== [])
                <dl class="card__specs">
                  @foreach ($specs as [$label, $value])
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                  @endforeach
                </dl>
              @endif
            </div>
          </{{ isset($t['sheet']) ? 'article' : 'a' }}>
        </li>
      @endforeach
    </ul>

    {{ $after }}
  </div>
</section>
