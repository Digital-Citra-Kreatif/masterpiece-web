{{-- Daftar titik --}}
@props(['section', 'id'])

<section id="{{ $id }}" class="pj-points room" aria-labelledby="{{ $id }}-title">
  <div class="wrap">
    <x-sheet-header id="{{ $id }}-title" :title="$section['title']" :intro="$section['intro']" />
    <ol class="pj-points__list">
      @foreach ($section['points'] as $point)
        <li>
          <span class="pj-points__no">{{ $point['number'] }}</span>
          <span>{{ $point['name'] }}</span>
        </li>
      @endforeach
    </ol>
  </div>
</section>
