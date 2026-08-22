@php
    $money = fn (float $value): string => number_format($value, 0, ',', ' ') . ' FCFA';
    $hasOverdue = $rows->contains(fn (array $row): bool => $row['overdue']);
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
        .overdue { color: #b91c1c; font-weight: bold; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #777; }
        .notice {
            margin-top: 28px;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 11px;
            line-height: 1.6;
        }
        .notice strong { display: block; margin-bottom: 6px; }
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
                <p class="doc-title">Relevé de compte</p>
                <p class="muted">Édité le {{ now()->format('d/m/Y') }}</p>
            </td>
        </tr>
    </table>

    <table class="block">
        <tr>
            <td>
                <p class="label">Client</p>
                <p><strong>{{ $customer->name }}</strong></p>
                @if ($customer->phone)
                    <p class="muted">Tél. : {{ $customer->phone }}</p>
                @endif
                @if ($customer->email)
                    <p class="muted">{{ $customer->email }}</p>
                @endif
                @if ($customer->address)
                    <p class="muted">{!! nl2br(e($customer->address)) !!}</p>
                @endif
            </td>
            <td class="right">
                <p class="label">Situation</p>
                <p><strong>{{ $money($balance) }}</strong> à régler</p>
                @if ($hasOverdue)
                    <p class="overdue">Dont sommes échues</p>
                @endif
            </td>
        </tr>
    </table>

    @if ($rows->isEmpty())
        <p class="muted">Aucun solde impayé.</p>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 20%;">Vente</th>
                    <th style="width: 14%;">Date</th>
                    <th style="width: 14%;">Échéance</th>
                    <th style="width: 18%;" class="num">Montant</th>
                    <th style="width: 16%;" class="num">Encaissé</th>
                    <th style="width: 18%;" class="num">Solde</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['reference'] }}</td>
                        <td>{{ $row['created_at']?->format('d/m/Y') }}</td>
                        <td>{{ $row['due_at']?->format('d/m/Y') ?? '—' }}</td>
                        <td class="num">{{ $money($row['total']) }}</td>
                        <td class="num">{{ $money($row['paid']) }}</td>
                        <td class="num {{ $row['overdue'] ? 'overdue' : '' }}">
                            {{ $money($row['balance']) }}
                            @if ($row['overdue'])
                                <br><span style="font-size: 9px;">{{ $row['days_late'] }} j de retard</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="5" class="right">TOTAL À RÉGLER</td>
                    <td class="num">{{ $money($balance) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="notice">
        <strong>{{ $hasOverdue ? 'Relance de paiement' : 'Récapitulatif de vos achats à crédit' }}</strong>
        @if ($hasOverdue)
            Nous vous remercions de bien vouloir régulariser votre situation dans les plus brefs délais.
            N'hésitez pas à nous contacter pour convenir d'un échéancier de paiement.
        @else
            Ce relevé récapitule les montants restant dus sur vos achats à crédit.
            Nous restons à votre disposition pour toute question.
        @endif
    </div>

    <div class="footer">
        <p>{{ config('app.name') }} — Merci de votre confiance.</p>
    </div>
</body>
</html>
