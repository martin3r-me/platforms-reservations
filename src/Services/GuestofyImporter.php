<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Facades\Http;
use Platform\Reservation\Models\FloorPlan;

/**
 * Importiert Räume samt Tischplänen aus dem Alt-System (Guestofy,
 * WordPress/WooCommerce) über dessen öffentliche AJAX-API.
 *
 * Fokus bewusst auf Räume/Tischpläne – das ist die aufwändige, stabile
 * Handarbeit (Positionen, Kapazitäten). Termine/Produkte werden separat
 * gepflegt (Termine sind saisonal, Produkte kommen über den CSV-Import).
 */
class GuestofyImporter
{
    // Zielfläche in unserer Canvas (Rand eingerechnet).
    private const CANVAS_W = 760;
    private const CANVAS_H = 560;
    private const PADDING  = 40;

    /**
     * Räume + Tische vom Alt-System holen (normalisiert, ohne DB-Schreibzugriff).
     *
     * @return array<int, array{name:string, tables:array<int, array{number:mixed, capacity:int, x:float, y:float}>}>
     */
    public function fetchRooms(string $baseUrl): array
    {
        $url = rtrim(trim($baseUrl), '/') . '/wp-admin/admin-ajax.php';

        $response = Http::asForm()->timeout(15)->post($url, [
            'action' => 'reservations_get_rooms',
        ]);

        if (!$response->ok()) {
            throw new \RuntimeException('Alt-System nicht erreichbar (HTTP ' . $response->status() . ').');
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new \RuntimeException('Unerwartete Antwort vom Alt-System – bitte URL prüfen.');
        }

        return collect($data)->map(fn ($room) => [
            'name'   => (string) ($room['name'] ?? 'Raum'),
            'tables' => collect($room['tables'] ?? [])->map(fn ($t) => [
                'number'   => $t['number'] ?? null,
                'capacity' => (int) ($t['capacity'] ?? 2),
                'x'        => (float) ($t['position']['x'] ?? 0),
                'y'        => (float) ($t['position']['y'] ?? 0),
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Räume als FloorPlans (mit Tischen) unter einem Venue anlegen.
     * Räume mit bereits existierendem Namen werden übersprungen.
     *
     * @return array{created:int, skipped:int, tables:int}
     */
    public function importRooms(array $rooms, int $venueId): array
    {
        $created = 0;
        $skipped = 0;
        $tables  = 0;

        foreach ($rooms as $room) {
            $name = trim((string) ($room['name'] ?? '')) ?: 'Raum';

            if (FloorPlan::where('venue_id', $venueId)->where('name', $name)->exists()) {
                $skipped++;
                continue;
            }

            $plan = FloorPlan::create([
                'venue_id'  => $venueId,
                'name'      => $name,
                'is_active' => true,
            ]);

            foreach ($this->fitPositions($room['tables'] ?? []) as $t) {
                $plan->tables()->create([
                    'label'     => (string) ($t['number'] ?? ''),
                    'capacity'  => max(1, (int) ($t['capacity'] ?? 2)),
                    'x'         => $t['x'],
                    'y'         => $t['y'],
                    'width'     => 80,
                    'height'    => 80,
                    'shape'     => 'square',
                    'is_active' => true,
                ]);
                $tables++;
            }

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'tables' => $tables];
    }

    /**
     * Skaliert die (teils sehr großen) Alt-Koordinaten proportional in unsere
     * Canvas, damit importierte Pläne direkt sauber aussehen.
     */
    protected function fitPositions(array $tables): array
    {
        if (empty($tables)) {
            return [];
        }

        $xs = array_column($tables, 'x');
        $ys = array_column($tables, 'y');
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);

        $spanX = max(1.0, $maxX - $minX);
        $spanY = max(1.0, $maxY - $minY);

        $scale = min(
            (self::CANVAS_W - 2 * self::PADDING) / $spanX,
            (self::CANVAS_H - 2 * self::PADDING) / $spanY,
            1.0,
        );

        return collect($tables)->map(function ($t) use ($minX, $minY, $scale) {
            return [
                'number'   => $t['number'] ?? null,
                'capacity' => $t['capacity'] ?? 2,
                'x'        => round(self::PADDING + ($t['x'] - $minX) * $scale),
                'y'        => round(self::PADDING + ($t['y'] - $minY) * $scale),
            ];
        })->all();
    }
}
