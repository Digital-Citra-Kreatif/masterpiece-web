{{-- Any entry of Types, at /tipe/{slug}. --}}
@include('pages.type', ['t' => \App\Support\Content::type($entry, $values)])
