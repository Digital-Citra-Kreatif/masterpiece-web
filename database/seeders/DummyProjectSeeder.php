<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Structure\StructureImporter;

/** Isi lorem ipsum project dummy, tiap project punya tipe sendiri */
class DummyProjectSeeder extends Seeder
{
    /** Slug project dummy => posisi hero */
    private const PROJECTS = [
        'lorem-ipsum-residence' => 'top-left',
        'dolor-sit-park' => 'middle-center',
        'amet-consectetur-hills' => 'bottom-right',
    ];

    /** Tipe Westlake sebagai cetakan, huruf => slug */
    private const BASE_TYPES = [
        'A' => 'streetview-tipe-a',
        'B' => 'lakeview-tipe-bcd',
        'C' => 'streetview-tipe-e',
    ];

    /** Key teks pendek => jumlah kata */
    private const SHORT = ['heading' => 5, 'title' => 2, 'name' => 3, 'label' => 2, 'value' => 3];

    /** Key teks kalimat => jumlah kata */
    private const SENTENCE = ['description' => 16, 'message' => 8, 'suitable_title' => 6, 'building_specs_note' => 10];

    /** Section yang bukan teks konten */
    private const SKIP = ['global_style', 'seo'];

    private const WORDS = [
        'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do', 'eiusmod', 'tempor',
        'incididunt', 'ut', 'labore', 'et', 'dolore', 'magna', 'aliqua', 'enim', 'ad', 'minim', 'veniam', 'quis',
        'nostrud', 'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip', 'ex', 'ea', 'commodo', 'consequat', 'duis',
        'aute', 'irure', 'in', 'reprehenderit', 'voluptate', 'velit', 'esse', 'cillum', 'fugiat', 'nulla', 'pariatur',
        'excepteur', 'sint', 'occaecat', 'cupidatat', 'non', 'proident', 'sunt', 'culpa', 'qui', 'officia', 'deserunt',
        'mollit', 'anim', 'id', 'est', 'laborum',
    ];

    /** Jalankan seeder */
    public function run(StructureImporter $importer, SaveDraft $saveDraft, PublishEntry $publish): void
    {
        foreach (self::PROJECTS as $slug => $align) {
            $project = self::translation('projects', $slug);
            $typeIds = $this->types($project, $importer, $saveDraft, $publish);

            $data = $this->lorem(array_diff_key($project->draft_data ?? [], array_flip(self::SKIP)), $slug);
            $data['hero']['align'] = $align;
            $data['types']['units'] = $typeIds;

            $publish->handle($saveDraft->handle($project, ['data' => $data, 'version' => $project->version]));
        }
    }

    /**
     * Tiga tipe milik project
     *
     * @return list<int>
     */
    private function types(EntryTranslation $project, StructureImporter $importer, SaveDraft $saveDraft, PublishEntry $publish): array
    {
        $prefix = str($project->title)->before(' ')->toString();
        $ids = [];
        foreach (self::BASE_TYPES as $letter => $baseSlug) {
            $title = "{$prefix} Tipe {$letter}";
            $slug = str($title)->slug()->toString();
            $data = $this->lorem(array_diff_key(self::translation('types', $baseSlug)->draft_data ?? [], array_flip(self::SKIP)), $slug);
            $data['intro'] = implode(' ', array_map(fn (int $i): string => $this->sentence(16, "{$slug}.intro.{$i}"), range(1, 3)));
            $data['project'] = $project->entry_id;

            $importer->import(['entries' => [[
                'collection' => 'types',
                'slug' => $slug,
                'translations' => ['id' => ['title' => $title, 'slug' => $slug, 'published' => true, 'data' => $data]],
            ]]]);

            // Sudah ada: perbarui isinya
            $type = self::translation('types', $slug);
            $publish->handle($saveDraft->handle($type, ['data' => $data, 'version' => $type->version]));
            $ids[] = $type->entry_id;
        }

        return $ids;
    }

    /** Entri bahasa Indonesia */
    private static function translation(string $collection, string $slug): EntryTranslation
    {
        return EntryTranslation::query()
            ->whereRelation('entry.collection', 'handle', $collection)
            ->where('locale', 'id')
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /** Ganti teks konten jadi lorem, selain itu tetap */
    private function lorem(mixed $value, string $path, int|string|null $key = null): mixed
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item, int|string $itemKey): mixed => is_string($itemKey) && str_starts_with($itemKey, '_') ? $item : $this->lorem($item, "{$path}.{$itemKey}", $itemKey))
                ->all();
        }
        if (! is_string($value) || $value === '' || ! is_string($key)) {
            return $value;
        }

        return match (true) {
            isset(self::SHORT[$key]) => str($this->words(self::SHORT[$key], $path))->title()->toString(),
            isset(self::SENTENCE[$key]) => $this->sentence(self::SENTENCE[$key], $path),
            default => $value,
        };
    }

    private function sentence(int $count, string $seed): string
    {
        return ucfirst($this->words($count, $seed)).'.';
    }

    /** Kata lorem tetap per seed */
    private function words(int $count, string $seed): string
    {
        $random = new Randomizer(new Mt19937(crc32($seed)));

        return implode(' ', array_map(fn (): string => self::WORDS[$random->getInt(0, count(self::WORDS) - 1)], range(1, $count)));
    }
}
