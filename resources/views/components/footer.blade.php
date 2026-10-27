{{--
  Footer (Globals → Footer, Menus → Footer Menu), on white.
  `logo` ({ src, width, height }): a project page's own logo, in place of the Branding logo.
  The WhatsApp number shows as the call-to-action button, not in the contact list.
--}}
@props(['logo' => null])

@php
  $ft = \App\Support\Content::footer();
  $logo ??= $ft['logo'];
@endphp

<footer class="ft">
  <div class="wrap ft__inner">
    <div class="ft__brand">
      @if ($logo)
        <img class="ft__logo" src="{{ $logo['src'] }}" alt="{{ $site['fullName'] }}" @if ($logo['width']) width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" @endif loading="lazy" />
      @else
        <p class="display ft__mark">{{ $site['fullName'] }}</p>
      @endif
      @if ($ft['description'])
        <p class="ft__text">{!! nl2br(e($ft['description']), false) !!}</p>
      @endif
      @if ($ft['whatsapp'])
        <a class="ft__cta" href="{{ $ft['whatsapp']['href'] }}" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.2-.3-.2-.5-.3Z"/></svg>
          Chat via WhatsApp
        </a>
      @endif
    </div>

    @if ($ft['address'] || $ft['phone'] || $ft['email'])
      <div class="ft__col">
        <h2 class="ft__head">Kontak</h2>
        @if ($ft['address'])
          <address class="ft__text">{!! nl2br(e($ft['address']), false) !!}</address>
        @endif
        <ul class="ft__list">
          @foreach (['phone', 'email'] as $key)
            @if ($ft[$key])
              <li><a href="{{ $ft[$key]['href'] }}">{{ $ft[$key]['label'] }}</a></li>
            @endif
          @endforeach
        </ul>
      </div>
    @endif

    @if ($ft['hours'])
      <div class="ft__col">
        <h2 class="ft__head">Jam operasional</h2>
        <p class="ft__text">{!! nl2br(e($ft['hours']), false) !!}</p>
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
