{{-- Detail entri --}}
@extends('cms.layout')

@section('content')
    <article class="entry"{{ cms_page()?->langAttribute() }}>
        <header class="entry-header">
            <h1>{{ $title }}</h1>
            @if ($collection->dated && $entry->published_at)
                <p class="muted"><time datetime="{{ $entry->published_at->toAtomString() }}">{{ $entry->published_at->translatedFormat('j F Y') }}</time></p>
            @endif
            @if ($entry->terms->isNotEmpty())
                {{-- Term entri --}}
                <p class="muted">
                    @foreach ($entry->terms as $term)
                        @if ($url = $term->url())
                            <a href="{{ $url }}">{{ $term->title() }}</a>
                        @else
                            {{ $term->title() }}
                        @endif
                    @endforeach
                </p>
            @endif
        </header>

        @include('cms.partials.fields', ['values' => $values, 'richText' => $richText])
    </article>

    @unless ($preview)
        <x-sunrice-cms::comments :entry="$entry" />
    @endunless
@endsection
