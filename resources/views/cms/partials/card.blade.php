{{-- Kartu entri --}}
@php($url = $item->url())
@php($fields = $item->values())
@php($image = $fields['image'] ?? null)
@php($image = $image instanceof \Illuminate\Support\Collection ? $image->first() : $image)
<li class="card">
    @if ($image instanceof \Sunrice\Admin\Media\Media && $image->isImage())
        <img src="{{ $image->url() }}" alt="" loading="lazy">
    @endif
    <h2 class="card-title">
        @if ($url)<a href="{{ $url }}">{{ $item->title }}</a>@else{{ $item->title }}@endif
    </h2>
    @if ($item->entry->collection->dated && $item->published_at)
        <p class="muted"><time datetime="{{ $item->published_at->toAtomString() }}">{{ $item->published_at->translatedFormat('j F Y') }}</time></p>
    @endif
    @if (is_string($excerpt = $fields['excerpt'] ?? null) && $excerpt !== '')
        <p>{{ $excerpt }}</p>
    @endif
</li>
