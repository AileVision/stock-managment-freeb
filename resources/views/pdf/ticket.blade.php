@php
    $money = fn (float $value): string => number_format($value, 0, ',', ' ') . ' FCFA';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #111;
            width: 226pt;
        }
        .center { text-align: center; }
        .shop-name { font-size: 14px; font-weight: bold; }
        .muted { color: #555; }
        .divider { border-top: 1px dashed #999; margin: 6px 0; }
        .row { display: block; margin-bottom: 2px; }
        .line { width: 100%; }
        .line .name { display: inline-block; width: 55%; vertical-align: top; }
        .line .qty { display: inline-block; width: 20%; text-align: center; }
        .line .amount { display: inline-block; width: 25%; text-align: right; }
        .total-row { font-weight: bold; font-size: 13px; }
        .footer { margin-top: 8px; text-align: center; font-size: 10px; }
    </style>
</head>
<body>
    <div class="center">
        <p class="shop-name">{{ config('app.name') }}</p>
        <p class="muted">Ticket de caisse</p>
    </div>

    <div class="divider"></div>

    <p class="row"><strong>Vente :</strong> {{ $sale->reference }}</p>
    <p class="row"><strong>Date :</strong> {{ $sale->created_at->format('d/m/Y H:i') }}</p>
    <p class="row"><strong>Caissier :</strong> {{ $sale->user->name ?? '—' }}</p>

    <div class="divider"></div>

    @foreach ($sale->items as $item)
        <div class="line">
            <span class="name">{{ $item->product->name }}</span>
            <span class="qty">{{ $item->quantity }} × {{ number_format((float) $item->unit_price, 0, ',', ' ') }}</span>
            <span class="amount">{{ $money((float) $item->unit_price * $item->quantity) }}</span>
        </div>
    @endforeach

    @php
        $discountPercent = (float) $sale->discount_percent;
        $discountAmount = $discountPercent > 0 ? (float) $sale->total * $discountPercent / (100 - $discountPercent) : 0.0;
        $taxPercent = (float) $sale->tax_percent;
        $taxAmount = $taxPercent > 0 ? $sale->taxAmount() : 0.0;
    @endphp

    @if ($discountPercent > 0)
        <div class="line">
            <span class="name">Remise ({{ number_format($discountPercent, 0, ',', ' ') }}%)</span>
            <span class="qty"></span>
            <span class="amount">−{{ $money($discountAmount) }}</span>
        </div>
    @endif

    @if ($taxPercent > 0)
        <div class="line">
            <span class="name">TVA ({{ number_format($taxPercent, 0, ',', ' ') }}%)</span>
            <span class="qty"></span>
            <span class="amount">{{ $money($taxAmount) }}</span>
        </div>
    @endif

    <div class="divider"></div>

    <div class="line total-row">
        <span class="name">TOTAL</span>
        <span class="qty"></span>
        <span class="amount">{{ $money((float) $sale->total) }}</span>
    </div>

    <div class="divider"></div>

    <p class="row"><strong>Paiement :</strong> {{ \App\Models\Sale::PAYMENT_METHODS[$sale->payment_method] ?? $sale->payment_method }}</p>

    @if ($sale->mobile_phone)
        <p class="row"><strong>Mobile :</strong> {{ $sale->mobile_phone }}</p>
        @if ($sale->mobile_reference)
            <p class="row muted">Réf. {{ $sale->mobile_reference }}</p>
        @endif
    @endif

    @if ($sale->notes)
        <p class="muted">{{ $sale->notes }}</p>
    @endif

    <div class="footer">
        <p>Merci de votre visite !</p>
    </div>
</body>
</html>
