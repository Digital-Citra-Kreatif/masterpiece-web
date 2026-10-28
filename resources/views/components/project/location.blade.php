{{-- Section lokasi --}}
@props(['loc', 'id'])

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
            <div class="pj-loc__map is-main">
              <x-project.img :image="$map" sizes="(min-width: 960px) 66vw, 100vw" :alt="$map['alt'] ?: 'Peta lokasi'" />
            </div>
            </div>
          </div>
        @else
          <div class="pj-loc__map">
            <x-project.img :image="$map" sizes="160px" :alt="$map['alt'] ?: 'Peta lokasi '.$loop->iteration" />
          </div>
        @endif
      @endforeach
    </div>
  </div>
</section>
