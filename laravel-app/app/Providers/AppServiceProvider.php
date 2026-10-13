<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\Inertia\ResponseFactory::class, function () {
            return new \Inertia\ResponseFactory();
        });

        $this->app->bind(
            \App\Contracts\UnusaFacilityProvider::class,
            \App\Services\Facilities\ConfigUnusaFacilityProvider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        \Illuminate\Support\Facades\Blade::directive('inertia', function () {
            return '<div id="app" data-page="{{ json_encode($page) }}"></div>';
        });

        \Illuminate\Support\Facades\Blade::directive('inertiaHead', function () {
            return '';
        });

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\KegiatanUpdatedEvent::class,
            \App\Listeners\SendKegiatanUpdatedNotification::class
        );
    }
}
