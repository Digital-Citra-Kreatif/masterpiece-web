{{--
  'home': transparent over the hero, the CMS menu (config site.menu; without
  links there, the homepage sections).
  'project': like 'home', with the page's own links (`nav`) and, when given, its own `logo` ({ src, width, height }).
  'type': solid, with a back link to the Unit update section, or, given `back`
  ({ href, label }), a button back to that page (a project's type page).
  Up to 1024px wide, 'home' and 'project' swap the links for a hamburger that
  opens them in a dropdown under the header (resources/js/menu.js).
  Not sticky: it sits at the top of the page and scrolls away.
--}}
@props(['variant' => 'home', 'nav' => null, 'logo' => null, 'back' => null])

<header @class(['hd', "hd--{$variant}"])>
  <div class="hd__inner wrap">
    <a class="hd__mark" href="{{ url('/') }}" aria-label="{{ $site['logo']['alt'] }}, beranda">
      @if ($logo)
        <img class="hd__logo hd__logo--own" src="{{ $logo['src'] }}" alt="" @if ($logo['width']) width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" @endif />
      @else
      @foreach (['light', 'dark'] as $tone)
        <img
          class="hd__logo hd__logo--{{ $tone }}"
          src="{{ asset($site['logo'][$tone]) }}"
          alt=""
          width="{{ $site['logo']['width'] }}"
          height="{{ $site['logo']['height'] }}"
        />
      @endforeach
      @endif
    </a>

    @if ($variant === 'home' || $variant === 'project')
      <nav class="hd__nav" aria-label="Menu utama">
        @foreach ($nav ?? $site['nav'] as $item)
          <a
            class="roll-host"
            href="{{ $item['href'] }}"
            @if ($item['active']) aria-current="page" @endif
            @if ($item['newTab']) target="_blank" rel="noopener" @endif
          ><x-roll-text :text="$item['label']" /></a>
        @endforeach
      </nav>

      {{-- Tablet dan mobile: menu hamburger, dropdown di bawah header (resources/js/menu.js) --}}
      <button class="hd__burger" type="button" aria-expanded="false" aria-controls="hd-menu" data-menu-toggle>
        <svg class="hd__burger-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M3 7h18M3 12h18M3 17h18" />
        </svg>
        <svg class="hd__burger-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M6 6l12 12M18 6L6 18" />
        </svg>
        <span class="visually-hidden">Menu</span>
      </button>
    @elseif ($back)
      <a class="btn hd__btn" href="{{ $back['href'] }}" aria-label="{{ $back['label'] }}">
        Kembali<span class="hd__btn-more">&nbsp;ke proyek</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M19 12H5M11 6l-6 6 6 6" />
        </svg>
      </a>
    @else
      <a class="hd__back roll-host" href="{{ url('/') }}#unit-update">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M19 12H5M11 6l-6 6 6 6" />
        </svg>
        <x-roll-text text="Kembali ke Unit Update" />
      </a>
    @endif
  </div>

  @if ($variant === 'home' || $variant === 'project')
    <div class="hd__menu" id="hd-menu" hidden>
      <nav class="hd__menu-nav wrap" aria-label="Menu">
        @foreach ($nav ?? $site['nav'] as $item)
          <a
            href="{{ $item['href'] }}"
            style="--i: {{ $loop->index }}"
            @if ($item['active']) aria-current="page" @endif
            @if ($item['newTab']) target="_blank" rel="noopener" @endif
          >{{ $item['label'] }}</a>
        @endforeach
      </nav>
    </div>
  @endif
</header>
