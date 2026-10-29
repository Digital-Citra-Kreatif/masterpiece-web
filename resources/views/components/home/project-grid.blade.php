{{-- Grid proyek --}}
@props(['projects', 'heading'])

<section id="proyek" class="types projects" aria-labelledby="proyek-title">
  <div class="wrap">
    <x-sheet-header id="proyek-title" :title="$heading['title']" :intro="$heading['intro']" />

    <ul class="types__grid">
      @foreach ($projects as $p)
        <li data-reveal style="transition-delay: {{ ($loop->index % 3) * 90 }}ms">
          <a class="card card--project" href="{{ $p['url'] }}">
            <div class="card__img">
              @if ($p['image'])
                <img src="{{ $p['image'] }}" alt="{{ $p['name'] }}" loading="lazy" />
              @endif
              <span class="card__open" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M7 17L17 7M9 7h8v8" /></svg>
              </span>
            </div>
            <div class="card__body">
              <div class="card__head">
                <h3 class="card__name display">{{ $p['name'] }}</h3>
              </div>
              @if ($p['description'])
                <p class="card__desc">{{ $p['description'] }}</p>
              @endif
            </div>
          </a>
        </li>
      @endforeach
    </ul>
  </div>
</section>
