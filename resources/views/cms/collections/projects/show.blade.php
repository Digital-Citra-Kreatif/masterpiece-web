{{-- Any entry of Projects, at /projects/{slug}. --}}
@include('pages.project', ['p' => \App\Support\ProjectPage::page($entry, $values)])
