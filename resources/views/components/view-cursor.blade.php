{{-- Kursor View --}}
@props(['icon' => false])

<span @class(['view-cursor', 'view-cursor--icon' => $icon]) data-view-cursor-el aria-hidden="true">
  @if ($icon)
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5L20 20" /></svg>
  @else
    Lihat
  @endif
</span>
