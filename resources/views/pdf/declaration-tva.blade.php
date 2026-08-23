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
            line-height: 1.5;
        }
        .container { padding: 25px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #111; padding-bottom: 15px; }
        .company-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 16px; font-weight: bold; margin-top: 8px; }
        .period { font-size: 12px; color: #555; margin-top: 4px; }
        .section { margin-bottom: 18px; }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            background: #f0f0f0;
            padding: 6px 10px;
            margin-bottom: 8px;
            border-left: 3px solid #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 5px 8px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
            font-size: 10px;
            text-transform: uppercase;
            color: #555;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .total-row { background: #f9f9f9; font-weight: bold; }
        .summary-box {
            border: 2px solid #333;
            padding: 12px;
            margin-top: 15px;
        }
        .summary-title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            text-align: center;
            background: #333;
            color: #fff;
            padding: 6px;
        }
        .summary-line {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px dashed #ccc;
        }
        .summary-line:last-child { border-bottom: none; }
        .summary-line.total {
            font-weight: bold;
            font-size: 13px;
            border-top: 2px solid #333;
            border-bottom: none;
            padding-top: 8px;
            margin-top: 4px;
        }
        .credit-note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 8px;
            margin-top: 10px;
            font-size: 10px;
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .muted { color: #777; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-green { background: #d4edda; color: #155724; }
        .badge-red { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        {{-- En-tête --}}
        <div class="header">
            <p class="company-name">{{ config('app.name') }}</p>
            <p class="doc-title">DÉCLARATION DE TVA</p>
            <p class="period">
                Période : du {{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}
                au {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}
            </p>
        </div>

        {{-- TVA Collectée --}}
        <div class="section">
            <div class="section-title">1. TVA Collectée (opérations imposables)</div>
            @if (count($collected_by_rate) > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Taux</th>
                            <th class="text-right">Base HT</th>
                            <th class="text-right">TVA collectée</th>
                            <th class="text-right">Montant TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($collected_by_rate as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">{{ $money($row['base']) }}</td>
                                <td class="text-right">{{ $money($row['vat']) }}</td>
                                <td class="text-right">{{ $money($row['ttc']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td class="font-bold">Total</td>
                            <td class="text-right font-bold">{{ $money($total_collected_base) }}</td>
                            <td class="text-right font-bold">{{ $money($total_collected) }}</td>
                            <td class="text-right font-bold">{{ $money($total_collected_ttc) }}</td>
                        </tr>
                    </tbody>
                </table>
            @else
                <p class="muted text-center">Aucune opération imposable sur la période.</p>
            @endif
        </div>

        {{-- TVA Déductible --}}
        <div class="section">
            <div class="section-title">2. TVA Déductible (achats et charges)</div>
            @if (count($deductible_by_rate) > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Taux</th>
                            <th class="text-right">Base HT</th>
                            <th class="text-right">TVA déductible</th>
                            <th class="text-right">Montant TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deductible_by_rate as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">{{ $money($row['base']) }}</td>
                                <td class="text-right">{{ $money($row['vat']) }}</td>
                                <td class="text-right">{{ $money($row['ttc']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td class="font-bold">Total</td>
                            <td class="text-right font-bold">{{ $money($total_deductible_base) }}</td>
                            <td class="text-right font-bold">{{ $money($total_deductible) }}</td>
                            <td class="text-right font-bold">{{ $money($total_deductible_ttc) }}</td>
                        </tr>
                    </tbody>
                </table>
            @else
                <p class="muted text-center">Aucune réception fournisseur sur la période.</p>
            @endif
        </div>

        {{-- Résultat --}}
        <div class="summary-box">
            <div class="summary-title">Résultat de la déclaration</div>

            <div class="summary-line">
                <span>TVA collectée</span>
                <span class="font-bold">{{ $money($total_collected) }}</span>
            </div>
            <div class="summary-line">
                <span>TVA déductible</span>
                <span class="font-bold">{{ $money($total_deductible) }}</span>
            </div>
            <div class="summary-line total">
                <span>TVA nette à reverser</span>
                <span>
                    @if ($net_vat >= 0)
                        <span class="badge badge-green">{{ $money($net_vat) }}</span>
                    @else
                        <span class="badge badge-red">{{ $money($net_vat) }}</span>
                    @endif
                </span>
            </div>
        </div>

        @if ($net_vat < 0)
            <div class="credit-note">
                ⚠ Le solde est négatif : crédit de TVA de <strong>{{ $money($net_vat) }}</strong> reportable sur la période suivante.
            </div>
        @endif

        @if ($credit_excluded_count > 0)
            <div class="credit-note" style="background: #e2e3e5; border-color: #6c757d; color: #383d41;">
                ℹ {{ $credit_excluded_count }} vente(s) à crédit non encaissée(s) exclue(s) de cette déclaration ({{ $credit_excluded_count > 1 ? 'comptabilisées une fois soldées' : 'comptabilisée une fois soldée' }}).
            </div>
        @endif

        {{-- Pied de page --}}
        <div class="footer">
            <p>Document généré le {{ now()->format('d/m/Y à H:i') }} — {{ config('app.name') }}</p>
            <p>Cette déclaration est un récapitulatif interne. Consultez votre comptable pour la déclaration officielle.</p>
        </div>
    </div>
</body>
</html>
