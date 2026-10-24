{{-- The homepage entry (Pages → Beranda), shown at /. Hero from its fields, the other sections hardcoded for now: resources/data/home.php. --}}
@include('pages.home', \App\Support\Content::home($values))
