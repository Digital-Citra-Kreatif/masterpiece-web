<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/** Frame orbit unggahan per proyek */
final class OrbitFrames
{
    /** Lebar tiap ukuran */
    public const SIZES = ['lg' => 1280, 'sm' => 800];

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Batas isi ZIP */
    public const MIN_FRAMES = 8;

    public const MAX_FRAMES = 720;

    public const MAX_FRAME_BYTES = 40 * 1024 * 1024;

    public const MAX_TOTAL_BYTES = 2 * 1024 * 1024 * 1024;

    public const MAX_SIDE = 8192;

    /** Folder disk public */
    public static function root(int $entryId, string $section): string
    {
        return "orbits/entry-{$entryId}/{$section}";
    }

    /**
     * Folder frame aktif, relatif public/
     */
    public static function base(int $entryId, string $section): ?string
    {
        $disk = Storage::disk('public');
        $versions = collect($disk->directories(self::root($entryId, $section)))
            ->filter(fn (string $dir): bool => preg_match('/\/v\d+$/', $dir) === 1 && $disk->exists("{$dir}/manifest.json"))
            ->sortDesc()
            ->values();

        return $versions->isEmpty() ? null : '/storage/'.$versions->first().'/';
    }

    /** Folder di disk untuk base frame (unggahan atau public/) */
    public static function directory(string $base): string
    {
        $path = trim($base, '/');

        return str_starts_with($path, 'storage/') ? Storage::disk('public')->path(substr($path, strlen('storage/'))) : public_path($path);
    }

    /**
     * Ekstrak, ubah ukuran, tulis manifest
     *
     * @throws RuntimeException with a message for the admin
     */
    public static function process(int $entryId, string $section, string $zipPath): int
    {
        @ini_set('memory_limit', '1024M');
        $archive = new ZipArchive;
        if ($archive->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('File bukan ZIP yang valid.');
        }

        $disk = Storage::disk('public');
        $root = self::root($entryId, $section);
        // Aktif setelah manifest ditulis
        $tmp = "{$root}/v".now()->format('YmdHisv');

        try {
            $frames = self::frames($archive);
            foreach (array_keys(self::SIZES) as $size) {
                $disk->makeDirectory("{$tmp}/{$size}");
            }

            $ratio = null;
            foreach (array_values($frames) as $n => [$index, $name]) {
                $contents = $archive->getFromIndex($index, self::MAX_FRAME_BYTES + 1);
                $info = $contents === false ? false : @getimagesizefromstring($contents);
                if ($info === false || strlen((string) $contents) > self::MAX_FRAME_BYTES) {
                    throw new RuntimeException("Frame {$name} tidak bisa dibaca sebagai gambar.");
                }
                [$width, $height] = $info;
                if ($width < 1 || $height < 1 || $width > self::MAX_SIDE || $height > self::MAX_SIDE) {
                    throw new RuntimeException("Frame {$name} terlalu besar (maks ".self::MAX_SIDE.' px per sisi).');
                }
                // Semua frame seukuran
                $ratio ??= $height / $width;
                if (abs($height / $width - $ratio) > 0.01) {
                    throw new RuntimeException("Frame {$name} berbeda ukuran dari frame pertama. Semua frame harus berukuran sama.");
                }
                $image = @imagecreatefromstring($contents);
                unset($contents);
                if ($image === false) {
                    throw new RuntimeException("Frame {$name} tidak bisa dibaca sebagai gambar.");
                }
                foreach (self::SIZES as $size => $w) {
                    $h = (int) round($w * $ratio);
                    $out = imagecreatetruecolor($w, $h);
                    // Latar putih untuk PNG transparan
                    imagefill($out, 0, 0, (int) imagecolorallocate($out, 255, 255, 255));
                    imagecopyresampled($out, $image, 0, 0, 0, 0, $w, $h, $width, $height);
                    imagejpeg($out, $disk->path(sprintf('%s/%s/f_%03d.jpg', $tmp, $size, $n)), 82);
                    unset($out);
                }
                unset($image);
            }

            $disk->put("{$tmp}/manifest.json", json_encode([
                'count' => count($frames),
                'width' => self::SIZES['lg'],
                'height' => (int) round(self::SIZES['lg'] * $ratio),
                'sizes' => self::SIZES,
                'pattern' => '{size}/f_{n}.jpg',
                'step' => 1,
                'hotspots' => (object) [],
            ]));
        } catch (RuntimeException $e) {
            $disk->deleteDirectory($tmp);

            throw $e;
        } finally {
            $archive->close();
        }

        // Versi lama dihapus
        foreach ($disk->directories($root) as $dir) {
            if ($dir !== $tmp) {
                $disk->deleteDirectory($dir);
            }
        }

        return count($frames);
    }

    /** Hapus frame unggahan */
    public static function clear(int $entryId, string $section): void
    {
        Storage::disk('public')->deleteDirectory(self::root($entryId, $section));
        Storage::disk('local')->delete(self::upload($entryId, $section).'.json');
    }

    /** ZIP dan status, disk privat */
    public static function upload(int $entryId, string $section): string
    {
        return "orbit-uploads/entry-{$entryId}-{$section}";
    }

    /**
     * Status unggahan terakhir
     *
     * @return array{state: string, message: ?string, count: ?int, source: ?int}
     */
    public static function status(int $entryId, string $section): array
    {
        $status = json_decode((string) Storage::disk('local')->get(self::upload($entryId, $section).'.json'), true);

        return is_array($status) ? [...$status, 'source' => $status['source'] ?? null] : ['state' => 'idle', 'message' => null, 'count' => null, 'source' => null];
    }

    /** Status baru, sumber ZIP tetap */
    public static function setStatus(int $entryId, string $section, string $state, ?string $message = null, ?int $count = null): void
    {
        $source = self::status($entryId, $section)['source'];
        Storage::disk('local')->put(self::upload($entryId, $section).'.json', json_encode(['state' => $state, 'message' => $message, 'count' => $count, 'source' => $source]));
    }

    /** Media ZIP asal frame; null untuk unggahan panel */
    public static function setSource(int $entryId, string $section, ?int $mediaId): void
    {
        Storage::disk('local')->put(self::upload($entryId, $section).'.json', json_encode([...self::status($entryId, $section), 'source' => $mediaId]));
    }

    /**
     * Gambar di ZIP, urut nama
     *
     * @return array<string, array{0: int, 1: string}> path => [zip index, file name]
     *
     * @throws RuntimeException
     */
    private static function frames(ZipArchive $archive): array
    {
        $frames = [];
        $total = 0;
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $stat = $archive->statIndex($index);
            $path = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            $name = basename($path);
            $isImage = in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
            if (! $isImage || str_ends_with($path, '/') || str_starts_with($name, '.') || str_contains($path, '__MACOSX')) {
                continue;
            }
            if (($stat['size'] ?? 0) > self::MAX_FRAME_BYTES) {
                throw new RuntimeException("Frame {$name} terlalu besar (maks 40 MB per gambar).");
            }
            $total += (int) ($stat['size'] ?? 0);
            $frames[$path] = [$index, $name];
        }

        if ($total > self::MAX_TOTAL_BYTES) {
            throw new RuntimeException('Isi ZIP terlalu besar (maks 2 GB setelah diekstrak).');
        }
        if (count($frames) < self::MIN_FRAMES) {
            throw new RuntimeException('ZIP berisi '.count($frames).' gambar. Butuh minimal '.self::MIN_FRAMES.' frame (JPG, PNG, atau WebP).');
        }
        if (count($frames) > self::MAX_FRAMES) {
            throw new RuntimeException('ZIP berisi '.count($frames).' gambar. Maksimal '.self::MAX_FRAMES.' frame.');
        }
        uksort($frames, strnatcasecmp(...));

        return $frames;
    }
}
