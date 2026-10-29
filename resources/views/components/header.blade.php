{{-- Header situs --}}
@props(['variant' => 'home', 'nav' => null, 'logo' => null, 'back' => null, 'mark' => null])

@php($items = $nav ?? $site['nav'])

<header @class(['hd', "hd--{$variant}"])>
  <div class="hd__inner wrap">
    {{-- Logo proyek ke halaman proyek --}}
    <a class="hd__mark" href="{{ $mark['href'] ?? url('/') }}" aria-label="{{ $mark['label'] ?? $site['logo']['alt'].', beranda' }}">
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
      {{-- Menu dari CMS → Menus --}}
      @if ($items !== [])
        <nav class="hd__nav" aria-label="Menu utama">
          @foreach ($items as $item)
            <a
              class="roll-host"
              href="{{ $item['href'] }}"
              @if ($item['active']) aria-current="page" @endif
              @if ($item['newTab']) target="_blank" rel="noopener" @endif
            ><x-roll-text :text="$item['label']" /></a>
          @endforeach
        </nav>

        {{-- Menu hamburger --}}
        <button class="hd__burger" type="button" aria-expanded="false" aria-controls="hd-menu" data-menu-toggle>
          <svg class="hd__burger-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <path d="M3 7h18M3 12h18M3 17h18" />
          </svg>
          <svg class="hd__burger-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
          <span class="visually-hidden">Menu</span>
        </button>
      @endif
    @elseif ($back)
      <a class="btn hd__btn" href="{{ $back['href'] }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M19 12H5M11 6l-6 6 6 6" />
        </svg>
        <span>Kembali <span class="hd__btn-more">ke {{ $back['label'] }}</span></span>
      </a>
    @else
      <a class="hd__back roll-host" href="{{ url('/') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M19 12H5M11 6l-6 6 6 6" />
        </svg>
        <x-roll-text text="Kembali ke beranda" />
      </a>
    @endif
  </div>

  @if (($variant === 'home' || $variant === 'project') && $items !== [])
    <div class="hd__menu" id="hd-menu" hidden>
      <nav class="hd__menu-nav wrap" aria-label="Menu">
        @foreach ($items as $item)
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
