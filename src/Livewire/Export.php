<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Carbon\CarbonImmutable;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Support\DatevBuchungsstapel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Platform\Reservation\Support\Zeitraum;

class Export extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $filterStatus = '';
    public string $format = 'csv'; // csv | json | datev

    /** Welcher Schnellzeitraum gerade aktiv ist ('' = von Hand gewählt). */
    public string $activePreset = 'month';

    /**
     * Ausgewählte Felder für CSV und JSON.
     *
     * Standardmäßig alle – wer nichts einstellt, bekommt alles. Abwählen ist
     * für die Fälle da, in denen eine Datei weitergereicht wird und Name,
     * E-Mail und Telefon nichts darin verloren haben.
     *
     * @var array<int, string>
     */
    public array $fields = [];

    public string $exportError = '';

    public function mount(): void
    {
        $this->setPreset('month');
        $this->fields = array_keys(self::fields());
    }

    /**
     * Die ausgebbaren Felder in ihrer Reihenfolge.
     *
     * Eine Liste für beide Formate: Sonst hätte CSV bald ein Feld, das JSON
     * nicht kennt, und niemand merkt es.
     *
     * @return array<string, string>
     */
    public static function fields(): array
    {
        return [
            'id'       => 'Buchungs-ID',
            'date'     => 'Datum',
            'time'     => 'Uhrzeit',
            'table'    => 'Tisch',
            'venue'    => 'Venue',
            'event'    => 'Termin',
            'guest'    => 'Gast',
            'email'    => 'E-Mail',
            'phone'    => 'Telefon',
            'guests'   => 'Personen',
            'status'   => 'Status',
            'amount'   => 'Betrag',
            'payment'  => 'Zahlungsart',
            'mollie'   => 'Mollie-ID',
            'tax'      => 'Steuersatz',
            'created'  => 'Erstellt',
        ];
    }

    /** Ausgewählte Felder in der festen Reihenfolge, nie leer. */
    protected function gewaehlteFelder(): array
    {
        $gueltig = array_values(array_intersect(array_keys(self::fields()), $this->fields));

        // Eine Datei ohne Spalten ist keine Datei.
        return $gueltig ?: array_keys(self::fields());
    }

    /** Feld an- oder abwählen. */
    public function toggleField(string $key): void
    {
        if (! array_key_exists($key, self::fields())) {
            return;
        }

        $this->fields = in_array($key, $this->fields, true)
            ? array_values(array_diff($this->fields, [$key]))
            : [...$this->fields, $key];
    }

    public function allFields(): void
    {
        $this->fields = array_keys(self::fields());
    }

    /**
     * Schnellzeiträume.
     *
     * "Ab heute" ist dabei der eine, den ein Kalender nicht hergibt: Buchungen
     * liegen in der ZUKUNFT, und wer wissen will, was noch ansteht, will von
     * heute bis Jahresende – nicht den abgelaufenen Monat.
     *
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return Zeitraum::beschriftungen() + [
            // Nicht "Ab heute": Das sagt, wo es anfängt, aber nicht, wo es
            // aufhört. Gemeint ist von heute bis Silvester.
            'ahead' => 'Rest des Jahres',
            'all'   => 'Alles',
        ];
    }

    public function setPreset(string $preset): void
    {
        $this->activePreset = $preset;
        $this->exportError  = '';

        // Die gemeinsamen Zeiträume aus Support\Zeitraum; hier stehen nur die
        // beiden, die es nur im Export gibt.
        [$this->dateFrom, $this->dateTo] = Zeitraum::spanne($preset) ?? match ($preset) {
            'ahead'      => [now()->toDateString(), now()->endOfYear()->toDateString()],
            // Von der ersten bis zur letzten Buchung – auch in die Zukunft, denn
            // Buchungen liegen dort. Ohne Buchungen das laufende Jahr.
            //
            // Durch Carbon geschickt, weil die Datenbank je nach Spaltentyp
            // "2026-06-15" oder "2026-06-15 00:00:00" liefert – das Datumsfeld
            // versteht nur das Erste.
            'all'        => [
                self::alsDatum(Booking::where('team_id', $this->getTeamId())->min('date'), now()->startOfYear()),
                self::alsDatum(Booking::where('team_id', $this->getTeamId())->max('date'), now()->endOfYear()),
            ],
            default      => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    /** Wert aus der Datenbank als reines Datum, sonst der Ersatzwert. */
    protected static function alsDatum($wert, $ersatz): string
    {
        return $wert ? CarbonImmutable::parse($wert)->toDateString() : $ersatz->toDateString();
    }

    /** Von Hand gewählte Daten heben die Schnellwahl auf. */
    public function updatedDateFrom(): void
    {
        $this->activePreset = '';
    }

    public function updatedDateTo(): void
    {
        $this->activePreset = '';
    }

    public function updatedFormat(): void
    {
        $this->exportError = '';
    }

    protected function getTeamId(): ?int
    {
        $user = Auth::user();
        return $user?->current_team_id;
    }

    #[Computed]
    public function previewCount(): int
    {
        return $this->buildQuery()->count();
    }

    protected function buildQuery()
    {
        $query = Booking::with(['table.floorPlan', 'pickupStation', 'items', 'order.payment', 'event.venue'])
            ->where('team_id', $this->getTeamId());

        if ($this->dateFrom) {
            $query->whereDate('date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('date', '<=', $this->dateTo);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return $query->orderBy('date')->orderBy('time_start');
    }

    /** Einstellungen des Teams – für den DATEV-Export. */
    #[Computed]
    public function settings(): CheckoutSetting
    {
        return CheckoutSetting::forTeam((int) $this->getTeamId());
    }

    /**
     * Vorschau auf den Buchungsstapel.
     *
     * Nur für DATEV, und dort aus einem konkreten Grund: Diese Datei kann
     * niemand lesen – zwei Kopfzeilen, Semikolons, Kontonummern statt Namen.
     * Eine falsche Kontonummer erzeugt keinen Fehler, sondern einen plausibel
     * aussehenden, aber falschen Stapel, der erst in der Kanzlei auffällt.
     *
     * Gerechnet wird mit derselben Methode wie beim Export. Eine eigene
     * Vorschau-Rechnung wäre die zweite Fassung derselben Sache – und damit
     * eine Vorschau, die irgendwann etwas anderes zeigt als die Datei.
     *
     * @return array{saetze: array<int, array<string, string>>, summe: float, buchungen: int}
     */
    #[Computed]
    public function datevPreview(): array
    {
        if ($this->format !== 'datev' || ! $this->settings->datevReady()) {
            return ['saetze' => [], 'summe' => 0.0, 'buchungen' => 0];
        }

        $bookings = $this->buildQuery()->get();
        $saetze   = DatevBuchungsstapel::saetze($bookings, $this->settings);

        return [
            'saetze'    => $saetze,
            'summe'     => collect($saetze)->sum(fn ($s) => (float) str_replace(',', '.', $s['umsatz'])),
            'buchungen' => $bookings->filter(
                fn ($b) => in_array($b->status, $this->settings->umsatzStatus(), true)
            )->count(),
        ];
    }

    public function export(): ?StreamedResponse
    {
        $this->exportError = '';

        if ($this->format === 'datev') {
            $fehlt = $this->settings->datevMissing();

            if ($fehlt !== []) {
                // Kein halbfertiger Stapel: Der würde in der Kanzlei landen und
                // dort Arbeit machen. Lieber sagen, was fehlt.
                $this->exportError = 'Für den DATEV-Export fehlen noch Angaben in den Einstellungen: '
                    . implode(', ', $fehlt) . '.';

                return null;
            }
        }

        $bookings = $this->buildQuery()->get();

        return match ($this->format) {
            'json'  => $this->exportJson($bookings),
            'datev' => $this->exportDatev($bookings),
            default => $this->exportCsv($bookings),
        };
    }

    /**
     * Buchungsstapel im DATEV-Format.
     *
     * Der Statusfilter greift hier bewusst NICHT: In die Buchhaltung gehören
     * bestätigte und abgeschlossene Umsätze, nie ausstehende oder stornierte.
     * Wer "Alle Status" gewählt hat, bekommt trotzdem einen richtigen Stapel.
     */
    protected function exportDatev($bookings): StreamedResponse
    {
        $einst = $this->settings;

        $inhalt = DatevBuchungsstapel::bauen(
            $bookings,
            $einst,
            CarbonImmutable::parse($this->dateFrom ?: now()->startOfMonth()),
            CarbonImmutable::parse($this->dateTo ?: now()),
        );

        // Dateiname nach DATEV-Gewohnheit: EXTF_ und der Zeitraum.
        $filename = 'EXTF_Buchungsstapel_'
            . CarbonImmutable::parse($this->dateFrom)->format('Ymd') . '-'
            . CarbonImmutable::parse($this->dateTo)->format('Ymd') . '.csv';

        return response()->streamDownload(
            fn () => print $inhalt,
            $filename,
            ['Content-Type' => 'text/csv; charset=Windows-1252'],
        );
    }

    protected function exportCsv($bookings): StreamedResponse
    {
        $filename = 'reservierungen_' . now()->format('Y-m-d') . '.csv';
        $felder   = $this->gewaehlteFelder();
        $namen    = self::fields();

        return response()->streamDownload(function () use ($bookings, $felder, $namen) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM für Excel
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map(fn ($k) => $namen[$k], $felder), ';');

            foreach ($bookings as $booking) {
                fputcsv($handle, array_map(fn ($k) => self::csvWert($k, $booking), $felder), ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Ein Feld als Text für die CSV – deutsche Schreibweise. */
    protected static function csvWert(string $key, $b): string
    {
        $summe = fn () => $b->items->sum(fn ($i) => $i->unit_price * $i->quantity);

        return (string) match ($key) {
            'id'      => $b->id,
            'date'    => $b->date?->format('d.m.Y'),
            'time'    => $b->time_start,
            'table'   => $b->zielortLabel(),
            // Venue aus dem TERMIN, nicht über den Tisch: Der Umweg
            // table->floorPlan->venue ließ die Spalte leer, sobald der Tisch
            // gelöscht war - und traf damit ausgerechnet die Zeilen, bei denen
            // ohnehin schon etwas fehlte.
            'venue'   => $b->event?->venue?->name,
            'event'   => $b->event?->name,
            'guest'   => $b->guest_name,
            'email'   => $b->guest_email,
            'phone'   => $b->guest_phone,
            'guests'  => $b->guest_count,
            'status'  => $b->status,
            'amount'  => number_format($summe(), 2, ',', '.'),
            'payment' => $b->payment?->method ?? $b->payment_method,
            'mollie'  => $b->mollie_payment_id,
            'tax'     => $b->items->first()?->tax_rate ?? '',
            'created' => $b->created_at?->format('d.m.Y H:i'),
            default   => '',
        };
    }

    /** Ein Feld für die JSON – maschinenlesbar, ISO-Datum, Zahlen als Zahlen. */
    protected static function jsonWert(string $key, $b)
    {
        return match ($key) {
            'id'      => $b->id,
            'date'    => $b->date?->toDateString(),
            'time'    => ['start' => $b->time_start, 'end' => $b->time_end],
            'table'   => $b->zielortLabel(),
            // Venue aus dem TERMIN, nicht über den Tisch: Der Umweg
            // table->floorPlan->venue ließ die Spalte leer, sobald der Tisch
            // gelöscht war - und traf damit ausgerechnet die Zeilen, bei denen
            // ohnehin schon etwas fehlte.
            'venue'   => $b->event?->venue?->name,
            'event'   => $b->event?->name,
            'guest'   => $b->guest_name,
            'email'   => $b->guest_email,
            'phone'   => $b->guest_phone,
            'guests'  => (int) $b->guest_count,
            'status'  => $b->status,
            'amount'  => round((float) $b->items->sum(fn ($i) => $i->unit_price * $i->quantity), 2),
            'payment' => $b->payment?->method ?? $b->payment_method,
            'mollie'  => $b->mollie_payment_id,
            'tax'     => $b->items->first()?->tax_rate,
            'created' => $b->created_at?->toIso8601String(),
            default   => null,
        };
    }

    protected function exportJson($bookings): StreamedResponse
    {
        $filename = 'reservierungen_' . now()->format('Y-m-d') . '.json';
        $felder   = $this->gewaehlteFelder();

        $data = $bookings->map(function ($b) use ($felder) {
            $zeile = [];

            foreach ($felder as $key) {
                $zeile[$key] = self::jsonWert($key, $b);
            }

            // Die Positionen hängen nicht an der Feldauswahl: Sie sind der
            // Inhalt der Bestellung, keine Spalte daneben.
            $zeile['items'] = $b->items->map(fn ($i) => [
                'name'       => $i->name ?: $i->menuItem?->name,
                'quantity'   => (int) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'tax_rate'   => (float) $i->tax_rate,
            ]);

            return $zeile;
        });

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            $filename,
            ['Content-Type' => 'application/json']
        );
    }

    public function render()
    {
        return view('reservation::livewire.export')
            ->layout('platform::layouts.app');
    }
}
