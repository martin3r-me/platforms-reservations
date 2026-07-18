<?php

namespace Platform\Reservation\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Services\SeatAvailabilityService;

/**
 * Öffentliche (token-gesicherte) Read-Endpunkte der Gast-API: Termine,
 * Termin-Details, Produkte (Verkaufsliste) und Tischplan mit Verfügbarkeit.
 */
class GuestEventController extends GuestApiController
{
    /** GET /guest/events – kommende, veröffentlichte Termine. */
    public function index(): JsonResponse
    {
        $events = Event::withoutGlobalScope('team')
            ->where('team_id', $this->guestTeamId())
            ->where('status', Event::STATUS_PUBLISHED)
            ->whereDate('date', '>=', now()->toDateString())
            ->with(['venue' => fn ($q) => $q->withoutGlobalScope('team')])
            ->orderBy('date')
            ->get()
            ->map(fn (Event $e) => [
                'uuid'  => $e->uuid,
                'name'  => $e->name,
                'date'  => $e->date?->toDateString(),
                'venue' => $e->venue?->name,
            ]);

        return response()->json(['events' => $events]);
    }

    /** GET /guest/events/{uuid} – Termin mit Slots und Räumen. */
    public function show(string $uuid): JsonResponse
    {
        $event = $this->findEvent($uuid, [
            'venue'                => fn ($q) => $q->withoutGlobalScope('team'),
            'slots',
            'eventRooms.floorPlan' => fn ($q) => $q->withoutGlobalScope('team'),
        ]);

        if (!$event) {
            return response()->json(['message' => 'Termin nicht gefunden.'], 404);
        }

        $settings = CheckoutSetting::forTeam($this->guestTeamId());

        return response()->json([
            'uuid'         => $event->uuid,
            'name'         => $event->name,
            'description'  => $event->description,
            'date'         => $event->date?->toDateString(),
            'orderable'    => $event->isOrderable(),
            'venue'        => $event->venue?->name,
            // #520/#521: welche Anmeldefelder das Frontend abfragt (required|optional|hidden).
            // name & count sind stets Pflicht.
            'guest_fields' => [
                'name'  => 'required',
                'count' => 'required',
                'email' => $settings->fieldMode('email'),
                'phone' => $settings->fieldMode('phone'),
                'notes' => $settings->fieldMode('notes'),
            ],
            'slots'        => $event->slots->map(fn ($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'time_start' => $s->time_start,
                'time_end'   => $s->time_end,
            ])->values(),
            'rooms'        => $event->eventRooms->map(fn ($r) => [
                'id'         => $r->id,
                'name'       => $r->floorPlan?->name,
                'floor_plan_id' => $r->floor_plan_id,
            ])->values(),
        ]);
    }

    /** GET /guest/events/{uuid}/products – gast-sichtbare Artikel der Verkaufsliste. */
    public function products(string $uuid): JsonResponse
    {
        $event = $this->findEvent($uuid);

        if (!$event) {
            return response()->json(['message' => 'Termin nicht gefunden.'], 404);
        }

        $items = $this->visibleItems($event)->map(fn (MenuItem $item) => [
            'id'            => $item->id,
            'name'          => $item->name,
            'description'   => $item->description,
            'portion_size'  => $item->portion_size,
            'price'         => (float) $item->price,
            'tax_rate'      => (float) $item->tax_rate,
            'is_vegetarian' => $item->is_vegetarian,
            'is_vegan'      => $item->is_vegan,
            'is_alcoholic'  => $item->is_alcoholic,
            'category'      => $item->category?->name,
            'allergens'     => $item->allergens->pluck('code')->values(),
            'additives'     => $item->additives->pluck('code')->values(),
            'image_url'     => $item->image_context_file_id ? $item->imageUrl('medium_1_1') : null,
        ]);

        return response()->json(['products' => $items->values()]);
    }

    /** GET /guest/events/{uuid}/floor-plan?room=ID – Tische + Verfügbarkeit je Pause. */
    public function floorPlan(Request $request, string $uuid): JsonResponse
    {
        $event = $this->findEvent($uuid, [
            'slots',
            'eventRooms.floorPlan' => fn ($q) => $q->withoutGlobalScope('team'),
        ]);

        if (!$event) {
            return response()->json(['message' => 'Termin nicht gefunden.'], 404);
        }

        $room = $event->eventRooms->firstWhere('id', (int) $request->query('room'))
            ?? $event->eventRooms->first();

        if (!$room || !$room->floorPlan) {
            return response()->json(['message' => 'Kein Raum/Tischplan verfügbar.'], 404);
        }

        /** @var FloorPlan $floorPlan */
        $floorPlan = $room->floorPlan;
        $tables    = $floorPlan->tables()->withoutGlobalScope('team')->where('is_active', true)->get();
        $seats     = app(SeatAvailabilityService::class);

        // Restplätze je Tisch und Pause.
        $availability = [];
        foreach ($event->slots as $slot) {
            $bookedByTable = $seats->bookedSeatsByTable($floorPlan, $slot);
            foreach ($tables as $table) {
                $booked = $bookedByTable->get($table->id, 0);
                $remaining = $event->isTableDisabled($table->id) ? 0 : max(0, $table->capacity - $booked);
                $availability[$slot->id][$table->id] = $remaining;
            }
        }

        return response()->json([
            'room_id'        => $room->id,
            'floor_plan'     => [
                'name'                => $floorPlan->name,
                'background_url'      => $floorPlan->backgroundUrl(),
                'background_rotation' => $floorPlan->background_rotation,
                'aspect'              => $floorPlan->displayAspect(),
            ],
            'tables'         => $tables->map(fn ($t) => [
                'id'       => $t->id,
                'label'    => $t->label,
                'capacity' => $t->capacity,
                'shape'    => $t->shape,
                'x_pct'    => (float) $t->x_pct,
                'y_pct'    => (float) $t->y_pct,
                'w_pct'    => (float) $t->w_pct,
                'h_pct'    => (float) $t->h_pct,
            ])->values(),
            'availability'   => $availability, // { slot_id: { table_id: remaining } }
        ]);
    }

    /** Gast-sichtbare Artikel der Event-Verkaufsliste (scope-sicher, mit Relationen). */
    protected function visibleItems(Event $event)
    {
        $salesList = $event->sales_list_id
            ? SalesList::withoutGlobalScope('team')->find($event->sales_list_id)
            : SalesList::withoutGlobalScope('team')
                ->where('team_id', $event->team_id)
                ->where('is_default', true)
                ->first();

        if (!$salesList) {
            return collect();
        }

        return $salesList->menuItems()
            ->withoutGlobalScope('team')
            ->where('approval_status', MenuItem::APPROVAL_APPROVED)
            ->where('available', true)
            ->with(['allergens', 'additives', 'category', 'imageFile.variants'])
            ->orderBy('sort_order')
            ->get();
    }
}
