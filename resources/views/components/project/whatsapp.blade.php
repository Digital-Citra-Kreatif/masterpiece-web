{{-- Tombol WhatsApp --}}
@props(['whatsapp', 'logo' => null])

<div class="wa" data-wa>
  <div class="wa__panel" id="wa-panel" role="region" aria-label="{{ $whatsapp['heading'] ?? $whatsapp['label'] }}" hidden>
    <div class="wa__head">
      <p class="wa__heading">{{ $whatsapp['heading'] ?? $whatsapp['label'] }}</p>
      @if ($whatsapp['description'])
        <p class="wa__text">{{ $whatsapp['description'] }}</p>
      @endif
    </div>

    <ul class="wa__contacts">
      @foreach ($whatsapp['contacts'] as $contact)
        <li style="--i: {{ $loop->index }}">
          <a href="{{ $contact['href'] }}" target="_blank" rel="noopener">
            @if ($contact['avatar'])
              <img src="{{ $contact['avatar'] }}" alt="" width="40" height="40" loading="lazy" />
            @elseif ($logo)
              <img class="wa__logo" src="{{ $logo['src'] }}" alt="" width="40" height="40" loading="lazy" />
            @else
              <span class="wa__initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($contact['title'], 0, 1)) }}</span>
            @endif
            <span class="wa__name">{{ $contact['title'] }}</span>
            <span class="wa__chat" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.2-.3-.2-.5-.3Z"/></svg>
              Chat
            </span>
          </a>
        </li>
      @endforeach
    </ul>
  </div>

  <button class="wa__toggle" type="button" aria-expanded="false" aria-controls="wa-panel" data-wa-toggle>
    <svg class="wa__icon-open" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.2-.3-.2-.5-.3Z"/></svg>
    <svg class="wa__icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
    <span class="visually-hidden">{{ $whatsapp['label'] }}</span>
  </button>
</div>
