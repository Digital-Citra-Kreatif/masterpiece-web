{{--
  Text whose letters roll up one after another on hover of the closest
  `.roll-host` (each letter is replaced by a copy rising from below).
  Screen readers get the plain text. Styles in resources/css/base.css.
--}}
@props(['text'])

<span class="roll" aria-hidden="true">@foreach (mb_str_split($text) as $char)<span class="roll__char" style="--i: {{ $loop->index }}">{{ $char }}</span>@endforeach</span><span class="visually-hidden">{{ $text }}</span>
