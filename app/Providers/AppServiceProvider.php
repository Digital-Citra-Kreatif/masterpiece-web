<?php

namespace App\Providers;

use App\Jobs\TrackProjectPoints;
use App\Support\Content;
use App\Support\OrbitZips;
use App\Support\ProjectFonts;
use App\Support\ProjectImages;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Sunrice\Cms\Models\EntryTranslation;

class AppServiceProvider extends ServiceProvider
{
    /** Daftar layanan */
    public function register(): void
    {
        //
    }

    /** Boot layanan */
    public function boot(): void
    {
        View::composer(['pages.*', 'components.*', 'errors.404'], function (ViewInstance $view): void {
            $view->with('site', Content::site());
        });

        EntryTranslation::saved(fn (EntryTranslation $translation) => app(ProjectFonts::class)->handle($translation));
        EntryTranslation::saved(fn (EntryTranslation $translation) => app(ProjectImages::class)->handle($translation));
        EntryTranslation::saved(fn (EntryTranslation $translation) => OrbitZips::handle($translation));
        // Lacak ulang titik
        EntryTranslation::saved(function (EntryTranslation $translation): void {
            if ($translation->wasChanged('data') && $translation->entry?->collection?->handle === 'projects') {
                TrackProjectPoints::afterSave($translation);
            }
        });
        Event::listen(RequestHandled::class, fn (RequestHandled $event) => app(ProjectFonts::class)->handleRequest($event->request));
        Event::listen(RequestHandled::class, fn (RequestHandled $event) => app(ProjectImages::class)->handleRequest($event->request));
        Event::listen(RequestHandled::class, fn (RequestHandled $event) => OrbitZips::handleRequest($event->request));
    }
}
