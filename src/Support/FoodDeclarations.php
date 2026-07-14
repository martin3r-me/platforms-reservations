<?php

namespace Platform\Reservation\Support;

use Platform\Reservation\Models\Additive;
use Platform\Reservation\Models\Allergen;

/**
 * Standard-Legende (LMIV) für Allergene und Zusatzstoffe – dient als
 * Vorlage, die pro Team in die pflegbaren Stammlisten übernommen wird.
 */
class FoodDeclarations
{
    /** @return array<int, array{code:string,name:string}> */
    public static function allergens(): array
    {
        return [
            ['code' => 'A',  'name' => 'Glutenhaltiges Getreide'],
            ['code' => 'A1', 'name' => 'enthält Weizen'],
            ['code' => 'A2', 'name' => 'enthält Roggen'],
            ['code' => 'A3', 'name' => 'enthält Gerste'],
            ['code' => 'A4', 'name' => 'enthält Hafer'],
            ['code' => 'A5', 'name' => 'enthält Dinkel'],
            ['code' => 'A6', 'name' => 'enthält Kamut'],
            ['code' => 'B',  'name' => 'enthält Krebstiere'],
            ['code' => 'C',  'name' => 'enthält Eier'],
            ['code' => 'D',  'name' => 'enthält Fisch'],
            ['code' => 'E',  'name' => 'enthält Erdnuss'],
            ['code' => 'F',  'name' => 'enthält Soja'],
            ['code' => 'G',  'name' => 'enthält Milch und Milcherzeugnisse'],
            ['code' => 'H',  'name' => 'enthält Schalenfrüchte/Nüsse'],
            ['code' => 'H1', 'name' => 'enthält Mandel'],
            ['code' => 'H2', 'name' => 'enthält Haselnuss'],
            ['code' => 'H3', 'name' => 'enthält Walnuss'],
            ['code' => 'H4', 'name' => 'enthält Kaschunuss'],
            ['code' => 'H5', 'name' => 'enthält Pekannuss'],
            ['code' => 'H6', 'name' => 'enthält Paranuss'],
            ['code' => 'H7', 'name' => 'enthält Pistazie'],
            ['code' => 'H8', 'name' => 'enthält Macadamianuss'],
            ['code' => 'I',  'name' => 'enthält Sellerie'],
            ['code' => 'J',  'name' => 'enthält Senf'],
            ['code' => 'K',  'name' => 'enthält Sesam'],
            ['code' => 'L',  'name' => 'enthält Schwefeldioxid'],
            ['code' => 'M',  'name' => 'enthält Lupine'],
            ['code' => 'N',  'name' => 'enthält Weichtiere'],
        ];
    }

    /** @return array<int, array{code:string,name:string}> */
    public static function additives(): array
    {
        return [
            ['code' => '1',    'name' => 'mit Farbstoff'],
            ['code' => '1.1',  'name' => 'Zuckerkulör E150d'],
            ['code' => '1.2',  'name' => 'Carotine E160e'],
            ['code' => '2',    'name' => 'mit Konservierungsstoff'],
            ['code' => '2.1',  'name' => 'Natriumbenzoat'],
            ['code' => '3',    'name' => 'mit Antioxidationsmittel'],
            ['code' => '3.1',  'name' => 'Ascorbinsäure'],
            ['code' => '4',    'name' => 'mit Geschmacksverstärker'],
            ['code' => '5',    'name' => 'geschwefelt'],
            ['code' => '6',    'name' => 'geschwärzt'],
            ['code' => '7',    'name' => 'gewachst'],
            ['code' => '8',    'name' => 'mit Phosphat'],
            ['code' => '9',    'name' => 'mit Süßungsmittel'],
            ['code' => '9.1',  'name' => 'Acesulfam-K'],
            ['code' => '10',   'name' => 'Aspartam & Phenylalaninquelle'],
            ['code' => '11',   'name' => 'mit Milcheiweiß'],
            ['code' => '12',   'name' => 'Koffeinhaltig'],
        ];
    }

    /**
     * Legt die Standard-Legende für ein Team an (idempotent, ohne Überschreiben
     * vorhandener Einträge). Gibt zurück, wie viele neu angelegt wurden.
     *
     * @return array{allergens:int, additives:int}
     */
    public static function ensureForTeam(int $teamId): array
    {
        $a = 0;
        foreach (self::allergens() as $row) {
            $created = Allergen::firstOrCreate(
                ['team_id' => $teamId, 'code' => $row['code']],
                ['name' => $row['name']],
            )->wasRecentlyCreated;
            $a += $created ? 1 : 0;
        }

        $z = 0;
        foreach (self::additives() as $row) {
            $created = Additive::firstOrCreate(
                ['team_id' => $teamId, 'code' => $row['code']],
                ['name' => $row['name']],
            )->wasRecentlyCreated;
            $z += $created ? 1 : 0;
        }

        return ['allergens' => $a, 'additives' => $z];
    }
}
