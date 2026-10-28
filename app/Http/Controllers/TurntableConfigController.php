<?php

namespace App\Http\Controllers;

use App\Support\ProjectPage;
use App\Support\Turntable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sunrice\Cms\Content as Cms;
use Sunrice\Cms\Models\EntryTranslation;

/** config.xml WebRotate per tab 360° */
class TurntableConfigController extends Controller
{
    public function __invoke(Request $request, int $project, int $tab): Response
    {
        $translation = Cms::entries('projects')->get()->first(fn (EntryTranslation $entry): bool => $entry->entry_id === $project) ?? abort(404);
        $values = $translation->values();
        $current = ProjectPage::turntableTabs(is_array($values['turntable'] ?? null) ? $values['turntable'] : [])[$tab] ?? abort(404);

        return response(Turntable::config(Turntable::images($current['frames']), $current['width'], $current['height']))
            ->header('Content-Type', 'text/xml; charset=utf-8');
    }
}
