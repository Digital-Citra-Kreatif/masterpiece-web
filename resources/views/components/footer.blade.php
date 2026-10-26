{{-- Footer (Globals → Footer, Menus → Footer Menu) --}}
@php($ft = \App\Support\Content::footer())

<footer class="ft room">
  <div class="wrap ft__inner">
    <div class="ft__brand">
      @if ($ft['logo'])
        <img class="ft__logo" src="{{ $ft['logo']['src'] }}" alt="{{ $site['fullName'] }}" @if ($ft['logo']['width']) width="{{ $ft['logo']['width'] }}" height="{{ $ft['logo']['height'] }}" @endif loading="lazy" />
      @else
        <p class="display ft__mark">{{ $site['fullName'] }}</p>
      @endif
      @if ($ft['description'])
        <p class="ft__text">{!! nl2br(e($ft['description']), false) !!}</p>
      @endif
    </div>

    @if ($ft['address'] || $ft['phone'] || $ft['email'] || $ft['whatsapp'])
      <div class="ft__col">
        <h2 class="ft__head">Kontak</h2>
        @if ($ft['address'])
          <address class="ft__text">{!! nl2br(e($ft['address']), false) !!}</address>
        @endif
        <ul class="ft__list">
          @foreach (['phone' => 'Telepon', 'email' => 'Email', 'whatsapp' => 'WhatsApp'] as $key => $label)
            @if ($ft[$key])
              <li>
                <span class="ft__label">{{ $label }}</span>
                <a href="{{ $ft[$key]['href'] }}" @if ($key === 'whatsapp') target="_blank" rel="noopener" @endif>{{ $ft[$key]['label'] }}</a>
              </li>
            @endif
          @endforeach
        </ul>
      </div>
    @endif

    @if ($ft['links'] !== [])
      <nav class="ft__col" aria-label="Menu footer">
        <h2 class="ft__head">Menu</h2>
        <ul class="ft__list">
          @foreach ($ft['links'] as $link)
            <li><a href="{{ $link['href'] }}" @if ($link['newTab']) target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a></li>
          @endforeach
        </ul>
      </nav>
    @endif
  </div>

  <div class="ft__bar">
    <div class="wrap">
      <p class="ft__meta">{{ $ft['copyright'] }}</p>
    </div>
  </div>
</footer>
