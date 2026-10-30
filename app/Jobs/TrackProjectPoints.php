<?php

namespace App\Jobs;

use App\Support\ProjectTracks;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Models\EntryTranslation;
use Throwable;

/** Lacak ulang titik; simpanan saat job jalan tetap diantrekan */
class TrackProjectPoints implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public function __construct(public int $translationId) {}

    public function uniqueId(): string
    {
        return (string) $this->translationId;
    }

    public function handle(): void
    {
        $translation = EntryTranslation::query()->find($this->translationId);
        if ($translation !== null) {
            ProjectTracks::make($translation);
        }
    }

    /** Langsung bila cache siap */
    public static function afterSave(EntryTranslation $translation): void
    {
        $id = $translation->id;
        if (! ProjectTracks::ready($translation->entry_id)) {
            self::dispatch($id)->afterCommit();

            return;
        }

        DB::afterCommit(function () use ($id): void {
            try {
                set_time_limit(120);
                (new self($id))->handle();
            } catch (Throwable $e) {
                report($e);
                self::dispatch($id);
            }
        });
    }
}
