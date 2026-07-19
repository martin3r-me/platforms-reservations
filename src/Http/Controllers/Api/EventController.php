<?php

namespace Platform\Reservation\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Core\Models\Team;
use Platform\Reservation\Enums\EventStatus;
use Platform\Reservation\Exceptions\GuestOrderException;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Models\Translation;
use Platform\Reservation\Services\GuestOrderService;
use Platform\Reservation\Services\SeatAvailabilityService;

/**
 * Token-gesicherte Read-API für Termine (Veranstaltungen).
 *
 * Gleiches Grundmuster wie helpdesk/planner (ApiController + Passport api.auth),
 * jedoch ein fachlicher Events-Endpunkt (kein Datawarehouse-Feed). Standardmäßig
 * werden alle zukünftigen und nicht geschlossenen Termine geliefert, inkl. Anzahl
 * Pausen sowie den zu buchenden Räumen mit Kapazitäten.
 *
 * Team-Scoping bewusst ohne Auth-Global-Scope (withoutGlobalScope), da im
 * api.auth-Kontext Auth::user()->currentTeam abweichen kann; gefiltert wird
 * optional per team_id (inkl. Kind-Teams).
 */
class EventController extends ApiController
{
    /**
     * GET /events – Liste der Termine (paginiert, gefiltert).
     */
    public function index(Request $request)
    {
        $query = Event::withoutGlobalScope('team');

        $this->applyFilters($query, $request);

        // ===== SORTING =====
        $sortBy  = $request->get('sort_by', 'date');
        $sortDir = $request->get('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSortColumns = ['id', 'date', 'created_at', 'updated_at', 'order_deadline_at', 'name'];
        $query->orderBy(in_array($sortBy, $allowedSortColumns, true) ? $sortBy : 'date', $sortDir);

        // ===== EAGER LOADS (scope-sicher) + COUNTS =====
        $query->with([
            'venue'                => fn ($q) => $q->withoutGlobalScope('team'),
            'eventRooms.floorPlan' => fn ($q) => $q->withoutGlobalScope('team'),
            'eventRooms.floorPlan.tables' => fn ($q) => $q->withoutGlobalScope('team')->where('is_active', true),
        ])->withCount([
            'slots',
            'eventRooms',
        ]);

        // ===== PAGINATION =====
        $perPage = min((int) $request->get('per_page', 100), 1000);
        $events  = $query->paginate($perPage);

        $formatted = $events->map(fn (Event $event) => $this->formatEvent($event));

        return $this->paginated(
            $events->setCollection($formatted),
            'Termine erfolgreich geladen'
        );
    }

    /**
     * GET /events/{event}/products – buchbare Artikel eines Termins.
     *
     * Die Artikel ergeben sich aus der Verkaufsliste des Termins (bzw. dem
     * Team-Default) und sind für alle Pausen des Termins identisch – daher ist
     * keine Slot-ID nötig. {event} kann UUID oder numerische ID sein.
     */
    public function products(Request $request, string $event)
    {
        $model = $this->resolveEvent($event);

        if (! $model) {
            return $this->notFound('Termin nicht gefunden.');
        }

        $locale    = $this->requestLocale($request);
        $salesList = $this->resolveSalesList($model);
        $items     = $this->visibleItems($salesList);
        $products  = $items->map(fn (MenuItem $item) => $this->formatProduct($item, $locale));

        return $this->success([
            'event' => [
                'id'   => $model->id,
                'uuid' => $model->uuid,
                'name' => $model->name,
                'date' => $model->date?->format('Y-m-d'),
            ],
            'language'       => $locale,
            'sales_list'     => $salesList?->name,
            'products_count' => $products->count(),
            'products'       => $products->values()->all(),
            // Legende: alle vorkommenden Allergene/Zusatzstoffe mit (übersetztem) Namen.
            'legend'         => $this->buildLegend($items, $locale),
        ], 'Artikel erfolgreich geladen');
    }

    /** Locale aus ?lang= (Fallback Basis-Sprache DE), locale-Format normalisiert. */
    protected function requestLocale(Request $request): string
    {
        $lang = strtolower(trim((string) $request->query('lang', '')));

        return preg_match('/^[a-z]{2}(_[a-z]{2})?$/', $lang) ? $lang : Translation::DEFAULT_LOCALE;
    }

    /**
     * Allergen-/Zusatzstoff-Legende (Code → übersetzter Name) über alle Artikel.
     */
    protected function buildLegend($items, string $locale): array
    {
        $allergens = collect();
        $additives = collect();

        foreach ($items as $item) {
            foreach ($item->allergens as $a) {
                $allergens[$a->code] = ['code' => $a->code, 'name' => $a->translate('name', $locale)];
            }
            foreach ($item->additives as $z) {
                $additives[$z->code] = ['code' => $z->code, 'name' => $z->translate('name', $locale)];
            }
        }

        return [
            'allergens' => $allergens->values()->all(),
            'additives' => $additives->values()->all(),
        ];
    }

    /**
     * GET /events/{event}/checkout-fields – Konfiguration der Anmeldefelder (#520/#521).
     *
     * Liefert je Gastfeld den Modus (required|optional|hidden), damit das externe
     * Frontend das Checkout-Formular korrekt rendert. name & count sind immer
     * Pflicht. Zusätzlich die Checkout-Texte (18+, Rechtstext, Datenschutz-Link).
     * {event} = UUID oder numerische ID.
     */
    public function checkoutFields(Request $request, string $event)
    {
        $model = $this->resolveEvent($event);

        if (! $model) {
            return $this->notFound('Termin nicht gefunden.');
        }

        $settings = CheckoutSetting::forTeam((int) $model->team_id);
        $settings->loadMissing('translations');
        $locale   = $this->requestLocale($request);

        $ageText   = $settings->translate('age_check_text', $locale) ?: $settings->ageText();
        $legalText = $settings->translate('legal_text', $locale) ?: $settings->legalText();

        return $this->success([
            'event' => [
                'id'   => $model->id,
                'uuid' => $model->uuid,
                'name' => $model->name,
            ],
            'language'  => $locale,
            'languages' => $settings->languages(), // angebotene Sprachen (DE zuerst)
            // required | optional | hidden. Vor-/Nachname & Personenzahl sind Pflicht;
            // Firma und Rechnungsadresse (billing) sind optional.
            'guest_fields' => [
                'first_name' => 'required',
                'last_name'  => 'required',
                'company'    => 'optional',
                'count'      => 'required',
                'email'      => $settings->fieldMode('email'),
                'phone'      => $settings->fieldMode('phone'),
                'notes'      => $settings->fieldMode('notes'),
                'billing'    => 'optional', // Adressblock: street, zip, city, country
            ],
            'texts' => [
                'age_check'   => $ageText,
                'legal'       => $legalText,
                'privacy_url' => $settings->privacy_url,
            ],
        ], 'Checkout-Felder geladen');
    }

    /**
     * GET /events/{event}/floor-plan – Tischplan(e) und Verfügbarkeit je Pause.
     *
     * Liefert die buchbaren Räume des Termins mit Tischplan und Tischen sowie die
     * freien Plätze JE PAUSE (Slot) und Tisch. Optional filterbar per room
     * (event_room_id oder floor_plan_id) und slot (Slot-ID). Mit ?party=N wird
     * je Pause/Tisch zusätzlich ein bookable-Flag berechnet (weiche Kapazität +
     * Limit berücksichtigt). {event} = UUID oder numerische ID.
     */
    public function floorPlan(Request $request, string $event)
    {
        $model = $this->resolveEvent($event);

        if (! $model) {
            return $this->notFound('Termin nicht gefunden.');
        }

        $model->loadMissing([
            'slots',
            'eventRooms.floorPlan'        => fn ($q) => $q->withoutGlobalScope('team'),
            'eventRooms.floorPlan.tables' => fn ($q) => $q->withoutGlobalScope('team')->where('is_active', true),
            'eventRooms.floorPlan.atmosphereFiles.variants',
        ]);

        $checkout   = CheckoutSetting::forTeam((int) $model->team_id);
        $soft       = $checkout->softTableCapacity();
        $maxGroup   = $checkout->maxGroupEmptyTable();
        $roomFilter = $request->filled('room') ? (int) $request->room : null;
        $slotFilter = $request->filled('slot') ? (int) $request->slot : null;
        // Optional: fuer eine Gruppengroesse je Tisch/Pause ein "bookable"-Flag mitliefern.
        $party      = $request->filled('party') ? max(1, (int) $request->party) : null;

        $slots = $model->slots
            ->when($slotFilter, fn ($c) => $c->where('id', $slotFilter))
            ->sortBy('sort_order')
            ->values();

        $seats = app(SeatAvailabilityService::class);

        $rooms = $model->eventRooms
            ->when($roomFilter, fn ($c) => $c->filter(
                fn ($room) => $room->id === $roomFilter || $room->floor_plan_id === $roomFilter
            ))
            ->map(fn ($room) => $this->formatRoomAvailability($room, $model, $slots, $seats, $soft, $maxGroup, $party))
            ->filter()
            ->values();

        return $this->success([
            'event' => [
                'id'   => $model->id,
                'uuid' => $model->uuid,
                'name' => $model->name,
                'date' => $model->date?->format('Y-m-d'),
            ],
            // Weiche Kapazität: Großgruppe darf einen leeren Tisch (remaining == capacity)
            // über die Platzzahl hinaus belegen (bis max_group_empty_table, null = unbegrenzt);
            // sonst muss die Gruppe in remaining passen.
            'soft_table_capacity'   => $soft,
            'max_group_empty_table' => $maxGroup,
            'party'                 => $party, // Gruppengröße, für die "bookable" berechnet wurde (null = nicht angefragt)
            'slots' => $slots->map(fn ($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'time_start' => $s->time_start,
                'time_end'   => $s->time_end,
            ])->values()->all(),
            'rooms' => $rooms->all(),
        ], 'Tischplan/Verfügbarkeit geladen');
    }

    /**
     * POST /events/{event}/orders – Bestellung anlegen (Order + N Slot-Buchungen).
     *
     * Preise/Steuer werden serverseitig aus der DB eingefroren, Artikel auf die
     * Verkaufsliste beschränkt, Mengen begrenzt und Plätze je Pause geprüft
     * (autoritativ via GuestOrderService). {event} = UUID oder numerische ID.
     * Antwort enthält ggf. eine checkout_url (Online-Zahlung).
     */
    public function createOrder(Request $request, string $event, GuestOrderService $service)
    {
        $model = $this->resolveEvent($event);

        if (! $model) {
            return $this->notFound('Termin nicht gefunden.');
        }

        // #520/#521: Pflicht/optional der Kontaktfelder aus den Team-Settings des Termins.
        $settings = CheckoutSetting::forTeam((int) $model->team_id);

        $data = $request->validate([
            'guest'                 => 'required|array',
            'guest.first_name'      => ['required', 'string', 'max:255'],
            'guest.last_name'       => ['required', 'string', 'max:255'],
            'guest.company'         => ['nullable', 'string', 'max:255'],
            'guest.email'           => $settings->guestFieldRule('email', ['email', 'max:255']),
            'guest.phone'           => $settings->guestFieldRule('phone', ['string', 'max:40']),
            'guest.count'           => ['required', 'integer', 'min:1', 'max:20'],
            'guest.notes'           => $settings->guestFieldRule('notes', ['string']),
            'guest.billing'         => ['nullable', 'array'],
            'guest.billing.street'  => ['nullable', 'string', 'max:255'],
            'guest.billing.zip'     => ['nullable', 'string', 'max:20'],
            'guest.billing.city'    => ['nullable', 'string', 'max:255'],
            'guest.billing.country' => ['nullable', 'string', 'size:2'],
            'legal_accepted'        => 'accepted',
            'age_confirmed'         => 'nullable|boolean',
            'redirect_url'          => ['nullable', 'url', 'max:2048'],
            'slots'                 => 'required|array|min:1',
            'slots.*.slot_id'       => 'required|integer',
            'slots.*.table_id'      => 'required|integer',
            'slots.*.items'         => 'required|array|min:1',
        ]);

        // Rücksprung-URL des Frontends nach der Zahlung – nur erlaubt, wenn Origin
        // der gepflegten Frontend-Basis-URL entspricht (Open-Redirect-Schutz).
        $redirectUrl = null;
        if (! empty($data['redirect_url'])) {
            if (! $settings->isAllowedRedirect($data['redirect_url'])) {
                return $this->error(
                    'redirect_url ist nicht erlaubt – Origin weicht von der hinterlegten Frontend-URL ab (oder es ist keine hinterlegt).',
                    ['code' => 'INVALID_REDIRECT'],
                    422,
                );
            }
            $redirectUrl = $data['redirect_url'];
        }

        $model->loadMissing(['slots', 'eventRooms']);

        try {
            $result = $service->place(
                $model,
                [
                    'first_name' => $data['guest']['first_name'],
                    'last_name'  => $data['guest']['last_name'],
                    'company'    => $data['guest']['company'] ?? null,
                    'email'      => $data['guest']['email'] ?? null,
                    'phone'      => $data['guest']['phone'] ?? null,
                    'count'      => (int) $data['guest']['count'],
                    'notes'      => $data['guest']['notes'] ?? null,
                    'billing'    => $data['guest']['billing'] ?? [],
                ],
                $data['slots'],
                (bool) ($data['age_confirmed'] ?? false),
                $redirectUrl,
            );
        } catch (GuestOrderException $e) {
            return $this->error($e->getMessage(), ['code' => $e->errorCode], 422);
        }

        return $this->created([
            'order_uuid'   => $result['order']->uuid,
            'total_amount' => round((float) $result['order']->total_amount, 2),
            'status'       => $result['order']->status,
            'checkout_url' => $result['checkout_url'], // null = keine Online-Zahlung nötig
        ], 'Bestellung angelegt');
    }

    /**
     * GET /events/{event}/orders/{order} – Status einer Bestellung.
     */
    public function orderStatus(string $event, string $order)
    {
        $model = $this->resolveEvent($event);

        if (! $model) {
            return $this->notFound('Termin nicht gefunden.');
        }

        $orderModel = Order::withoutGlobalScope('team')
            ->where('team_id', $model->team_id)
            ->where('event_id', $model->id)
            ->where('uuid', $order)
            ->with(['payment', 'bookings' => fn ($q) => $q->withoutGlobalScope('team')->with(['slot', 'table', 'items.menuItem'])])
            ->first();

        if (! $orderModel) {
            return $this->notFound('Bestellung nicht gefunden.');
        }

        return $this->success($this->formatOrderStatus($orderModel), 'Bestellstatus geladen');
    }

    /**
     * GET /orders/{order} – Bestellstatus allein per Order-UUID (fürs Polling,
     * ohne Termin-Kontext). Token-gesichert; die UUID ist unrätbar.
     */
    public function orderByUuid(string $order)
    {
        $orderModel = Order::withoutGlobalScope('team')
            ->where('uuid', $order)
            ->with(['payment', 'bookings' => fn ($q) => $q->withoutGlobalScope('team')->with(['slot', 'table', 'items.menuItem'])])
            ->first();

        if (! $orderModel) {
            return $this->notFound('Bestellung nicht gefunden.');
        }

        return $this->success($this->formatOrderStatus($orderModel), 'Bestellstatus geladen');
    }

    /** Einheitliche Status-Darstellung einer Bestellung (fürs Polling). */
    protected function formatOrderStatus(Order $order): array
    {
        return [
            'order_uuid'     => $order->uuid,
            'status'         => $order->status,
            'total_amount'   => round((float) $order->total_amount, 2),
            'payment_status' => $order->payment?->status,
            'customer'       => [
                'first_name' => $order->first_name,
                'last_name'  => $order->last_name,
                'company'    => $order->company,
                'email'      => $order->email,
                'phone'      => $order->phone,
                'billing'    => $order->billingAddress(),
            ],
            'bookings'       => $order->bookings->map(fn ($b) => [
                'uuid'        => $b->uuid,
                'slot'        => $b->slot?->name,
                'status'      => $b->status,
                'guest_count' => $b->guest_count,
                'table'       => $b->table?->label,
                'items'       => $b->items->map(fn ($i) => [
                    'name'     => $i->menuItem?->name,
                    'quantity' => $i->quantity,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Ein Raum mit Tischplan, Tischen und Verfügbarkeit je Pause.
     *
     * Bei gesetzter Gruppengröße ($party) liefert das Ergebnis zusätzlich ein
     * "bookable"-Flag je Pause/Tisch – serverseitig nach derselben Regel wie
     * die Buchung (freie Plätze ODER leerer Tisch bei weicher Kapazität bis
     * $maxGroupEmptyTable). So muss das Frontend die Logik nicht nachbauen.
     */
    protected function formatRoomAvailability($room, Event $event, $slots, SeatAvailabilityService $seats, bool $softCapacity = false, ?int $maxGroupEmptyTable = null, ?int $party = null): ?array
    {
        $floorPlan = $room->floorPlan;

        if (! $floorPlan) {
            return null;
        }

        $tables = $floorPlan->tables;

        // Restplätze (und optional Buchbarkeit für eine Gruppe) je Pause und Tisch.
        $availability = [];
        $bookable     = [];
        foreach ($slots as $slot) {
            $bookedByTable = $seats->bookedSeatsByTable($floorPlan, $slot);
            foreach ($tables as $table) {
                $disabled  = $event->isTableDisabled($table->id);
                $booked    = (int) $bookedByTable->get($table->id, 0);
                $remaining = $disabled ? 0 : max(0, (int) $table->capacity - $booked);

                $availability[$slot->id][$table->id] = $remaining;

                if ($party !== null) {
                    $bookable[$slot->id][$table->id] = ! $disabled && (
                        $party <= $remaining
                        || ($softCapacity && $booked === 0 && ($maxGroupEmptyTable === null || $party <= $maxGroupEmptyTable))
                    );
                }
            }
        }

        $result = [
            'event_room_id' => $room->id,
            'floor_plan_id' => $room->floor_plan_id,
            'name'          => $floorPlan->name,
            'capacity'      => $room->capacity_override ?? (int) $tables->sum('capacity'),
            'floor_plan'    => [
                'background_url'      => $floorPlan->backgroundUrl(),
                'background_rotation' => $floorPlan->background_rotation,
                'aspect'             => $floorPlan->displayAspect(),
            ],
            'atmosphere_images' => $floorPlan->atmosphereImages(), // [{ id, url, thumbnail }]
            'tables' => $tables->map(fn ($t) => [
                'id'          => $t->id,
                'label'       => $t->label,
                'capacity'    => (int) $t->capacity,
                'shape'       => $t->shape,
                'x_pct'       => (float) $t->x_pct,
                'y_pct'       => (float) $t->y_pct,
                'w_pct'       => (float) $t->w_pct,
                'h_pct'       => (float) $t->h_pct,
                'is_disabled' => $event->isTableDisabled($t->id),
            ])->values()->all(),
            'availability' => $availability, // { slot_id: { table_id: remaining } }
        ];

        if ($party !== null) {
            $result['bookable'] = $bookable; // { slot_id: { table_id: bool } } für party=N
        }

        return $result;
    }

    /**
     * Termin scope-sicher per UUID oder numerischer ID auflösen.
     */
    protected function resolveEvent(string $key): ?Event
    {
        $query = Event::withoutGlobalScope('team');

        return ctype_digit($key)
            ? $query->find((int) $key)
            : $query->where('uuid', $key)->first();
    }

    /**
     * Verkaufsliste des Termins (explizit oder Team-Default), scope-sicher.
     */
    protected function resolveSalesList(Event $event): ?SalesList
    {
        return $event->sales_list_id
            ? SalesList::withoutGlobalScope('team')->find($event->sales_list_id)
            : SalesList::withoutGlobalScope('team')
                ->where('team_id', $event->team_id)
                ->where('is_default', true)
                ->first();
    }

    /**
     * Gast-sichtbare Artikel (freigegeben + verfügbar) der Verkaufsliste.
     */
    protected function visibleItems(?SalesList $salesList)
    {
        if (! $salesList) {
            return collect();
        }

        return $salesList->menuItems()
            ->withoutGlobalScope('team')
            ->where('approval_status', MenuItem::APPROVAL_APPROVED)
            ->where('available', true)
            ->with([
                'translations',
                'category.translations',
                'allergens.translations',
                'additives.translations',
                'holdingClass',
                'imageFile.variants',
            ])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Denormalisiertes Format eines Artikels.
     */
    protected function formatProduct(MenuItem $item, string $locale = Translation::DEFAULT_LOCALE): array
    {
        return [
            'id'               => $item->id,
            'name'             => $item->translate('name', $locale),
            'description'      => $item->translate('description', $locale),
            'portion_size'     => $item->portion_size,
            'price'            => (float) $item->price,
            'tax_rate'         => (float) $item->tax_rate,
            'category'         => $item->category?->translate('name', $locale),
            'category_id'      => $item->category_id,
            'holding_class'    => $item->holdingClass?->name,
            'holding_class_id' => $item->holding_class_id,
            'is_vegetarian'    => $item->is_vegetarian,
            'is_vegan'         => $item->is_vegan,
            'is_alcoholic'     => $item->is_alcoholic,
            'allergens'        => $item->allergens->pluck('code')->values(),
            'additives'        => $item->additives->pluck('code')->values(),
            'image_url'        => $item->image_context_file_id ? $item->imageUrl('medium_1_1') : null,
            'sort_order'       => $item->sort_order,
        ];
    }

    /**
     * Wendet die Filter auf die Query an.
     */
    protected function applyFilters($query, Request $request): void
    {
        // Team-Filter (inkl. Kind-Teams, Default: true wenn team_id gesetzt)
        if ($request->filled('team_id')) {
            $teamId          = (int) $request->team_id;
            $includeChildren = $request->has('include_child_teams')
                ? $request->boolean('include_child_teams')
                : true;

            if ($includeChildren && ($team = Team::find($teamId))) {
                $query->whereIn('team_id', $team->getAllTeamIdsIncludingChildren());
            } else {
                $query->where('team_id', $teamId);
            }
        }

        // Zeitraum: standardmäßig nur zukünftige (Datum >= heute).
        $upcoming = $request->has('upcoming') ? $request->boolean('upcoming') : true;
        if ($upcoming) {
            $query->whereDate('date', '>=', Carbon::today());
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        // Status: standardmäßig geschlossene ("closed") ausschließen; per status-Parameter überschreibbar.
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } elseif (! $request->boolean('include_closed')) {
            $query->where('status', '!=', EventStatus::Closed->value);
        }
    }

    /**
     * Denormalisiertes Format eines Termins inkl. Pausen und buchbarer Räume.
     */
    protected function formatEvent(Event $event): array
    {
        $rooms = $event->eventRooms->map(function ($room) {
            $capacity = $room->capacity_override
                ?? (int) ($room->floorPlan?->tables->sum('capacity') ?? 0);

            return [
                'event_room_id' => $room->id,
                'floor_plan_id' => $room->floor_plan_id,
                'name'          => $room->floorPlan?->name,
                'capacity'      => (int) $capacity,
                'tables_count'  => $room->floorPlan?->tables->count() ?? 0,
                'sort_order'    => $room->sort_order,
            ];
        })->values();

        return [
            'id'                => $event->id,
            'uuid'              => $event->uuid,
            'name'              => $event->name,
            'date'              => $event->date?->format('Y-m-d'),
            'status'            => $event->status->value,
            'is_closed'         => $event->status === EventStatus::Closed,
            'orderable'         => $event->isOrderable(),
            'order_deadline_at' => $event->order_deadline_at?->toIso8601String(),
            'team_id'           => $event->team_id,
            'venue'             => $event->venue?->name,
            'pauses_count'      => $event->slots_count,
            'rooms_count'       => $event->event_rooms_count,
            'total_capacity'    => (int) $rooms->sum('capacity'),
            'rooms'             => $rooms->all(),
            'created_at'        => $event->created_at?->toIso8601String(),
            'updated_at'        => $event->updated_at?->toIso8601String(),
        ];
    }
}
