{{-- Grid proyek --}}
@props(['projects', 'heading', 'id' => 'projects'])

@php($cursor = $site['cardCursor']['type'])

<section id="{{ $id }}" class="prj" aria-labelledby="{{ $id }}-title" data-card-cursor="{{ $cursor }}" @if ($cursor !== 'default') data-view-cursor @endif>
  <div class="prj__head">
    <div class="wrap">
      <x-sheet-header id="{{ $id }}-title" :title="$heading['title']" :intro="$heading['intro']" />
    </div>
  </div>

  <div class="wrap">
    <ul class="prj__grid">
      @foreach ($projects as $p)
        <li>
          <a class="prj__card" href="{{ $p['url'] }}" data-view-target>
            <div class="prj__media">
              <div class="prj__frame">
                @if ($p['image'])
                  <img src="{{ $p['image'] }}" alt="" loading="lazy" decoding="async" />
                @endif
              </div>
              @if ($p['logo'])
                <span class="prj__logo"><img src="{{ $p['logo'] }}" alt="" loading="lazy" decoding="async" /></span>
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
  @if ($cursor !== 'default')
    <x-view-cursor :icon="$cursor === 'zoom'" :image="$site['cardCursor']['icon']" :text="$site['cardCursor']['text']" />
  @endif
</section>
