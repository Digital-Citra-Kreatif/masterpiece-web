<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Sunrice\Cms\Models\EntryTranslation;

/**
 * Where a project's Masterplan and Cluster points are on every frame of their
 * orbit, so their pins stay on their place while it turns.
 *
 * The CMS gives each point's place on the orbit's first frame (x/y %).
 * scripts/track-points.mjs follows it through the frames (App\Jobs\TrackProjectPoints
 * runs it after a project is saved) and the paths are kept in
 * tracks/{slug}.json on the local disk, with a hash of the places they were made for.
 * A page uses a section's paths only while the places still match; until the
 * paths are made again its pins stay at their places on screen.
 */
final class ProjectTracks
{
    /**
     * The sections with points on an orbit, and the frame sequence each turns on
     * (the homepage's for now: Content::sampleArea(), Content::sampleCluster()).
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
     * A section's points that have a place, by hotspot key ({section}-{n}, n
     * counting the named points as App\Support\ProjectPage does).
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
     * The section's stored paths, when they were made for these places.
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
     * Make the paths of every section whose places changed since they were last made.
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
     * Run scripts/track-points.mjs on the section's frames.
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
