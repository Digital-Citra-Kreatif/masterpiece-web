{{-- Menu navigasi --}}
<ul>
    @foreach ($items as $item)
        <li @class(['is-active' => $item['active_trail']])>
            <a href="{{ $item['url'] }}"@if ($item['active']) aria-current="page"@endif @if ($item['new_tab']) target="_blank" rel="noopener"@endif>{{ $item['label'] }}</a>
            @if ($item['children'] !== [])
                @include('cms.partials.menu', ['items' => $item['children']])
            @endif
        </li>
    @endforeach
</ul>
