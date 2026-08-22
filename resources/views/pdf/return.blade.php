@php
    $money = fn (float $value): string => number_format($value, 0, ',', ' ') . ' FCFA';
    $refundLabel = \App\Models\SaleReturn::REFUND_METHODS[$saleReturn->refund_method] ?? $saleReturn->refund_method;
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
        <p class="muted">Ticket de retour</p>
    </div>

    <div class="divider"></div>

    <p class="row"><strong>Retour :</strong> {{ $saleReturn->reference }}</p>
    <p class="row"><strong>Vente :</strong> {{ $saleReturn->sale?->reference ?? '—' }}</p>
    <p class="row"><strong>Date :</strong> {{ $saleReturn->returned_at->format('d/m/Y H:i') }}</p>
    @if ($saleReturn->customer)
        <p class="row"><strong>Client :</strong> {{ $saleReturn->customer->name }}</p>
    @endif
    <p class="row"><strong>Caissier :</strong> {{ $saleReturn->user->name ?? '—' }}</p>

    <div class="divider"></div>

    @foreach ($saleReturn->items as $item)
        <div class="line">
            <span class="name">{{ $item->product->name }}</span>
            <span class="qty">{{ $item->quantity }} × {{ number_format((float) $item->unit_price, 0, ',', ' ') }}</span>
            <span class="amount">{{ $money((float) $item->unit_price * $item->quantity) }}</span>
        </div>
    @endforeach

    <div class="divider"></div>

    <div class="line total-row">
        <span class="name">TOTAL RETOURNÉ</span>
        <span class="qty"></span>
        <span class="amount">{{ $money((float) $saleReturn->total) }}</span>
    </div>

    <div class="divider"></div>

    @if ((float) $saleReturn->applied_to_balance > 0)
        <p class="row"><strong>Solde impayé réduit :</strong> {{ $money((float) $saleReturn->applied_to_balance) }}</p>
    @endif
    @if ((float) $saleReturn->refund_amount > 0)
        <p class="row"><strong>Remboursement :</strong> {{ $refundLabel }}</p>
        <p class="row"><strong>Montant :</strong> {{ $money((float) $saleReturn->refund_amount) }}</p>
    @elseif ($saleReturn->refund_method === \App\Models\SaleReturn::REFUND_AVOIR)
        <p class="row"><strong>Avoir :</strong> émis pour le client</p>
    @else
        <p class="row"><strong>Remboursement :</strong> aucun</p>
    @endif

    @if ($saleReturn->notes)
        <p class="muted">{{ $saleReturn->notes }}</p>
    @endif

    <div class="footer">
        <p>Stock réintégré — Merci de votre visite !</p>
    </div>
</body>
</html>
