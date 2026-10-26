{{--
  Location (after design-project.pdf): on the dark colour, the title and its
  intro on the left, the location map large on the right with its spots
  (Lokasi → Titik peta), each naming itself on hover or focus; further maps
  as small thumbnails under it. Maps open in the overlay.

  loc: App\Support\ProjectPage::location(); gallery: the page's gallery payload builder.
--}}
@props(['loc', 'id', 'gallery'])

<section id="{{ $id }}" class="pj-loc" aria-labelledby="{{ $id }}-title">
  <div class="pj-loc__inner wrap">
    <div class="pj-loc__copy">
      <h2 class="pj-title display" id="{{ $id }}-title">{{ $loc['title'] }}</h2>
      @if ($loc['intro'])
        <p class="pj-loc__intro">{{ $loc['intro'] }}</p>
      @endif
    </div>

    <div class="pj-loc__maps">
      @foreach ($loc['maps'] as $map)
        @if ($loop->first)
          <div class="pj-loc__stage" @if ($map['width']) style="--ratio: {{ $map['width'] }} / {{ $map['height'] }}" @endif>
            {{-- The map and its spots on one plane that covers the stage, so a spot at x/y % stays on its place when the edges are cut. --}}
            <div class="pj-loc__plane">
            <button class="pj-loc__map is-main" type="button" data-open="gallery" data-payload="{{ $gallery($loc['title'], $loc['maps']) }}" aria-label="Perbesar peta">
              <x-project.img :image="$map" sizes="(min-width: 960px) 66vw, 100vw" :alt="$map['alt'] ?: 'Peta lokasi'" />
            </button>
            @foreach ($loc['pins'] as $pin)
              <button class="pj-loc__pin" type="button" style="left: {{ $pin['x'] }}%; top: {{ $pin['y'] }}%">
                <span class="pj-loc__pin-name">{{ $pin['name'] }}</span>
              </button>
            @endforeach
            </div>
          </div>
        @else
          <button class="pj-loc__map" type="button" data-open="gallery" data-payload="{{ $gallery($loc['title'], $loc['maps'], $loop->index) }}" aria-label="Perbesar peta {{ $loop->iteration }}">
            <x-project.img :image="$map" sizes="160px" :alt="$map['alt'] ?: 'Peta lokasi '.$loop->iteration" />
          </button>
        @endif
      @endforeach
    </div>
  </div>
</section>
