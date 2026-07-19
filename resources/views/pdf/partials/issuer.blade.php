@if ($issuer['name'])
    <div style="font-size: 10px; color: #6b7280; margin-bottom: 10px; line-height: 1.5;">
        <strong style="color: #374151;">{{ $issuer['name'] }}</strong>@if ($issuer['street']) · {{ $issuer['street'] }}@endif@if ($issuer['zip'] || $issuer['city']) · {{ $issuer['zip'] }} {{ $issuer['city'] }}@endif@if ($issuer['country']) · {{ $issuer['country'] }}@endif
        @if ($issuer['register_court'] || $issuer['register_number'] || $issuer['managing_directors'])
            <br>@if ($issuer['register_court']){{ $issuer['register_court'] }}@endif@if ($issuer['register_number']) HRB {{ $issuer['register_number'] }}@endif@if ($issuer['managing_directors']) · Vertreten durch: {{ $issuer['managing_directors'] }}@endif
        @endif
        @if ($issuer['vat_id'] || $issuer['tax_number'])
            <br>@if ($issuer['vat_id'])USt-IdNr: {{ $issuer['vat_id'] }}@endif@if ($issuer['tax_number']) · Steuernr.: {{ $issuer['tax_number'] }}@endif
        @endif
        @if ($issuer['phone'] || $issuer['fax'] || $issuer['email'] || $issuer['website'])
            <br>@if ($issuer['phone'])Tel: {{ $issuer['phone'] }}@endif@if ($issuer['fax']) · Fax: {{ $issuer['fax'] }}@endif@if ($issuer['email']) · {{ $issuer['email'] }}@endif@if ($issuer['website']) · {{ $issuer['website'] }}@endif
        @endif
    </div>
@endif
