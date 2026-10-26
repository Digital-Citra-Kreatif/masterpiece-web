{{-- Ketersediaan unit --}}
@props(['av', 'id', 'gallery'])

<section id="{{ $id }}" class="pj-av" aria-labelledby="{{ $id }}-title">
  <div class="wrap">
    <h2 class="pj-title display" id="{{ $id }}-title">{{ $av['title'] }}</h2>
    @if ($av['intro'])
      <p class="pj-av__intro">{{ $av['intro'] }}</p>
    @endif

    <div @class(['pj-av__grid', 'has-blocks' => $av['blocks'] !== []])>
      @if ($av['siteplan'])
        <button class="pj-av__siteplan pj-frame" type="button" data-open="gallery" data-payload="{{ $gallery($av['title'], [['label' => 'Siteplan', ...$av['siteplan']]]) }}" aria-label="Perbesar siteplan">
          <x-project.img :image="$av['siteplan']" sizes="(min-width: 960px) 70vw, 100vw" :alt="$av['siteplan']['alt'] ?: 'Siteplan'" />
        </button>
      @endif

      @if ($av['blocks'] !== [])
        <ul class="pj-av__side">
          @foreach ($av['blocks'] as $block)
            <li>
              <button class="pj-block" type="button" data-open="gallery" data-payload="{{ $gallery($av['title'], $av['blocks'], $loop->index) }}">
                <span class="pj-block__name">{{ $block['name'] }}</span>
                @if ($block['viewName'])
                  <span class="pj-block__view">{{ $block['viewName'] }}</span>
                @endif
              </button>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </div>
</section>
