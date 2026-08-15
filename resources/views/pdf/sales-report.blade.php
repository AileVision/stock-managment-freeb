@php
    $money = fn (float $value): string => number_format($value, 0, ',', ' ') . ' FCFA';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; }
        .muted { color: #666; }
        .header { margin-bottom: 16px; }
        .kpis { width: 100%; margin-bottom: 18px; }
        .kpis td { border: 1px solid #ddd; padding: 8px; }
        .kpis .label { font-size: 9px; text-transform: uppercase; color: #777; }
        .kpis .value { font-size: 15px; font-weight: bold; }
        h2 { font-size: 13px; margin: 14px 0 6px; text-transform: uppercase; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { text-align: left; font-size: 9px; text-transform: uppercase; color: #666; border-bottom: 1px solid #ccc; padding: 5px 4px; }
        table.grid td { padding: 6px 4px; border-bottom: 1px solid #eee; }
        .num { text-align: right; white-space: nowrap; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rapport de ventes</h1>
        <p class="muted">
            {{ config('app.name') }} · du {{ $filters['from'] }} au {{ $filters['to'] }} ·
            ventes validées uniquement
        </p>
    </div>

    <table class="kpis">
        <tr>
            <td>
                <p class="label">Chiffre d'affaires</p>
                <p class="value">{{ $money((float) $summary['revenue']) }}</p>
            </td>
            <td>
                <p class="label">Ventes</p>
                <p class="value">{{ (int) $summary['sales_count'] }}</p>
            </td>
            <td>
                <p class="label">Panier moyen</p>
                <p class="value">{{ $money((float) $summary['average_basket']) }}</p>
            </td>
            <td>
                <p class="label">Marge brute</p>
                <p class="value">{{ $money((float) $summary['margin']) }}</p>
            </td>
            <td>
                <p class="label">Taux de marge</p>
                <p class="value">{{ number_format((float) $summary['margin_rate'], 1, ',', ' ') }} %</p>
            </td>
        </tr>
    </table>

    <h2>Chiffre d'affaires par jour</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Date</th>
                <th class="num">Ventes</th>
                <th class="num">CA</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($daily as $row)
                <tr>
                    <td>{{ $row['day'] }}</td>
                    <td class="num">{{ (int) $row['sales_count'] }}</td>
                    <td class="num">{{ $money((float) $row['revenue']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Meilleures ventes</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Produit</th>
                <th class="num">Qté</th>
                <th class="num">CA</th>
                <th class="num">Coût</th>
                <th class="num">Marge</th>
                <th class="num">Taux</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($top_products as $row)
                <tr>
                    <td>{{ $row['name'] }} <span class="muted">({{ $row['sku'] }})</span></td>
                    <td class="num">{{ (int) $row['quantity'] }}</td>
                    <td class="num">{{ $money((float) $row['revenue']) }}</td>
                    <td class="num">{{ $money((float) $row['cost']) }}</td>
                    <td class="num">{{ $money((float) $row['margin']) }}</td>
                    <td class="num">{{ number_format((float) $row['margin_rate'], 1, ',', ' ') }} %</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Par mode de paiement</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Mode</th>
                <th class="num">Ventes</th>
                <th class="num">CA</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($by_payment as $row)
                <tr>
                    <td>{{ $row['payment_label'] }}</td>
                    <td class="num">{{ (int) $row['sales_count'] }}</td>
                    <td class="num">{{ $money((float) $row['revenue']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>{{ config('app.name') }} — rapport généré le {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
