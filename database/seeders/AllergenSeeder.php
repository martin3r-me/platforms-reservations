<?php

namespace Platform\Reservation\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Reservation\Models\Allergen;

class AllergenSeeder extends Seeder
{
    /**
     * Die 14 kennzeichnungspflichtigen Allergene (LMIV) mit Buchstaben-Legende A–N.
     */
    public function run(): void
    {
        $allergens = [
            ['code' => 'A', 'name' => 'Glutenhaltiges Getreide'],
            ['code' => 'B', 'name' => 'Krebstiere'],
            ['code' => 'C', 'name' => 'Eier'],
            ['code' => 'D', 'name' => 'Fisch'],
            ['code' => 'E', 'name' => 'Erdnüsse'],
            ['code' => 'F', 'name' => 'Soja'],
            ['code' => 'G', 'name' => 'Milch (einschl. Laktose)'],
            ['code' => 'H', 'name' => 'Schalenfrüchte (Nüsse)'],
            ['code' => 'I', 'name' => 'Sellerie'],
            ['code' => 'J', 'name' => 'Senf'],
            ['code' => 'K', 'name' => 'Sesam'],
            ['code' => 'L', 'name' => 'Schwefeldioxid und Sulfite'],
            ['code' => 'M', 'name' => 'Lupinen'],
            ['code' => 'N', 'name' => 'Weichtiere'],
        ];

        foreach ($allergens as $allergen) {
            Allergen::firstOrCreate(
                ['code' => $allergen['code']],
                ['name' => $allergen['name']]
            );
        }
    }
}
