{{-- Daftar taksonomi --}}
@extends('cms.layout')

@section('content')
    <header class="page-header">
        @if ($collection !== null)
            <p class="eyebrow">{{ $collection->titleIn($locale) }}</p>
        @endif
        <h1>{{ $title }}</h1>
        @include('cms.partials.fields', ['values' => $values, 'richText' => $richText])
    </header>

    @if ($terms->isEmpty())
        <p class="muted">{{ __('Nothing here yet.') }}</p>
    @else
        <ul class="terms">
            @foreach ($terms as $term)
                @php($termUrl = $term->url($locale))
                <li>
                    @if ($termUrl !== null)
                        <a href="{{ $termUrl }}">{{ $term->title($locale) }}</a>
                    @else
                        {{ $term->title($locale) }}
                    @endif
                    <span class="muted">({{ $term->entries_count }})</span>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
