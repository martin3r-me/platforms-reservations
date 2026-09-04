@php
    $currency = strtoupper((string) config('reservation.currency', 'EUR'));
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . ($currency === 'EUR' ? '€' : $currency);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stornobestätigung</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial,Helvetica,sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:560px; max-width:92%; background-color:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e5e7eb;">
                    {{-- Kopf. Bewusst dieselbe Farbe wie die Bestätigung: Der Gast
                         soll die Mail als dieselbe Absenderin erkennen, nicht als
                         Warnung eines fremden Systems. --}}
                    <tr>
                        <td style="padding:28px 32px; background-color:#285567; color:#ffffff;">
                            <div style="font-size:13px; letter-spacing:0.08em; text-transform:uppercase; opacity:0.85;">Stornobestätigung</div>
                            <div style="font-size:22px; font-weight:bold; margin-top:4px;">{{ $order->event?->name ?? 'PausePlus' }}</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <p style="margin:0 0 16px; font-size:15px;">
                                Hallo {{ $order->customerName() }},<br>
                                <strong>Ihre Vorbestellung wurde storniert.</strong>
                                @if ($grund)
                                    {{ $grund }}
                                @endif
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; margin-bottom:8px;">
                                @if ($order->bookings->first()?->date)
                                    <tr>
                                        <td style="padding:6px 0; color:#6b7280; width:140px;">Datum</td>
                                        <td style="padding:6px 0;">{{ $order->bookings->first()->date->format('d.m.Y') }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:6px 0; color:#6b7280;">Bestellnr.</td>
                                    <td style="padding:6px 0; font-family:monospace; font-size:12px;">{{ $order->uuid }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Was storniert wurde. Ohne diese Liste müsste der Gast in der
                         alten Mail nachsehen, worum es überhaupt ging. --}}
                    @foreach ($order->bookings as $booking)
                        <tr>
                            <td style="padding:8px 32px;">
                                <div style="font-size:13px; font-weight:bold; color:#285567; border-bottom:2px solid #e5e7eb; padding-bottom:6px; margin-bottom:8px;">
                                    {{ $booking->slot?->displayLabel() ?? 'Pause' }}
                                </div>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                                    @foreach ($booking->items as $item)
                                        <tr>
                                            <td style="padding:4px 0; color:#6b7280;">
                                                {{ $item->quantity }} × {{ $item->menuItem?->name ?? 'Artikel' }}
                                            </td>
                                            <td style="padding:4px 0; text-align:right; white-space:nowrap;">
                                                {{ $fmt($item->quantity * $item->unit_price) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endforeach

                    <tr>
                        <td style="padding:16px 32px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px; border-top:2px solid #e5e7eb; padding-top:8px;">
                                <tr>
                                    <td style="padding:10px 0; font-weight:bold;">Storniert</td>
                                    <td style="padding:10px 0; text-align:right; font-weight:bold;">{{ $fmt($order->total_amount) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Über Geld nur reden, wenn welches zurückgeht. Eine Zeile über
                         eine Gutschrift, auf die der Gast dann wartet, wäre schlimmer
                         als gar keine. --}}
                    <tr>
                        <td style="padding:8px 32px 28px;">
                            @if ($erstattet)
                                <p style="margin:0; font-size:14px; color:#4b5563;">
                                    Der Betrag wird über das Zahlungsmittel zurückerstattet, mit dem Sie bezahlt haben.
                                    Bis die Gutschrift auf Ihrem Konto erscheint, können je nach Bank einige Werktage vergehen.
                                </p>
                            @else
                                <p style="margin:0; font-size:14px; color:#4b5563;">
                                    Es wurde nichts abgebucht – eine Rückerstattung ist deshalb nicht nötig.
                                </p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 28px; font-size:13px; color:#6b7280;">
                            Bei Fragen antworten Sie einfach auf diese E-Mail.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
