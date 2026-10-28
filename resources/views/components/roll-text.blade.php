{{-- Teks bergulir --}}
@props(['text'])

<span class="roll" aria-hidden="true">@foreach (mb_str_split($text) as $char)<span class="roll__char" style="--i: {{ $loop->index }}">{{ $char }}</span>@endforeach</span><span class="visually-hidden">{{ $text }}</span>
