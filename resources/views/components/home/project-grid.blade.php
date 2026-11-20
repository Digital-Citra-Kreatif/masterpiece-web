{{-- Grid proyek --}}
@props(['groups', 'heading', 'id' => 'projects'])

@php($cursor = $site['cardCursor']['type'])
{{-- Level judul kartu --}}
@php($titled = collect($groups)->whereNotNull('title')->isNotEmpty())

<section id="{{ $id }}" class="prj" aria-labelledby="{{ $id }}-title" data-card-cursor="{{ $cursor }}" @if ($cursor !== 'default') data-view-cursor @endif>
  <div class="prj__head">
    <div class="wrap">
      <x-sheet-header id="{{ $id }}-title" :title="$heading['title']" :intro="$heading['intro']" />
    </div>
  </div>

  <div class="wrap">
    @foreach ($groups as $group)
      <div class="prj__group">
        @if ($group['title'])
          <h3 class="prj__cat">{{ $group['title'] }}</h3>
        @endif
        <ul class="prj__grid">
          @foreach ($group['cards'] as $p)
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
                <{{ $titled ? 'h4' : 'h3' }} class="prj__title">{{ $p['name'] }}</{{ $titled ? 'h4' : 'h3' }}>
                @if ($p['description'])
                  <p class="prj__desc">{{ $p['description'] }}</p>
                @endif
              </a>
            </li>
          @endforeach
        </ul>
      </div>
    @endforeach
  </div>
  @if ($cursor !== 'default')
    <x-view-cursor />
  @endif
</section>
