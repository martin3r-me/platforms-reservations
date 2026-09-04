<?php

namespace Platform\Reservation\Support;

use Platform\Reservation\Models\MenuItem;

/**
 * Prüfen und Setzen der Bundle-Bestandteile.
 *
 * Einmal ausprogrammiert, weil Create-Tool, Update-Tool und die Livewire-Maske
 * dieselben Schranken brauchen. Liefen sie auseinander, ließe sich über den
 * schwächeren Weg etwas anlegen, das die anderen ausschließen.
 */
class BundleComponents
{
    /**
     * Eingabe normalisieren: [['component_id' => int, 'quantity' => int], …]
     * wird zu [component_id => quantity].
     *
     * @return array<int, int>
     */
    public static function normalize(array $input): array
    {
        $out = [];

        foreach ($input as $row) {
            $id = (int) ($row['component_id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $out[$id] = max(1, min(99, (int) ($row['quantity'] ?? 1)));
        }

        return $out;
    }

    /**
     * Fachliche Prüfung. Gibt die Fehlermeldung zurück oder null, wenn alles passt.
     *
     * @param  array<int, int>  $components  [component_id => quantity]
     */
    public static function validate(array $components, int $teamId, ?int $selfId = null): ?string
    {
        if ($components === []) {
            return 'Ein Bundle braucht mindestens einen Bestandteil.';
        }

        $ids = array_keys($components);

        if ($selfId !== null && in_array($selfId, $ids, true)) {
            return 'Ein Bundle kann sich nicht selbst enthalten.';
        }

        $found = MenuItem::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'is_bundle']);

        if ($found->count() !== count($ids)) {
            return 'Mindestens ein Bestandteil wurde nicht gefunden oder gehört nicht zum Team.';
        }

        // Verschachtelte Bundles würden die Preisverteilung in eine
        // Endlosschleife schicken.
        $nested = $found->where('is_bundle', true)->pluck('name');

        if ($nested->isNotEmpty()) {
            return 'Bundles können keine Bundles enthalten: ' . $nested->implode(', ');
        }

        return null;
    }

    /**
     * Hinweis, wenn das Bundle keinen Preisvorteil bringt – sonst null.
     *
     * Bewusst KEINE Validierung: ein Bundle ohne Ersparnis ist meist ein
     * Zahlendreher, kann aber gewollt sein (etwa ein Bundle, das nur die
     * Bestellung vereinfacht). Deshalb wird gemeldet, nicht geblockt.
     *
     * Erwartet ein Item mit geladenen components (samt Pivot-Menge).
     */
    public static function priceNotice(MenuItem $item): ?string
    {
        $reference = round($item->bundleReferencePrice() ?? 0.0, 2);
        $price     = round((float) $item->price, 2);

        if ($reference <= 0 || $price < $reference) {
            return null;
        }

        $money = fn (float $v) => number_format($v, 2, ',', '.') . ' €';

        if ($price > $reference) {
            return 'Das Bundle ist teurer als die enthaltenen Artikel einzeln ('
                . $money($price) . ' statt ' . $money($reference) . ').';
        }

        return 'Das Bundle kostet genauso viel wie die enthaltenen Artikel einzeln ('
            . $money($reference) . ') – es entsteht kein Preisvorteil.';
    }

    /**
     * Bestandteile setzen (ersetzt die bisherige Zuordnung).
     *
     * @param  array<int, int>  $components  [component_id => quantity]
     */
    /** @return array{attached: array, detached: array, updated: array} was sich bewegt hat */
    public static function apply(MenuItem $item, array $components): array
    {
        $sync = [];
        $sort = 0;

        foreach ($components as $id => $quantity) {
            $sync[$id] = ['quantity' => $quantity, 'sort_order' => $sort++];
        }

        return $item->components()->sync($sync);
    }
}
