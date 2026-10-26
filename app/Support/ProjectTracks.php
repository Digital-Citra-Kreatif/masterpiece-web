<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Sunrice\Cms\Models\EntryTranslation;

/** Jalur titik proyek */
final class ProjectTracks
{
    /**
     * Section dengan orbit
     *
     * @return array<string, string> section handle => frame folder under public/
     */
    public static function sections(): array
    {
        return [
            'masterplan' => (string) Content::sampleArea()['scene']['base'],
            'cluster' => (string) Content::sampleCluster()['scene']['base'],
        ];
    }

    /**
     * Titik yang ditempatkan
     *
     * @param  list<mixed>  $rows  the section's `points` rows
     * @return array<string, array{0: float, 1: float}> key => [x %, y %]
     */
    public static function placed(array $rows, string $section): array
    {
        $percent = fn (mixed $value): ?float => is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : null;

        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['name'] ?? null))
            ->values()
            ->mapWithKeys(fn (array $row, int $index): array => ["{$section}-".($index + 1) => [$percent($row['x'] ?? null), $percent($row['y'] ?? null)]])
            ->filter(fn (array $point): bool => $point[0] !== null && $point[1] !== null)
            ->all();
    }

    /**
     * Jalur tersimpan
     *
     * @param  array<string, array{0: float, 1: float}>  $placed
     * @return array<string, list<array{0: int, 1: int}|null>>|null
     */
    public static function for(string $slug, string $section, array $placed): ?array
    {
        $stored = self::stored($slug)[$section] ?? null;

        return $placed !== [] && ($stored['hash'] ?? null) === self::hash($section, $placed) ? $stored['tracks'] : null;
    }

    /**
     * Buat ulang jalur
     *
     * @throws RuntimeException when the tracking script fails
     */
    public static function make(EntryTranslation $translation): void
    {
        $slug = (string) $translation->slug;
        $stored = self::stored($slug);
        $changed = false;

        foreach (self::sections() as $section => $base) {
            $placed = self::placed($translation->data[$section]['points'] ?? [], $section);
            $hash = self::hash($section, $placed);
            if (($stored[$section]['hash'] ?? null) === $hash) {
                continue;
            }
            $stored[$section] = ['hash' => $hash, 'tracks' => $placed === [] ? [] : self::track($base, $placed)];
            $changed = true;
        }

        if ($changed) {
            Storage::put(self::file($slug), json_encode($stored));
        }
    }

    /**
     * Jalankan skrip pelacak
     *
     * @param  array<string, array{0: float, 1: float}>  $placed
     * @return array<string, list<array{0: int, 1: int}|null>>
     */
    private static function track(string $base, array $placed): array
    {
        $dir = public_path(trim($base, '/'));
        $manifest = json_decode((string) file_get_contents("{$dir}/manifest.json"), true);
        $points = array_map(fn (array $p): array => [$p[0] / 100 * $manifest['width'], $p[1] / 100 * $manifest['height']], $placed);

        $result = Process::path(base_path())
            ->timeout(900)
            ->input(json_encode(['points' => $points]))
            ->run(['node', base_path('scripts/track-points.mjs'), $dir]);

        if ($result->failed()) {
            throw new RuntimeException('Tracking the project points failed: '.$result->errorOutput());
        }

        return json_decode($result->output(), true);
    }

    /**
     * @param  array<string, array{0: float, 1: float}>  $placed
     */
    private static function hash(string $section, array $placed): string
    {
        return md5(json_encode([self::sections()[$section], $placed]));
    }

    /**
     * @return array<string, array{hash: string, tracks: array<string, list<array{0: int, 1: int}|null>>}>
     */
    private static function stored(string $slug): array
    {
        $file = self::file($slug);

        return Storage::exists($file) ? (json_decode((string) Storage::get($file), true) ?: []) : [];
    }

    private static function file(string $slug): string
    {
        return "tracks/{$slug}.json";
    }
}
