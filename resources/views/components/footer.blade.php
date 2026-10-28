{{-- Footer situs --}}
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
    </div>

    @if ($ft['address'] || $ft['phone'] || $ft['email'] || $ft['whatsapp'])
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
          @if ($ft['whatsapp'])
            <li><a href="{{ $ft['whatsapp']['href'] }}" target="_blank" rel="noopener">WhatsApp {{ $ft['whatsapp']['label'] }}</a></li>
          @endif
        </ul>
      </div>
    @endif

    @if ($ft['hours'])
      <div class="ft__col">
        <h2 class="ft__head">Jam operasional</h2>
        <p class="ft__text">{!! nl2br(e($ft['hours']), false) !!}</p>
      </div>
    @endif
  </div>

  <div class="ft__bar">
    <div class="wrap">
      <p class="ft__meta">{{ $ft['copyright'] }}</p>
    </div>
  </div>
</footer>
