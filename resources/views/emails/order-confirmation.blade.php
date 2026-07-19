@php
    $currency = strtoupper((string) config('reservation.currency', 'EUR'));
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . ($currency === 'EUR' ? '€' : $currency);
    $billing = $order->billingAddress();
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bestellbestätigung</title>
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
                            <div style="font-size:22px; font-weight:bold; margin-top:4px;">{{ $order->event?->name ?? 'PausePlus' }}</div>
                        </td>
                    </tr>

                    {{-- Begrüßung --}}
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <p style="margin:0 0 16px; font-size:15px;">
                                Hallo {{ $order->customerName() }},<br>
                                <strong>vielen Dank für Ihre Bestellung!</strong> Wir haben Ihre Vorbestellung erhalten – hier Ihre Zusammenfassung:
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
                                @if ($order->payment)
                                    <tr>
                                        <td style="padding:6px 0; color:#6b7280;">Zahlung</td>
                                        <td style="padding:6px 0;">{{ $order->payment->status === 'paid' ? 'bezahlt' : $order->payment->status }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    {{-- Je Pause eine Position-Gruppe --}}
                    @foreach ($order->bookings as $booking)
                        <tr>
                            <td style="padding:8px 32px;">
                                <div style="font-size:13px; font-weight:bold; color:#285567; border-bottom:2px solid #e5e7eb; padding-bottom:6px; margin-bottom:8px;">
                                    {{ $booking->slot?->displayLabel() ?? 'Pause' }}
                                    @if ($booking->table)<span style="color:#9ca3af; font-weight:normal;"> · Tisch {{ $booking->table->label }}</span>@endif
                                </div>
                                @if ($booking->items->isNotEmpty())
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                                        @foreach ($booking->items as $item)
                                            <tr>
                                                <td style="padding:6px 0; border-bottom:1px solid #f3f4f6;">{{ $item->quantity }}× {{ $item->menuItem?->name ?? 'Produkt' }}</td>
                                                <td style="padding:6px 0; border-bottom:1px solid #f3f4f6; text-align:right; white-space:nowrap;">{{ $fmt($item->quantity * $item->unit_price) }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    {{-- Gesamtsumme --}}
                    <tr>
                        <td style="padding:8px 32px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px; border-top:2px solid #285567;">
                                <tr>
                                    <td style="padding:12px 0 0; font-weight:bold;">Gesamt</td>
                                    <td style="padding:12px 0 0; font-weight:bold; text-align:right; white-space:nowrap;">{{ $fmt($order->total_amount) }}</td>
                                </tr>
                            </table>

                            @if ($order->company || $billing)
                                <p style="margin:20px 0 0; font-size:12px; color:#6b7280;">
                                    <strong style="color:#374151;">Rechnungsanschrift</strong><br>
                                    @if ($order->company){{ $order->company }}<br>@endif
                                    {{ $order->customerName() }}<br>
                                    @if ($billing)
                                        {{ $billing['street'] }}<br>
                                        {{ $billing['zip'] }} {{ $billing['city'] }}@if ($billing['country']) · {{ $billing['country'] }}@endif
                                    @endif
                                </p>
                            @endif
                        </td>
                    </tr>

                    {{-- Belege (PDF) --}}
                    @isset($receiptUrl)
                        <tr>
                            <td style="padding:0 32px 4px;">
                                <div style="border-top:1px solid #e5e7eb; padding-top:16px; font-size:13px; color:#374151;">
                                    <strong>Belege zum Download:</strong><br>
                                    <a href="{{ $receiptUrl }}" style="display:inline-block; margin-top:8px; color:#285567; font-weight:bold;">Bestellbestätigung (PDF)</a>
                                    @isset($bewirtungUrl)
                                        <span style="color:#9ca3af;"> · </span>
                                        <a href="{{ $bewirtungUrl }}" style="color:#285567; font-weight:bold;">Bewirtungsbeleg (PDF)</a>
                                    @endisset
                                </div>
                            </td>
                        </tr>
                    @endisset

                    {{-- Storno --}}
                    @isset($cancelUrl)
                        @if ($cancelUrl)
                            <tr>
                                <td style="padding:0 32px 20px;">
                                    <div style="border-top:1px solid #e5e7eb; padding-top:16px; font-size:13px; color:#6b7280;">
                                        Planänderung? Sie können Ihre Bestellung hier stornieren:<br>
                                        <a href="{{ $cancelUrl }}" style="display:inline-block; margin-top:8px; color:#285567; font-weight:bold;">Bestellung stornieren</a>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endisset

                    {{-- Fuß --}}
                    <tr>
                        <td style="padding:20px 32px; background-color:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; color:#9ca3af;">
                            Vielen Dank &amp; bis zur Veranstaltung! Diese E-Mail wurde automatisch erstellt – bei Fragen antworten Sie bitte direkt auf diese Nachricht.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
