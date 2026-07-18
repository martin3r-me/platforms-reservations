<?php

namespace Platform\Reservation\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Automatische Mandanten-Trennung (Multi-Tenancy) für Models mit eigener
 * team_id-Spalte.
 *
 * - Ein globaler Scope filtert JEDE Query (inkl. find/findOrFail) auf das
 *   aktive Team des eingeloggten Users (Auth::user()->currentTeam). Damit sind
 *   Cross-Team-Zugriffe (IDOR) in den Admin-Livewire-Komponenten strukturell
 *   ausgeschlossen – ein findOrFail() auf eine fremde ID liefert 404 statt der
 *   fremden Ressource.
 * - OHNE eingeloggten User (Gast-Checkout, Mollie-Webhook, Konsole/Seeder)
 *   greift der Scope NICHT. Dort wird das Team weiterhin explizit aus dem
 *   Kontext hergeleitet (z.B. Event->team_id, Booking-UUID).
 * - team_id wird beim Anlegen automatisch aus dem aktiven Team gesetzt, sofern
 *   nicht bereits explizit gesetzt.
 *
 * Muster analog zu Platform\Helpdesk (addGlobalScope('team', ...)).
 *
 * NICHT verwenden für:
 * - Allergen/Additive (nullable Hybrid: team_id NULL = global sichtbar),
 * - indirekt zugeordnete Models ohne eigene team_id (FloorPlan, Table,
 *   BookingItem, Payment, EventSlot, EventRoom) – diese erben die Trennung
 *   über ihre gescopte Elternressource.
 *
 * Bewusste Cross-Team-Zugriffe (falls je nötig) via ->withoutGlobalScope('team').
 */
trait BelongsToTeam
{
    protected static function bootBelongsToTeam(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void {
            $team = Auth::check() ? Auth::user()->currentTeam : null;

            if ($team) {
                // Tabellen-qualifiziert wegen Joins (belongsToMany/whereHas).
                $builder->where($builder->getModel()->getTable() . '.team_id', $team->id);
            }
        });

        static::creating(function ($model): void {
            if (! $model->team_id && Auth::check() && Auth::user()->currentTeam) {
                $model->team_id = Auth::user()->currentTeam->id;
            }
        });
    }
}
