<?php

namespace App\Providers;

use App\Support\Content;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

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
    }
}
