@php
    $currency = strtoupper((string) config('reservation.currency', 'EUR'));
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . ($currency === 'EUR' ? '€' : $currency);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buchungsbestätigung</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial,Helvetica,sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:560px; max-width:92%; background-color:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e5e7eb;">
                    {{-- Kopf --}}
                    <tr>
                        <td style="padding:28px 32px; background-color:#285567; color:#ffffff;">
                            <div style="font-size:13px; letter-spacing:0.08em; text-transform:uppercase; opacity:0.85;">Bestellbestätigung</div>
                            <div style="font-size:22px; font-weight:bold; margin-top:4px;">{{ $booking->event?->name ?? 'PausePlus' }}</div>
                        </td>
                    </tr>

                    {{-- Begrüßung + Eckdaten --}}
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin:0 0 16px; font-size:15px;">
                                Hallo {{ $booking->guest_name }},<br>
                                vielen Dank für Ihre Vorbestellung. Hier Ihre Zusammenfassung:
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; margin-bottom:20px;">
                                <tr>
                                    <td style="padding:6px 0; color:#6b7280; width:140px;">Datum</td>
                                    <td style="padding:6px 0;">{{ $booking->date->format('d.m.Y') }}</td>
                                </tr>
                                @if ($booking->slot)
                                    <tr>
                                        <td style="padding:6px 0; color:#6b7280;">Pause</td>
                                        <td style="padding:6px 0;">{{ $booking->slot->name }} · {{ substr($booking->slot->time_start, 0, 5) }} Uhr</td>
                                    </tr>
                                @endif
                                @if ($booking->table)
                                    <tr>
                                        <td style="padding:6px 0; color:#6b7280;">Tisch</td>
                                        <td style="padding:6px 0;">{{ $booking->table->label }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:6px 0; color:#6b7280;">Personen</td>
                                    <td style="padding:6px 0;">{{ $booking->guest_count }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0; color:#6b7280;">Buchungsnr.</td>
                                    <td style="padding:6px 0; font-family:monospace; font-size:12px;">{{ $booking->uuid }}</td>
                                </tr>
                            </table>

                            {{-- Positionen --}}
                            @if ($booking->items->isNotEmpty())
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; border-top:1px solid #e5e7eb;">
                                    @foreach ($booking->items as $item)
                                        <tr>
                                            <td style="padding:8px 0; border-bottom:1px solid #f3f4f6;">
                                                {{ $item->quantity }}× {{ $item->menuItem?->name ?? 'Produkt' }}
                                            </td>
                                            <td style="padding:8px 0; border-bottom:1px solid #f3f4f6; text-align:right; white-space:nowrap;">
                                                {{ $fmt($item->quantity * $item->unit_price) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td style="padding:12px 0 0; font-weight:bold;">Gesamt</td>
                                        <td style="padding:12px 0 0; font-weight:bold; text-align:right; white-space:nowrap;">{{ $fmt($booking->total_amount) }}</td>
                                    </tr>
                                </table>
                            @endif

                            @if ($booking->notes)
                                <p style="margin:20px 0 0; font-size:13px; color:#6b7280;">
                                    <strong style="color:#374151;">Ihre Anmerkung:</strong><br>{{ $booking->notes }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    {{-- Fuß --}}
                    <tr>
                        <td style="padding:20px 32px; background-color:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; color:#9ca3af;">
                            Diese E-Mail wurde automatisch erstellt. Bei Fragen antworten Sie bitte direkt auf diese Nachricht.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
