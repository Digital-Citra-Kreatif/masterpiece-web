{{-- The Global Style marker beside a point's number: a drawn shape or the uploaded image. Nothing without one. --}}
@props(['marker'])

@if ($marker && $marker['image'])
  <img class="pj-marker pj-marker--image" src="{{ $marker['image'] }}" alt="" aria-hidden="true" />
@elseif ($marker)
  <span class="pj-marker pj-marker--{{ $marker['shape'] }}" aria-hidden="true"></span>
@endif
