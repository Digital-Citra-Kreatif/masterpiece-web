{{--
  'home': transparent over the hero, the CMS menu (config site.menu; without
  links there, the homepage sections).
  'project': like 'home', with the page's own links (`nav`) and, when given, its own `logo` ({ src, width, height }).
  'type': solid, with a back link to the Unit update section.
  Not sticky: it sits at the top of the page and scrolls away.
--}}
@props(['variant' => 'home', 'nav' => null, 'logo' => null])

<header class="hd hd--{{ $variant }}">
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
    @else
      <a class="hd__back roll-host" href="{{ url('/') }}#unit-update">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M19 12H5M11 6l-6 6 6 6" />
        </svg>
        <x-roll-text text="Kembali ke Unit Update" />
      </a>
    @endif
  </div>
</header>
