@php
    $money = fn (float $value): string => number_format($value, 0, ',', ' ') . ' FCFA';
    $methods = \App\Models\CustomerPayment::PAYMENT_METHODS;
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
        .amount-row { display: block; text-align: center; margin: 6px 0; }
        .amount {
            display: inline-block;
            font-size: 16px;
            font-weight: bold;
            border: 2px solid #111;
            border-radius: 6px;
            padding: 4px 12px;
        }
        .footer { margin-top: 8px; text-align: center; font-size: 10px; }
    </style>
</head>
<body>
    <div class="center">
        <p class="shop-name">{{ config('app.name') }}</p>
        <p class="muted">Reçu d’encaissement</p>
    </div>

    <div class="divider"></div>

    <p class="row"><strong>N° :</strong> REC-{{ str_pad((string) $payment->id, 4, '0', STR_PAD_LEFT) }}</p>
    <p class="row"><strong>Date :</strong> {{ $payment->paid_at->format('d/m/Y H:i') }}</p>
    <p class="row"><strong>Caissier :</strong> {{ $payment->user->name ?? '—' }}</p>

    <div class="divider"></div>

    @if ($customer)
        <p class="row"><strong>Client :</strong> {{ $customer->name }}</p>
        @if ($customer->phone)
            <p class="row"><strong>Tél :</strong> {{ $customer->phone }}</p>
        @endif
    @endif
    <p class="row"><strong>Vente :</strong> {{ $sale->reference }}</p>
    <p class="row"><strong>Vente du :</strong> {{ $sale->created_at->format('d/m/Y') }}</p>

    <div class="divider"></div>

    <div class="amount-row">
        <span class="amount">{{ $money((float) $payment->amount) }}</span>
    </div>

    <p class="row"><strong>Mode de paiement :</strong> {{ $methods[$payment->payment_method] ?? $payment->payment_method }}</p>

    @if ($payment->isMobilePayment())
        @if ($payment->mobile_phone)
            <p class="row"><strong>Mobile :</strong> {{ $payment->mobile_phone }}</p>
        @endif
        @if ($payment->mobile_reference)
            <p class="row muted">Réf. {{ $payment->mobile_reference }}</p>
        @endif
    @endif

    @if ($payment->notes)
        <p class="muted">{{ $payment->notes }}</p>
    @endif

    <div class="divider"></div>

    <p class="row"><strong>Solde restant :</strong> {{ $money($balance) }}</p>

    <div class="footer">
        <p>Merci de votre confiance !</p>
    </div>
</body>
</html>
