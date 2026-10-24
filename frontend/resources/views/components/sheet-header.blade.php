{{-- Section heading: title and intro. --}}
@props(['id' => null, 'title', 'intro' => null])

<div class="sheet" data-reveal>
  <div>
    <h2 class="sheet__title display" @if ($id) id="{{ $id }}" @endif>{{ $title }}</h2>
  </div>
  @if ($intro)
    <p class="sheet__intro">{{ $intro }}</p>
  @endif
</div>
