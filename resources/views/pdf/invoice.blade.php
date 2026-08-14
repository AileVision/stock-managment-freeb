@php
    $money = fn (float $value): string => number_format($value, 0, ',', ' ') . ' FCFA';
    $sale = $invoice->sale;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #111;
        }
        table { width: 100%; border-collapse: collapse; }
        .header { width: 100%; margin-bottom: 24px; }
        .shop-name { font-size: 20px; font-weight: bold; }
        .muted { color: #555; }
        .right { text-align: right; }
        .doc-title { font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .block { margin-bottom: 18px; }
        .label { font-size: 10px; text-transform: uppercase; color: #777; margin-bottom: 2px; }
        .items th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 1px solid #ccc;
            padding: 6px 4px;
        }
        .items td {
            padding: 7px 4px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .items .num { text-align: right; white-space: nowrap; }
        .total-row td { font-weight: bold; border-top: 2px solid #111; font-size: 14px; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #777; }
        .notes { margin-top: 24px; font-size: 11px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <p class="shop-name">{{ config('app.name') }}</p>
                <p class="muted">Gestion de stock & point de vente</p>
            </td>
            <td class="right">
                <p class="doc-title">Facture</p>
                <p class="muted">N° {{ $invoice->reference }}</p>
                <p class="muted">Date d'émission : {{ $invoice->issued_at->format('d/m/Y') }}</p>
            </td>
        </tr>
    </table>

    <table class="block">
        <tr>
            <td>
                <p class="label">Facturé à</p>
                <p><strong>{{ $invoice->customer_name }}</strong></p>
                @if ($invoice->customer_email)
                    <p class="muted">{{ $invoice->customer_email }}</p>
                @endif
                @if ($invoice->customer_address)
                    <p class="muted">{!! nl2br(e($invoice->customer_address)) !!}</p>
                @endif
            </td>
            <td class="right">
                <p class="label">Vente associée</p>
                <p><strong>{{ $sale?->reference ?? '—' }}</strong></p>
                <p class="muted">Date de vente : {{ $sale?->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 55%;">Désignation</th>
                <th style="width: 10%;" class="num">Qté</th>
                <th style="width: 17%;" class="num">Prix unitaire</th>
                <th style="width: 18%;" class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @if ($sale)
                @foreach ($sale->items as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td class="num">{{ number_format($item->quantity, 0, ',', ' ') }}</td>
                        <td class="num">{{ $money((float) $item->unit_price) }}</td>
                        <td class="num">{{ $money((float) $item->unit_price * $item->quantity) }}</td>
                    </tr>
                @endforeach
            @endif
            @php
                $discountPercent = (float) ($sale?->discount_percent ?? 0);
                $discountAmount = $discountPercent > 0 && $sale !== null
                    ? (float) $sale->total * $discountPercent / (100 - $discountPercent)
                    : 0.0;
            @endphp
            @if ($discountPercent > 0)
                <tr>
                    <td colspan="3" class="right">Remise ({{ number_format($discountPercent, 0, ',', ' ') }}%)</td>
                    <td class="num">− {{ $money($discountAmount) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td colspan="3" class="right">TOTAL</td>
                <td class="num">{{ $money((float) ($sale?->total ?? 0)) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($sale)
        <p class="block" style="margin-top: 12px;">
            <span class="label">Mode de paiement</span><br>
            <strong>{{ \App\Models\Sale::PAYMENT_METHODS[$sale->payment_method] ?? $sale->payment_method }}</strong>
        </p>
    @endif

    @if ($invoice->notes)
        <div class="notes">
            <p class="label">Remarques</p>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <p>{{ config('app.name') }} — Merci de votre confiance.</p>
    </div>
</body>
</html>
