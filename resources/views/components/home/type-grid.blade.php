{{--
  Kartu tipe unit: `url` atau `sheet` (id <dialog>), fakta `specs`

  variant 'project': kartu proyek
  Slot `top`: isi di atas judul, selebar konten
  Slot `before` / `after`: isi tambahan
--}}
@props(['types', 'heading', 'id' => 'tipe', 'variant' => 'default', 'top' => null, 'before' => null, 'after' => null])

@php($project = $variant === 'project')

<section id="{{ $id }}" @class(['types', 'types--project' => $project]) aria-labelledby="{{ $id }}-title">
  @if ($top)
    <div class="types__top">
      <div class="wrap">{{ $top }}</div>
    </div>
  @endif

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
