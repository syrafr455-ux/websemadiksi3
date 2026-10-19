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

        // Client @inertiajs/react v3 membaca halaman awal HANYA dari
        // <script data-page="app" type="application/json">, bukan dari atribut data-page pada div root.
        // JSON_HEX_TAG mencegah "</script>" di dalam props menutup elemen script lebih awal.
        \Illuminate\Support\Facades\Blade::directive('inertia', function () {
            return '<script data-page="app" type="application/json"><?php echo json_encode($page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?></script>'
                .'<div id="app"></div>';
        });

        \Illuminate\Support\Facades\Blade::directive('inertiaHead', function () {
            return '';
        });

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\KegiatanUpdatedEvent::class,
            \App\Listeners\SendKegiatanUpdatedNotification::class
        );

        \Illuminate\Support\Facades\Gate::policy(\App\Models\Kegiatan::class, \App\Policies\KegiatanPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\KipkDocument::class, \App\Policies\KipkDocumentPolicy::class);
    }
}
