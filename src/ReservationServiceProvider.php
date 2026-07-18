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
        // Mollie-Credential-Seam: Standard liest die verschlüsselte Team-
        // Einstellung (mit ENV-Fallback). Für eine integrations-basierte
        // Quelle hier einfach ein anderes Binding setzen.
        $this->app->bind(
            \Platform\Reservation\Contracts\MollieCredentialResolver::class,
            \Platform\Reservation\Services\SettingsMollieCredentialResolver::class,
        );
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

        // MCP-Tools registrieren (read-only)
        $this->registerTools();
    }

    /**
     * MCP-Tools des Moduls bei der zentralen ToolRegistry anmelden.
     * Läuft inert, wenn platform-core (Registry) nicht vorhanden ist.
     */
    protected function registerTools(): void
    {
        try {
            if (!class_exists(\Platform\Core\Tools\ToolRegistry::class)) {
                return;
            }

            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);
            $registry->register(new \Platform\Reservation\Tools\ReservationOverviewTool());
            $registry->register(new \Platform\Reservation\Tools\ListEventsTool());
            $registry->register(new \Platform\Reservation\Tools\ListBookingsTool());
            $registry->register(new \Platform\Reservation\Tools\RevenueSummaryTool());

            // Allergene & Zusatzstoffe (CRUD)
            $registry->register(new \Platform\Reservation\Tools\AllergenListTool());
            $registry->register(new \Platform\Reservation\Tools\AllergenCreateTool());
            $registry->register(new \Platform\Reservation\Tools\AllergenUpdateTool());
            $registry->register(new \Platform\Reservation\Tools\AllergenDeleteTool());
            $registry->register(new \Platform\Reservation\Tools\AdditiveListTool());
            $registry->register(new \Platform\Reservation\Tools\AdditiveCreateTool());
            $registry->register(new \Platform\Reservation\Tools\AdditiveUpdateTool());
            $registry->register(new \Platform\Reservation\Tools\AdditiveDeleteTool());

            // Menü-Kategorien & Artikel/Speisen (CRUD)
            $registry->register(new \Platform\Reservation\Tools\MenuCategoryListTool());
            $registry->register(new \Platform\Reservation\Tools\MenuCategoryCreateTool());
            $registry->register(new \Platform\Reservation\Tools\MenuCategoryUpdateTool());
            $registry->register(new \Platform\Reservation\Tools\MenuCategoryDeleteTool());
            $registry->register(new \Platform\Reservation\Tools\MenuItemListTool());
            $registry->register(new \Platform\Reservation\Tools\MenuItemCreateTool());
            $registry->register(new \Platform\Reservation\Tools\MenuItemUpdateTool());
            $registry->register(new \Platform\Reservation\Tools\MenuItemDeleteTool());

            // Termine & Pausen-Slots (CRUD + publish)
            $registry->register(new \Platform\Reservation\Tools\EventCreateTool());
            $registry->register(new \Platform\Reservation\Tools\EventUpdateTool());
            $registry->register(new \Platform\Reservation\Tools\EventDeleteTool());
            $registry->register(new \Platform\Reservation\Tools\EventPublishTool());
            $registry->register(new \Platform\Reservation\Tools\EventSlotListTool());
            $registry->register(new \Platform\Reservation\Tools\EventSlotCreateTool());
            $registry->register(new \Platform\Reservation\Tools\EventSlotUpdateTool());
            $registry->register(new \Platform\Reservation\Tools\EventSlotDeleteTool());

            // Venues (CRUD)
            $registry->register(new \Platform\Reservation\Tools\VenueListTool());
            $registry->register(new \Platform\Reservation\Tools\VenueCreateTool());
            $registry->register(new \Platform\Reservation\Tools\VenueUpdateTool());
            $registry->register(new \Platform\Reservation\Tools\VenueDeleteTool());

            // Verkaufslisten (CRUD + Artikel-Zuordnung)
            $registry->register(new \Platform\Reservation\Tools\SalesListListTool());
            $registry->register(new \Platform\Reservation\Tools\SalesListCreateTool());
            $registry->register(new \Platform\Reservation\Tools\SalesListUpdateTool());
            $registry->register(new \Platform\Reservation\Tools\SalesListDeleteTool());
            $registry->register(new \Platform\Reservation\Tools\SalesListAssignItemsTool());

            // Tischpläne & Tische (CRUD)
            $registry->register(new \Platform\Reservation\Tools\FloorPlanListTool());
            $registry->register(new \Platform\Reservation\Tools\FloorPlanCreateTool());
            $registry->register(new \Platform\Reservation\Tools\FloorPlanUpdateTool());
            $registry->register(new \Platform\Reservation\Tools\FloorPlanDeleteTool());
            $registry->register(new \Platform\Reservation\Tools\TableListTool());
            $registry->register(new \Platform\Reservation\Tools\TableCreateTool());
            $registry->register(new \Platform\Reservation\Tools\TableUpdateTool());
            $registry->register(new \Platform\Reservation\Tools\TableDeleteTool());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'Reservation: Tool-Registrierung fehlgeschlagen',
                ['error' => $e->getMessage()],
            );
        }
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

                    // Öffentliche API-Routen (Mollie-Webhook) – /api/reservation, ohne CSRF/Auth
                    \Platform\Core\Routing\ModuleRouter::apiGroup('reservation', function () {
                        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
                    }, requireAuth: false);

                    // Token-gesicherte Gast-API – /api/reservation/guest/*, Passport (api.auth)
                    \Platform\Core\Routing\ModuleRouter::apiGroup('reservation', function () {
                        $this->loadRoutesFrom(__DIR__ . '/../routes/guest-api.php');
                    }, requireAuth: true);
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
