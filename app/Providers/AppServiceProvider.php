<?php

namespace App\Providers;

use App\Jobs\TrackProjectPoints;
use App\Support\Content;
use App\Support\ProjectFonts;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Sunrice\Cms\Models\EntryTranslation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['pages.*', 'components.*', 'errors.404'], function (ViewInstance $view): void {
            $view->with('site', Content::site());
        });

        EntryTranslation::saved(fn (EntryTranslation $translation) => app(ProjectFonts::class)->handle($translation));
        // Lacak ulang titik
        EntryTranslation::saved(function (EntryTranslation $translation): void {
            if ($translation->wasChanged('data') && $translation->entry?->collection?->handle === 'projects') {
                TrackProjectPoints::dispatch($translation->id)->afterCommit();
            }
        });
        Event::listen(RequestHandled::class, fn (RequestHandled $event) => app(ProjectFonts::class)->handleRequest($event->request));
    }
}
