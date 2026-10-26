{{--
  A section's numbered points without an aerial to place them on (Masterplan or
  Cluster before their map is set): the title, its intro and the list.

  section: App\Support\ProjectPage::masterplan() or ::cluster(); id: the section's anchor.
--}}
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
