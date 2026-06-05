<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Export extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $filterStatus = '';
    public string $format = 'csv'; // csv | json

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo   = now()->toDateString();
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
        $query = Booking::with(['table.floorPlan.venue', 'items', 'payment'])
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

    public function export(): StreamedResponse
    {
        $bookings = $this->buildQuery()->get();

        if ($this->format === 'json') {
            return $this->exportJson($bookings);
        }

        return $this->exportCsv($bookings);
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
