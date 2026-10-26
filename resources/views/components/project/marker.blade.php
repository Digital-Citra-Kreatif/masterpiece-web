{{-- Penanda titik --}}
@props(['marker'])

@if ($marker && $marker['image'])
  <img class="pj-marker pj-marker--image" src="{{ $marker['image'] }}" alt="" aria-hidden="true" />
@elseif ($marker)
  <span class="pj-marker pj-marker--{{ $marker['shape'] }}" aria-hidden="true"></span>
@endif
