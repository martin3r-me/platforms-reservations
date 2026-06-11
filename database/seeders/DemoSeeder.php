<?php

namespace Platform\Reservation\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Models\Venue;

/**
 * Demo-Daten für den Klick-Dummy (Kundentermin):
 * 1 Venue mit 2 Räumen (sequentielle Freigabe), 1 publizierter Termin mit
 * 2 Pausen-Slots, 1 Verkaufsliste, ~10 freigegebene Artikel.
 *
 * Team via RESERVATION_DEMO_TEAM_ID (Fallback: erstes Team).
 * Aufruf: php artisan db:seed --class="Platform\Reservation\Database\Seeders\DemoSeeder"
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $teamId = (int) env('RESERVATION_DEMO_TEAM_ID', 0)
            ?: \Platform\Core\Models\Team::query()->value('id');

        if (!$teamId) {
            $this->command?->warn('Kein Team gefunden – Demo-Seeder übersprungen.');
            return;
        }

        $this->call([AllergenSeeder::class, AdditiveSeeder::class]);

        // ── Venue + Räume mit Tischen ────────────────────────────
        $venue = Venue::firstOrCreate(
            ['team_id' => $teamId, 'name' => 'Stadthalle'],
            ['address' => 'Johannisberg 40', 'city' => 'Wuppertal', 'is_active' => true]
        );

        $rooms = [];
        foreach (['Gartenhalle', 'Offenbachsaal'] as $roomIndex => $roomName) {
            $plan = FloorPlan::firstOrCreate(
                ['venue_id' => $venue->id, 'name' => $roomName],
                ['is_active' => true]
            );

            if ($plan->tables()->count() === 0) {
                foreach (range(0, 7) as $i) {
                    $plan->tables()->create([
                        'label'    => sprintf('%s%d', $roomIndex === 0 ? 'G' : 'O', $i + 1),
                        'capacity' => [2, 4, 4, 6][$i % 4],
                        'x'        => 80 + ($i % 4) * 180,
                        'y'        => 120 + intdiv($i, 4) * 200,
                        'width'    => 90,
                        'height'   => 90,
                        'shape'    => $i % 3 === 0 ? 'round' : 'square',
                    ]);
                }
            }

            $rooms[] = $plan;
        }

        // ── Artikel ──────────────────────────────────────────────
        $allergens = \Platform\Reservation\Models\Allergen::pluck('id', 'code');
        $additives = \Platform\Reservation\Models\Additive::pluck('id', 'code');

        $catalog = [
            'Snacks' => [
                ['Brezel', 'Ofenfrisch mit Butter', 3.50, '7.00', false, true, false, ['A'], []],
                ['Antipasti-Teller', 'Mediterranes Gemüse, Oliven, Ciabatta', 8.90, '7.00', false, true, true, ['A'], []],
                ['Käsewürfel', 'Mit Trauben und Crackern', 6.50, '7.00', false, true, false, ['A', 'G'], []],
                ['Pizza-Ecke Margherita', 'Warm serviert', 4.50, '7.00', false, true, false, ['A', 'G'], []],
            ],
            'Süßes' => [
                ['Schokoladen-Brownie', 'Hausgemacht', 4.00, '7.00', false, true, false, ['A', 'C', 'G', 'H'], []],
                ['Obstsalat', 'Saisonale Früchte', 4.50, '7.00', true, true, false, [], []],
            ],
            'Getränke' => [
                ['Mineralwasser 0,25l', 'Still oder spritzig', 2.80, '19.00', true, true, false, [], []],
                ['Cola 0,2l', '', 3.20, '19.00', true, true, false, [], ['1', '2', '11']],
                ['Riesling 0,2l', 'Trocken, von der Mosel', 6.90, '19.00', true, true, true, ['L'], []],
                ['Pils 0,33l', 'Frisch gezapft', 4.20, '19.00', false, true, true, ['A'], []],
            ],
        ];

        $allItems = [];

        foreach ($catalog as $categoryName => $items) {
            $category = MenuCategory::firstOrCreate(
                ['team_id' => $teamId, 'name' => $categoryName],
            );

            foreach ($items as [$name, $description, $price, $tax, $vegan, $vegetarian, $alcoholic, $allergenCodes, $additiveCodes]) {
                $item = MenuItem::firstOrCreate(
                    ['team_id' => $teamId, 'name' => $name],
                    [
                        'category_id'     => $category->id,
                        'description'     => $description ?: null,
                        'price'           => $price,
                        'tax_rate'        => $tax,
                        'available'       => true,
                        'is_vegan'        => $vegan,
                        'is_vegetarian'   => $vegetarian,
                        'is_alcoholic'    => $alcoholic,
                        'approval_status' => MenuItem::APPROVAL_APPROVED,
                        'approved_at'     => now(),
                    ]
                );

                $item->allergens()->syncWithoutDetaching(
                    collect($allergenCodes)->map(fn ($code) => $allergens[$code] ?? null)->filter()->all()
                );
                $item->additives()->syncWithoutDetaching(
                    collect($additiveCodes)->map(fn ($code) => $additives[$code] ?? null)->filter()->all()
                );

                $allItems[] = $item->id;
            }
        }

        // ── Verkaufsliste ────────────────────────────────────────
        $salesList = SalesList::firstOrCreate(
            ['team_id' => $teamId, 'name' => 'Konzert'],
            ['description' => 'Standard-Sortiment für Konzertabende', 'is_default' => true]
        );
        $salesList->menuItems()->syncWithoutDetaching($allItems);

        // ── Termin mit 2 Pausen + 2 Räumen (sequentiell) ────────
        $event = Event::firstOrCreate(
            ['team_id' => $teamId, 'name' => 'Bodo Wartke'],
            [
                'description'       => 'Klavierkabarett in der Stadthalle',
                'date'              => '2026-08-29',
                'order_deadline_at' => '2026-08-29 18:00:00',
                'status'            => Event::STATUS_PUBLISHED,
                'venue_id'          => $venue->id,
                'sales_list_id'     => $salesList->id,
                'room_release_mode' => Event::RELEASE_SEQUENTIAL,
            ]
        );

        if ($event->slots()->count() === 0) {
            $event->slots()->createMany([
                ['name' => 'Pause 1', 'time_start' => '20:15', 'time_end' => '20:35', 'sort_order' => 0],
                ['name' => 'Pause 2', 'time_start' => '21:30', 'time_end' => '21:50', 'sort_order' => 1],
            ]);
        }

        if ($event->eventRooms()->count() === 0) {
            foreach ($rooms as $i => $plan) {
                $event->eventRooms()->create([
                    'floor_plan_id'          => $plan->id,
                    'sort_order'             => $i,
                    'fill_threshold_percent' => 80,
                ]);
            }
        }

        $this->command?->info("PausePlus-Demo angelegt (Team {$teamId}): Termin „Bodo Wartke“ mit 2 Pausen und 2 Räumen.");
    }
}
