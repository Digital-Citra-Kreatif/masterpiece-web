{{-- Unit types: 3 cards per row, at most 2 rows. Each card links to /tipe/<slug>. --}}
@props(['types', 'heading'])

<section id="tipe" class="types" aria-labelledby="tipe-title">
  <div class="wrap">
    <x-sheet-header
      id="tipe-title"
      :title="$heading['title']"
      :intro="$heading['intro']"
    />

    <ul class="types__grid">
      @foreach ($types as $t)
        <li data-reveal style="transition-delay: {{ ($loop->index % 3) * 90 }}ms">
          <a class="card" href="{{ $t['url'] }}">
            <div class="card__img">
              <img src="{{ asset($t['image']) }}" alt="{{ $t['name'] }}{{ $t['label'] ? ", {$t['label']}" : '' }}" loading="lazy" />
              <span class="card__open" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M7 17L17 7M9 7h8v8" /></svg>
              </span>
              @if ($site['showPlaceholderBadges'] && $t['placeholder'])
                <span class="badge card__badge">Data contoh</span>
              @endif
            </div>
            <div class="card__head">
              <h3 class="card__name display">{{ $t['name'] }}</h3>
              @if ($t['label'])
                <span class="card__label eyebrow">{{ $t['label'] }}</span>
              @endif
            </div>
            <dl class="card__specs">
              <div><dt>LT</dt><dd>{{ $t['lt'] }} m²</dd></div>
              <div><dt>LB</dt><dd>{{ $t['lb'] }} m²</dd></div>
              <div><dt>K. tidur</dt><dd>{{ $t['bedrooms'] }}</dd></div>
              <div><dt>K. mandi</dt><dd>{{ $t['bathrooms'] }}</dd></div>
            </dl>
          </a>
        </li>
      @endforeach
    </ul>
  </div>
</section>
