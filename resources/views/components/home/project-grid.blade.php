{{-- Grid proyek --}}
@props(['projects', 'heading', 'id' => 'projects'])

<section id="{{ $id }}" class="prj" aria-labelledby="{{ $id }}-title">
  <div class="prj__head">
    <div class="wrap">
      <x-sheet-header id="{{ $id }}-title" :title="$heading['title']" :intro="$heading['intro']" />
    </div>
  </div>

  <div class="wrap">
    <ul class="prj__grid">
      @foreach ($projects as $p)
        <li data-reveal style="transition-delay: {{ ($loop->index % 2) * 90 }}ms">
          <a class="prj__card" href="{{ $p['url'] }}">
            <div class="prj__media">
              <div class="prj__frame">
                @if ($p['image'])
                  <img src="{{ $p['image'] }}" alt="" loading="lazy" />
                @endif
              </div>
              @if ($p['logo'])
                <span class="prj__logo"><img src="{{ $p['logo'] }}" alt="" loading="lazy" /></span>
              @endif
            </div>
            <h3 class="prj__title">{{ $p['name'] }}</h3>
            @if ($p['description'])
              <p class="prj__desc">{{ $p['description'] }}</p>
            @endif
          </a>
        </li>
      @endforeach
    </ul>
  </div>
</section>
