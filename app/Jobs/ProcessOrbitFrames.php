<?php

namespace App\Jobs;

use App\Support\OrbitFrames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Sunrice\Cms\Models\EntryTranslation;
use Throwable;

/** Proses ZIP frame orbit, lalu lacak ulang titik */
class ProcessOrbitFrames implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $entryId, public string $section) {}

    public function handle(): void
    {
        $zip = OrbitFrames::upload($this->entryId, $this->section).'.zip';
        OrbitFrames::setStatus($this->entryId, $this->section, 'processing');
        try {
            $count = OrbitFrames::process($this->entryId, $this->section, Storage::disk('local')->path($zip));
        } catch (RuntimeException $e) {
            OrbitFrames::setStatus($this->entryId, $this->section, 'failed', $e->getMessage());

            return;
        } finally {
            Storage::disk('local')->delete($zip);
        }

        OrbitFrames::setStatus($this->entryId, $this->section, 'ready', null, $count);
        self::retrack($this->entryId);
    }

    public function failed(?Throwable $exception): void
    {
        Storage::disk('local')->delete(OrbitFrames::upload($this->entryId, $this->section).'.zip');
        OrbitFrames::setStatus($this->entryId, $this->section, 'failed', 'ZIP gagal diproses. Coba unggah ulang.');
    }

    /** Lacak ulang titik proyek */
    public static function retrack(int $entryId): void
    {
        foreach (EntryTranslation::query()->where('entry_id', $entryId)->pluck('id') as $id) {
            TrackProjectPoints::dispatch($id);
        }
    }
}
