{{-- Detail tipe unit --}}
@props(['unit', 'gallery'])

@php($title = $unit['name'])

<dialog class="ov is-info pj-sheet" id="{{ $unit['id'] }}" aria-labelledby="{{ $unit['id'] }}-title">
  <div class="ov__bar">
    <div class="ov__tools">
      <button class="icon-btn ov__close" type="button" data-sheet-close aria-label="Tutup">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 5l14 14M19 5L5 19"></path></svg>
      </button>
    </div>
  </div>

  <div class="ov__body">
    <article class="pj-sheet__body">
      @if ($unit['image'])
        <div class="pj-sheet__img pj-frame">
          <x-project.img :image="$unit['image']" sizes="460px" alt="" />
        </div>
      @endif

      <h2 class="display pj-sheet__name" id="{{ $unit['id'] }}-title">{{ $unit['name'] }}</h2>
      @if ($unit['category'])
        <p class="pj-sheet__label eyebrow">{{ $unit['category'] }}</p>
      @endif

      @if ($unit['facts'] !== [])
        <dl class="panel__facts pj-sheet__facts">
          @foreach ($unit['facts'] as [$label, $value])
            <div>
              <dt>{{ $label }}</dt>
              <dd>{{ $value }}</dd>
            </div>
          @endforeach
        </dl>
      @endif

      @if ($unit['plans'] !== [])
        <div class="panel__denah pj-sheet__plans">
          <ul>
            @foreach ($unit['plans'] as $plan)
              <li>
                <button type="button" data-open="gallery" data-payload="{{ $gallery($title, $unit['plans'], $loop->index) }}" aria-label="Buka denah {{ $plan['label'] }}">
                  <x-project.img :image="$plan" sizes="200px" alt="" />
                  <span>{{ $plan['label'] }}</span>
                </button>
              </li>
            @endforeach
          </ul>
        </div>
      @endif

      @if ($unit['gallery'] !== [] || $unit['vr'])
        <div class="panel__actions pj-sheet__actions">
          @if ($unit['gallery'] !== [])
            <button class="btn" type="button" data-open="gallery" data-payload="{{ $gallery("{$title}: Galeri", $unit['gallery']) }}">Galeri</button>
          @endif
          @if ($unit['vr'])
            <button class="btn btn--solid" type="button" data-open="iframe" data-payload="{{ json_encode(['src' => $unit['vr'], 'title' => "{$title}: VR"]) }}">VR</button>
          @endif
        </div>
      @endif
    </article>
  </div>
</dialog>
