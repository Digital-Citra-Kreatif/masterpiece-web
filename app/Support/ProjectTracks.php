<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Sunrice\Cms\Models\EntryTranslation;

/** Jalur titik proyek */
final class ProjectTracks
{
    /** Versi pelacak; naikkan agar semua titik dilacak ulang */
    private const TRACKER = 2;

    /** @var array<string, array<string, mixed>> manifest per folder frame */
    private static array $manifests = [];

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
     * Folder frame proyek: unggahan ZIP, atau contoh
     *
     * @return array<string, string> section handle => frame folder under public/
     */
    public static function bases(int $entryId): array
    {
        $bases = [];
        foreach (self::sections() as $section => $sample) {
            $bases[$section] = OrbitFrames::base($entryId, $section) ?? $sample;
        }

        return $bases;
    }

    /**
     * Titik yang ditempatkan
     *
     * @param  list<mixed>  $rows
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
    public static function for(int $entryId, string $section, array $placed, ?string $slug = null): ?array
    {
        $stored = self::stored($entryId, $slug)[$section] ?? null;
        $base = self::bases($entryId)[$section];
        if ($placed === [] || $stored === null) {
            return null;
        }
        if (($stored['hash'] ?? null) === self::hash($section, $placed, $base)) {
            return $stored['tracks'];
        }
        // Frame lain, jalur tak berlaku
        if (! self::sameFrames($section, $stored, $base)) {
            return null;
        }

        // Per titik sambil menunggu pelacakan ulang
        $tracks = self::reuse($base, $placed, $stored);

        return $tracks === [] ? null : $tracks;
    }

    /**
     * Jalur lama yang masih bisa dipakai: titik sama dipakai utuh, titik geser ikut digeser
     *
     * @param  array<string, array{0: float, 1: float}>  $placed
     * @param  array{tracks?: array<string, list<array{0: int, 1: int}|null>>, points?: array<string, array{0: float, 1: float}>}  $stored
     * @return array<string, list<array{0: int, 1: int}|null>>
     */
    private static function reuse(string $base, array $placed, array $stored): array
    {
        $old = $stored['points'] ?? [];
        [$width, $height] = self::size($base);
        $tracks = [];
        foreach ($placed as $key => [$x, $y]) {
            $track = $stored['tracks'][$key] ?? null;
            $from = $old[$key] ?? null;
            if ($track === null || $from === null) {
                continue;
            }
            $dx = (int) round(($x - $from[0]) / 100 * $width);
            $dy = (int) round(($y - $from[1]) / 100 * $height);
            $tracks[$key] = $dx === 0 && $dy === 0 ? $track : array_map(fn (?array $p): ?array => $p === null ? null : [$p[0] + $dx, $p[1] + $dy], $track);
        }

        return $tracks;
    }

    /**
     * Buat ulang jalur
     *
     * @throws RuntimeException when the tracking script fails
     */
    public static function make(EntryTranslation $translation): void
    {
        $file = self::file($translation->entry_id);
        $stored = self::stored($translation->entry_id, (string) $translation->slug);
        // File lama per slug
        $changed = ! Storage::exists($file);

        foreach (self::bases($translation->entry_id) as $section => $base) {
            // Gambar diam tanpa jalur
            if ($base === self::sections()[$section] && filled($translation->data[$section]['image'] ?? null)) {
                continue;
            }
            $placed = self::placed($translation->data[$section]['points'] ?? [], $section);
            $hash = self::hash($section, $placed, $base);
            if (($stored[$section]['hash'] ?? null) === $hash) {
                // File lama tanpa posisi titik
                if (! isset($stored[$section]['points'])) {
                    $stored[$section]['points'] = $placed;
                    $changed = true;
                }

                continue;
            }
            // Lacak ulang hanya titik yang berubah
            $fresh = ($stored[$section]['tracker'] ?? 1) === self::TRACKER && self::sameFrames($section, $stored[$section] ?? [], $base);
            $old = $fresh ? ($stored[$section]['points'] ?? []) : [];
            $kept = [];
            $todo = [];
            foreach ($placed as $key => $point) {
                if (isset($stored[$section]['tracks'][$key]) && self::same($old[$key] ?? null, $point)) {
                    $kept[$key] = $stored[$section]['tracks'][$key];
                } else {
                    $todo[$key] = $point;
                }
            }
            $tracks = [...$kept, ...($todo === [] ? [] : self::track($base, $todo))];
            $stored[$section] = ['hash' => $hash, 'tracker' => self::TRACKER, 'base' => $base, 'points' => $placed, 'tracks' => array_intersect_key($tracks, $placed)];
            $changed = true;
        }

        if ($changed) {
            Storage::put($file, json_encode($stored));
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
        $dir = OrbitFrames::directory($base);
        $manifest = self::manifest($base);
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
     * Posisi sama (JSON menyimpan 50.0 sebagai 50)
     *
     * @param  array{0: float|int, 1: float|int}|null  $a
     * @param  array{0: float, 1: float}  $b
     */
    private static function same(?array $a, array $b): bool
    {
        return $a !== null && abs($a[0] - $b[0]) < 0.001 && abs($a[1] - $b[1]) < 0.001;
    }

    /**
     * Manifest frame orbit
     *
     * @return array<string, mixed>
     */
    private static function manifest(string $base): array
    {
        return self::$manifests[$base] ??= json_decode((string) @file_get_contents(OrbitFrames::directory($base).'/manifest.json'), true) ?: [];
    }

    /** @return array{0: int, 1: int} ukuran frame dalam piksel */
    private static function size(string $base): array
    {
        $manifest = self::manifest($base);

        return [(int) ($manifest['width'] ?? 0), (int) ($manifest['height'] ?? 0)];
    }

    /**
     * @param  array<string, array{0: float, 1: float}>  $placed
     */
    private static function hash(string $section, array $placed, ?string $base = null): string
    {
        return md5(json_encode([self::TRACKER, $base ?? self::sections()[$section], $placed]));
    }

    /**
     * Jalur dari frame yang sama (file lama: frame contoh)
     *
     * @param  array{base?: string}  $stored
     */
    private static function sameFrames(string $section, array $stored, string $base): bool
    {
        return ($stored['base'] ?? self::sections()[$section]) === $base;
    }

    /**
     * Per entri, cadangan file lama per slug
     *
     * @return array<string, array{hash: string, tracker?: int, points?: array<string, array{0: float, 1: float}>, tracks: array<string, list<array{0: float, 1: float}|null>>}>
     */
    private static function stored(int $entryId, ?string $slug = null): array
    {
        foreach (array_filter([self::file($entryId), $slug !== null ? "tracks/{$slug}.json" : null]) as $file) {
            if (Storage::exists($file)) {
                return json_decode((string) Storage::get($file), true) ?: [];
            }
        }

        return [];
    }

    /** Tetap walau slug proyek diganti */
    private static function file(int $entryId): string
    {
        return "tracks/entry-{$entryId}.json";
    }
}
