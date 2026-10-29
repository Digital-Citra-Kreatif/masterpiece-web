<?php

namespace App\Support;

use App\Jobs\ProcessOrbitFrames;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Support\Locales;

/** ZIP di field Gambar jadi frame 360° */
final class OrbitZips
{
    /** Setelah simpan draft */
    public static function handleRequest(Request $request): void
    {
        $route = $request->route();
        $entry = $route instanceof Route ? $route->parameter('entry') : null;
        if ($request->isMethodSafe() || ! $entry instanceof Entry || ! str_starts_with((string) $route->getName(), 'sunrice.admin.cms.entries.')) {
            return;
        }

        foreach ($entry->translations()->get() as $translation) {
            self::handle($translation);
        }
    }

    public static function handle(EntryTranslation $translation): void
    {
        if ($translation->entry?->collection?->handle !== 'projects' || $translation->locale !== Locales::default()) {
            return;
        }

        $entryId = $translation->entry_id;
        $data = $translation->draft_data ?: ($translation->data ?? []);
        foreach (array_keys(ProjectTracks::sections()) as $section) {
            $id = $data[$section]['image'] ?? null;
            $media = is_numeric($id) ? Media::query()->find((int) $id) : null;
            $zip = $media instanceof Media && self::isZip($media) ? $media : null;
            $source = OrbitFrames::status($entryId, $section)['source'];

            if ($zip !== null && $source !== $zip->id) {
                // ZIP baru: antrekan
                OrbitFrames::setSource($entryId, $section, $zip->id);
                $stream = Storage::disk($zip->disk)->readStream($zip->path);
                if (! is_resource($stream)) {
                    OrbitFrames::setStatus($entryId, $section, 'failed', 'File ZIP tidak ditemukan di media. Unggah ulang ZIP-nya.');

                    continue;
                }
                Storage::disk('local')->put(OrbitFrames::upload($entryId, $section).'.zip', $stream);
                fclose($stream);
                OrbitFrames::setStatus($entryId, $section, 'queued');
                ProcessOrbitFrames::dispatch($entryId, $section);
            } elseif ($zip === null && $source !== null) {
                // ZIP dilepas dari field
                OrbitFrames::clear($entryId, $section);
                ProcessOrbitFrames::retrack($entryId);
            }
        }
    }

    public static function isZip(Media $media): bool
    {
        return strtolower(pathinfo($media->filename, PATHINFO_EXTENSION)) === 'zip'
            || in_array($media->mime_type, ['application/zip', 'application/x-zip-compressed'], true);
    }
}
