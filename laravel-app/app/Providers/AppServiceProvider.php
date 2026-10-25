<?php

namespace App\Providers;

use App\Contracts\KipRecipientProvider;
use App\Contracts\UnusaAcademicProvider;
use App\Contracts\UnusaFacilityProvider;
use App\Events\KegiatanUpdatedEvent;
use App\Listeners\SendKegiatanUpdatedNotification;
use App\Models\CustomFormSubmission;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KipkDocument;
use App\Models\User;
use App\Policies\CustomFormSubmissionPolicy;
use App\Policies\KegiatanPolicy;
use App\Policies\KegiatanPresensiPolicy;
use App\Policies\KipkDocumentPolicy;
use App\Policies\UserPolicy;
use App\Services\Academic\ApiUnusaAcademicProvider;
use App\Services\Academic\DatabaseUnusaAcademicProvider;
use App\Services\Facilities\ConfigUnusaFacilityProvider;
use App\Services\Kip\ApiKipRecipientProvider;
use App\Services\Kip\DatabaseKipRecipientProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\ResponseFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ResponseFactory::class, function () {
            return new ResponseFactory;
        });

        $this->app->bind(
            UnusaFacilityProvider::class,
            ConfigUnusaFacilityProvider::class
        );

        // Sumber data dipilih lewat config/unusa.php ("database" = tabel lokal hasil impor, "api" = kerangka API).
        $this->app->bind(UnusaAcademicProvider::class, fn ($app) => $app->make(
            config('unusa.academic_provider') === 'api'
                ? ApiUnusaAcademicProvider::class
                : DatabaseUnusaAcademicProvider::class
        ));

        $this->app->bind(KipRecipientProvider::class, fn ($app) => $app->make(
            config('unusa.kip_recipient_provider') === 'api'
                ? ApiKipRecipientProvider::class
                : DatabaseKipRecipientProvider::class
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        // Client @inertiajs/react v3 membaca halaman awal HANYA dari
        // <script data-page="app" type="application/json">, bukan dari atribut data-page pada div root.
        // JSON_HEX_TAG mencegah "</script>" di dalam props menutup elemen script lebih awal.
        Blade::directive('inertia', function () {
            return '<script data-page="app" type="application/json"><?php echo json_encode($page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?></script>'
                .'<div id="app"></div>';
        });

        Blade::directive('inertiaHead', function () {
            return '';
        });

        Event::listen(
            KegiatanUpdatedEvent::class,
            SendKegiatanUpdatedNotification::class
        );

        Gate::policy(Kegiatan::class, KegiatanPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(KipkDocument::class, KipkDocumentPolicy::class);
        Gate::policy(CustomFormSubmission::class, CustomFormSubmissionPolicy::class);
        Gate::policy(KegiatanPresensi::class, KegiatanPresensiPolicy::class);
    }
}
