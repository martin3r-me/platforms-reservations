<?php

namespace Platform\Reservation\Tools\Concerns;

use Platform\Reservation\Models\Additive;
use Platform\Reservation\Models\Allergen;
use Platform\Reservation\Models\MenuItem;

/**
 * Allergene und Zusatzstoffe eines Artikels über ihre CODES setzen.
 *
 * Geteilt von Anlegen und Ändern. Vorher konnte das nur das Bulk-Werkzeug: Ein
 * einzeln angelegter Artikel bekam nie Allergene, und eine falsche
 * Kennzeichnung ließ sich über die Werkzeuge gar nicht korrigieren – man musste
 * in die Oberfläche. Bei einer Angabe, die rechtlich verpflichtend ist, ist das
 * die falsche Lücke.
 *
 * Über Codes und nicht über IDs, weil die Codes die fachliche Sprache sind:
 * „A, C, G" steht so auf der Karte, die ID kennt niemand.
 *
 * Unbekannte Codes werden übergangen und ZURÜCKGEMELDET. Sie stillschweigend zu
 * schlucken hieße, eine Kennzeichnung zu verlieren, ohne dass es jemand merkt;
 * abzubrechen hieße, wegen eines Tippfehlers den ganzen Artikel zu verlieren.
 */
trait PflegtKennzeichnung
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, string>  unbekannte Codes, für die Rückmeldung
     */
    protected function kennzeichnungSetzen(MenuItem $item, array $arguments, int $teamId): array
    {
        $unbekannt = [];

        foreach ([
            'allergen_codes' => [Allergen::class, 'allergens'],
            'additive_codes' => [Additive::class, 'additives'],
        ] as $feld => [$modell, $beziehung]) {
            // array_key_exists, nicht isset: Ein leeres Array bedeutet „alle
            // entfernen" und muss ankommen. isset() unterschiede das nicht von
            // „nicht mitgeschickt".
            if (! array_key_exists($feld, $arguments) || ! is_array($arguments[$feld])) {
                continue;
            }

            $karte = $modell::where('team_id', $teamId)->pluck('id', 'code');
            $codes = array_map(fn ($c) => (string) $c, $arguments[$feld]);

            $item->{$beziehung}()->sync($karte->only($codes)->values()->all());

            $unbekannt = array_merge(
                $unbekannt,
                array_values(array_diff($codes, array_map('strval', $karte->keys()->all()))),
            );
        }

        return $unbekannt;
    }

    /** Schema-Bausteine, damit beide Werkzeuge dieselben Feldnamen anbieten. */
    protected static function kennzeichnungSchema(): array
    {
        return [
            'allergen_codes' => [
                'type'        => 'array',
                'items'       => ['type' => 'string'],
                'description' => 'Allergene als CODES ("A", "C", "G") - ersetzt die bisherigen. Leeres Array entfernt alle; weglassen laesst sie unveraendert.',
            ],
            'additive_codes' => [
                'type'        => 'array',
                'items'       => ['type' => 'string'],
                'description' => 'Zusatzstoffe als CODES ("1", "2", "11") - ersetzt die bisherigen. Leeres Array entfernt alle; weglassen laesst sie unveraendert.',
            ],
        ];
    }
}
