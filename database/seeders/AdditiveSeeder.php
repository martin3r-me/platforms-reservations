<?php

namespace Platform\Reservation\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Reservation\Models\Additive;

class AdditiveSeeder extends Seeder
{
    /**
     * Standardisierte Zusatzstoff-Legende (Nummern 1–x).
     * Interne Kundenlegende wird beim CSV-Import auf diese Codes gemappt.
     */
    public function run(): void
    {
        $additives = [
            ['code' => '1',  'name' => 'Farbstoff'],
            ['code' => '2',  'name' => 'Konservierungsstoff'],
            ['code' => '3',  'name' => 'Antioxidationsmittel'],
            ['code' => '4',  'name' => 'Geschmacksverstärker'],
            ['code' => '5',  'name' => 'geschwefelt'],
            ['code' => '6',  'name' => 'geschwärzt'],
            ['code' => '7',  'name' => 'gewachst'],
            ['code' => '8',  'name' => 'Phosphat'],
            ['code' => '9',  'name' => 'Süßungsmittel'],
            ['code' => '10', 'name' => 'enthält eine Phenylalaninquelle'],
            ['code' => '11', 'name' => 'koffeinhaltig'],
            ['code' => '12', 'name' => 'chininhaltig'],
            ['code' => '13', 'name' => 'Taurin'],
        ];

        foreach ($additives as $additive) {
            Additive::firstOrCreate(
                ['code' => $additive['code']],
                ['name' => $additive['name']]
            );
        }
    }
}
