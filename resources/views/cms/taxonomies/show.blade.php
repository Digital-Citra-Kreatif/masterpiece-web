{{-- Halaman term --}}
@extends('cms.layout')

@section('content')
    <header class="page-header">
        <p class="eyebrow">{{ $taxonomy->title }}</p>
        <div{{ cms_page()?->langAttribute() }}>
            <h1>{{ $title }}</h1>
            @include('cms.partials.fields', ['values' => $values, 'richText' => $richText])
        </div>
    </header>

    @if ($entries->isEmpty())
        <p class="muted">{{ __('Nothing here yet.') }}</p>
    @else
        <ul class="cards">
            @foreach ($entries as $item)
                @include('cms.partials.card', ['item' => $item])
            @endforeach
        </ul>
        @include('cms.partials.pagination', ['paginator' => $entries])
    @endif
@endsection
