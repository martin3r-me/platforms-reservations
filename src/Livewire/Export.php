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

class Export extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $filterStatus = '';
    public string $format = 'csv'; // csv | json | datev

    /** Welcher Schnellzeitraum gerade aktiv ist ('' = von Hand gewählt). */
    public string $activePreset = 'month';

    public string $exportError = '';

    public function mount(): void
    {
        $this->setPreset('month');
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
        return [
            'last_week'  => 'Letzte Woche',
            'month'      => 'Dieser Monat',
            'last_month' => 'Letzter Monat',
            'quarter'    => 'Dieses Quartal',
            'year'       => 'Dieses Jahr',
            'last_year'  => 'Letztes Jahr',
            // Nicht "Ab heute": Das sagt, wo es anfängt, aber nicht, wo es
            // aufhört. Gemeint ist von heute bis Silvester.
            'ahead'      => 'Rest des Jahres',
            'all'        => 'Alles',
        ];
    }

    public function setPreset(string $preset): void
    {
        $this->activePreset = $preset;
        $this->exportError  = '';

        [$this->dateFrom, $this->dateTo] = match ($preset) {
            'last_week'  => [
                now()->subWeek()->startOfWeek()->toDateString(),
                now()->subWeek()->endOfWeek()->toDateString(),
            ],
            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'quarter'    => [now()->startOfQuarter()->toDateString(), now()->endOfQuarter()->toDateString()],
            'year'       => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'last_year'  => [
                now()->subYear()->startOfYear()->toDateString(),
                now()->subYear()->endOfYear()->toDateString(),
            ],
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
        $query = Booking::with(['table.floorPlan.venue', 'items', 'order.payment', 'event'])
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
                fn ($b) => in_array($b->status, DatevBuchungsstapel::UMSATZ_STATUS, true)
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

        return response()->streamDownload(function () use ($bookings) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM für Excel
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Buchungs-ID', 'Datum', 'Uhrzeit', 'Tisch', 'Venue',
                'Gast', 'E-Mail', 'Telefon', 'Personen',
                'Status', 'Betrag', 'Zahlungsart', 'Mollie-ID',
                'Steuersatz', 'Erstellt',
            ], ';');

            foreach ($bookings as $booking) {
                $total = $booking->items->sum(fn ($i) => $i->unit_price * $i->quantity);
                fputcsv($handle, [
                    $booking->id,
                    $booking->date->format('d.m.Y'),
                    $booking->time_start,
                    $booking->table?->label,
                    $booking->table?->floorPlan?->venue?->name,
                    $booking->guest_name,
                    $booking->guest_email,
                    $booking->guest_phone,
                    $booking->guest_count,
                    $booking->status,
                    number_format($total, 2, ',', '.'),
                    $booking->payment?->method,
                    $booking->mollie_payment_id,
                    $booking->items->first()?->tax_rate ?? '',
                    $booking->created_at->format('d.m.Y H:i'),
                ], ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function exportJson($bookings): StreamedResponse
    {
        $filename = 'reservierungen_' . now()->format('Y-m-d') . '.json';
        $data = $bookings->map(fn ($b) => [
            'id'              => $b->id,
            'uuid'            => $b->uuid,
            'date'            => $b->date->toDateString(),
            'time_start'      => $b->time_start,
            'time_end'        => $b->time_end,
            'table'           => $b->table?->label,
            'venue'           => $b->table?->floorPlan?->venue?->name,
            'guest_name'      => $b->guest_name,
            'guest_email'     => $b->guest_email,
            'guest_phone'     => $b->guest_phone,
            'guest_count'     => $b->guest_count,
            'status'          => $b->status,
            'total_amount'    => $b->items->sum(fn ($i) => $i->unit_price * $i->quantity),
            'payment_method'  => $b->payment?->method,
            'mollie_id'       => $b->mollie_payment_id,
            'items'           => $b->items->map(fn ($i) => [
                'name'       => $i->menuItem?->name,
                'quantity'   => $i->quantity,
                'unit_price' => $i->unit_price,
                'tax_rate'   => $i->tax_rate,
            ]),
        ]);

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
