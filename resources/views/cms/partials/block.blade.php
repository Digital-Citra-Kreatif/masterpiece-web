{{-- Blok bawaan --}}
@php($richText = collect(app(\Sunrice\Cms\Fields\Fieldsets::class)->fields($block->type) ?? [])->where('type', 'rich_text')->pluck('handle')->all())
@include('cms.partials.fields', ['values' => $block->values, 'richText' => $richText])
