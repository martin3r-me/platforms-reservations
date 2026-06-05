<?php

namespace Platform\Reservation;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ReservationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Config laden
        $this->mergeConfigFrom(__DIR__ . '/../config/reservation.php', 'reservation');

        // Modul registrieren (falls platform-core vorhanden)
        $this->registerModule();

        // Migrationen laden
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Config veröffentlichen
        $this->publishes([
            __DIR__ . '/../config/reservation.php' => config_path('reservation.php'),
        ], 'reservation-config');

        // Views laden
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'reservation');

        // Views veröffentlichen
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/reservation'),
        ], 'reservation-views');

        // Livewire-Komponenten registrieren
        $this->registerLivewireComponents();
    }

    protected function registerModule(): void
    {
        try {
            if (
                class_exists(\Platform\Core\PlatformCore::class) &&
                Schema::hasTable('modules')
            ) {
                \Platform\Core\PlatformCore::registerModule([
                    'key'        => 'reservation',
                    'title'      => 'PausePlus',
                    'group'      => 'operations',
                    'routing'    => config('reservation.routing'),
                    'guard'      => config('reservation.guard'),
                    'navigation' => config('reservation.navigation'),
                    'sidebar'    => config('reservation.sidebar'),
                ]);

                if (\Platform\Core\PlatformCore::getModule('reservation')) {
                    // Authentifizierte Admin-Routen (Prefix + Middleware via ModuleRouter)
                    \Platform\Core\Routing\ModuleRouter::group('reservation', function () {
                        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
                    });

                    // Öffentliche Gast-Routen (z.B. Tischplan-Buchung) – ohne Auth
                    \Platform\Core\Routing\ModuleRouter::group('reservation', function () {
                        $this->loadRoutesFrom(__DIR__ . '/../routes/guest.php');
                    }, requireAuth: false);
                }
            }
        } catch (\Throwable $e) {
            // Silent fail – platform-core nicht installiert
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath      = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Reservation\\Livewire';
        $prefix        = 'reservation';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath    = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class        = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias     = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
