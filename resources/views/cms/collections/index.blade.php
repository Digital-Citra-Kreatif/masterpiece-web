{{-- Daftar koleksi --}}
@extends('cms.layout')

@section('content')
    <header class="page-header">
        <h1>{{ $title }}</h1>
        @include('cms.partials.fields', ['values' => $values, 'richText' => $richText])
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
