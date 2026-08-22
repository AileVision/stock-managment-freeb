@php
    $money = fn (float $value): string => number_format($value, 0, ',', ' ') . ' FCFA';
    $remaining = $creditNote->remaining();
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
        .amount-box {
            margin-top: 24px;
            border: 2px solid #111;
            padding: 16px;
            text-align: center;
        }
        .amount-box .value { font-size: 26px; font-weight: bold; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #777; }
        .notice { margin-top: 28px; font-size: 11px; line-height: 1.6; }
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
                <p class="doc-title">Avoir</p>
                <p class="muted">N° {{ $creditNote->reference }}</p>
                <p class="muted">Date d'émission : {{ $creditNote->issued_at->format('d/m/Y') }}</p>
            </td>
        </tr>
    </table>

    <table class="block">
        <tr>
            <td>
                <p class="label">Bénéficiaire</p>
                <p><strong>{{ $creditNote->customer?->name ?? 'Client de passage' }}</strong></p>
                @if ($creditNote->customer?->phone)
                    <p class="muted">Tél. : {{ $creditNote->customer->phone }}</p>
                @endif
            </td>
            <td class="right">
                <p class="label">Retour d'origine</p>
                <p><strong>{{ $creditNote->saleReturn?->reference ?? '—' }}</strong></p>
                @if ($creditNote->saleReturn)
                    <p class="muted">Date : {{ $creditNote->saleReturn->returned_at->format('d/m/Y') }}</p>
                @endif
            </td>
        </tr>
    </table>

    <div class="amount-box">
        <p class="label">Montant de l'avoir</p>
        <p class="value">{{ $money((float) $creditNote->amount) }}</p>
        <p class="muted" style="margin-top: 6px;">
            Restant utilisable : {{ $money($remaining) }}
        </p>
    </div>

    @if ($creditNote->notes)
        <div class="notice">
            <p class="label">Remarques</p>
            <p>{{ $creditNote->notes }}</p>
        </div>
    @endif

    <div class="notice">
        <p>
            Cet avoir est utilisable sur vos prochains achats auprès de
            {{ config('app.name') }}. Présentez ce document ou mentionnez sa
            référence lors de votre prochaine visite.
        </p>
    </div>

    <div class="footer">
        <p>{{ config('app.name') }} — Merci de votre confiance.</p>
    </div>
</body>
</html>
