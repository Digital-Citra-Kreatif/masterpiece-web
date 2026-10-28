{{-- Blok fleksibel --}}
@foreach ($blocks as $block)
    <section class="block block-{{ $block->type }}"@if ($block->key) id="{{ $block->key }}"@endif>
        @includeFirst(['cms.blocks.'.$block->type, 'cms.partials.block'], ['block' => $block])
    </section>
@endforeach
