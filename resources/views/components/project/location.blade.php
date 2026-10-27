{{-- Section lokasi --}}
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
            {{-- Bidang peta --}}
            <div class="pj-loc__plane">
            <button class="pj-loc__map is-main" type="button" data-open="gallery" data-payload="{{ $gallery($loc['title'], $loc['maps']) }}" aria-label="Perbesar peta">
              <x-project.img :image="$map" sizes="(min-width: 960px) 66vw, 100vw" :alt="$map['alt'] ?: 'Peta lokasi'" />
            </button>
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
