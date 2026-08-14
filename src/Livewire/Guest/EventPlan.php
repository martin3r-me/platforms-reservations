<?php

namespace Platform\Reservation\Livewire\Guest;

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\EventShareAccess;
use Platform\Reservation\Services\FunctionSheetService;
use Platform\Reservation\Services\KitchenPrepService;

/**
 * Küche und Laufzettel einer Veranstaltung – ohne Konto, per Link mit PIN.
 *
 * Für Veranstaltungsleiter, die vor Ort sind, wenn niemand aus dem Catering
 * da ist. Bewusst NUR diese beiden Ansichten: Die Buchungsliste enthält Namen
 * und E-Mail-Adressen, und ein Link ist ein Schlüssel – weitergeleitete Mails
 * verteilen ihn weiter, als gedacht.
 *
 * Drei Schranken, die zusammenwirken:
 *   1. Der Token im Link. Neu gewürfelt = alle alten Links tot.
 *   2. Die PIN. Ein durchgereichter Link allein genügt nicht.
 *   3. Der Ablauf am Tag nach der Veranstaltung.
 *
 * Rein lesend: Die Komponente hat keine Methode, die etwas verändert.
 */
class EventPlan extends Component
{
    /** Beide aus der URL – dürfen clientseitig nicht überschrieben werden. */
    #[Locked]
    public string $uuid = '';

    #[Locked]
    public string $token = '';

    public string $pin = '';

    public string $pinError = '';

    /** dashboard | function – welcher Reiter offen ist. */
    public string $tab = 'kitchen';

    /** Wie viele Fehlversuche pro Minute und IP erlaubt sind. */
    protected const MAX_VERSUCHE = 5;

    public function mount(string $uuid, string $token): void
    {
        $this->uuid  = $uuid;
        $this->token = $token;

        // Ungültiger, zurückgezogener oder abgelaufener Link: nicht
        // unterscheiden, sonst verrät die Fehlermeldung, welcher Fall vorliegt.
        if (! $this->event) {
            abort(404);
        }
    }

    /**
     * Bewusst NICHT über Requests hinweg gecacht: Token und Ablauf müssen bei
     * jedem Aufruf neu geprüft werden, sonst bliebe ein zurückgezogener Zugang
     * bis zum Cache-Ablauf offen.
     */
    #[Computed]
    public function event(): ?Event
    {
        if ($this->token === '') {
            return null;
        }

        $event = Event::withoutGlobalScope('team')
            ->where('uuid', $this->uuid)
            ->where('share_token', $this->token)
            ->with(['slots', 'venue'])
            ->first();

        return $event?->shareIsActive() ? $event : null;
    }

    /** Ist die PIN in dieser Sitzung schon bestätigt worden? */
    #[Computed]
    public function unlocked(): bool
    {
        return session()->get($this->sessionKey()) === $this->token;
    }

    public function submitPin(): void
    {
        $event = $this->event;

        if (! $event) {
            abort(404);
        }

        // Sechs Ziffern wären ohne Drosselung in Minuten durchprobiert.
        $key = 'event-plan:' . $event->id . ':' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_VERSUCHE)) {
            $this->pinError = 'Zu viele Fehlversuche. Bitte '
                . RateLimiter::availableIn($key) . ' Sekunden warten.';

            return;
        }

        $treffer = $event->sharePinMatches(trim($this->pin));

        $this->protokollieren($event, $treffer);

        if (! $treffer) {
            RateLimiter::hit($key, 60);
            $this->pinError = 'PIN stimmt nicht.';
            $this->pin      = '';

            return;
        }

        RateLimiter::clear($key);
        session()->put($this->sessionKey(), $this->token);

        $this->pin      = '';
        $this->pinError = '';
        unset($this->unlocked);
    }

    /**
     * Nur PIN-Eingaben protokollieren, nicht jeden Seitenaufruf – sonst
     * ersäuft das Signal (gehäufte Fehlversuche) in Rauschen.
     */
    protected function protokollieren(Event $event, bool $erfolg): void
    {
        EventShareAccess::create([
            'event_id'   => $event->id,
            'ip'         => EventShareAccess::truncateIp(request()->ip()),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'successful' => $erfolg,
        ]);
    }

    protected function sessionKey(): string
    {
        return 'reservation_event_plan_' . $this->uuid;
    }

    #[Computed]
    public function prepBySlot(): \Illuminate\Support\Collection
    {
        return app(KitchenPrepService::class)->prepBySlot($this->event);
    }

    #[Computed]
    public function slotStats(): \Illuminate\Support\Collection
    {
        return app(KitchenPrepService::class)->slotStats($this->event);
    }

    #[Computed]
    public function sheet(): array
    {
        return app(FunctionSheetService::class)->build($this->event);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['kitchen', 'function'], true) ? $tab : 'kitchen';
    }

    public function render()
    {
        return view('reservation::livewire.guest.event-plan')
            ->layout('platform::layouts.guest');
    }
}
