<?php

namespace App\Jobs;

use App\Support\ProjectTracks;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Sunrice\Cms\Models\EntryTranslation;

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
}
