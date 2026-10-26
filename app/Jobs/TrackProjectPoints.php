<?php

namespace App\Jobs;

use App\Support\ProjectTracks;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Sunrice\Cms\Models\EntryTranslation;

/**
 * Makes a project's Masterplan and Cluster point paths again after it was saved
 * (App\Support\ProjectTracks). It takes a minute or two, so it runs on the queue;
 * one at a time per project.
 */
class TrackProjectPoints implements ShouldBeUnique, ShouldQueue
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
