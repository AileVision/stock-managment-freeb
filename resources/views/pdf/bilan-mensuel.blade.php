@php
    $money = fn (float $value): string => number_format($value, 0, ',', ' ') . ' FCFA';
    $monthLabel = \Carbon\Carbon::createFromDate($closing->year, $closing->month, 1)->translatedFormat('F Y');
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
        .result-box {
            margin-top: 15px;
            text-align: center;
            padding: 15px;
            border: 2px solid #333;
            background: #f9f9f9;
        }
        .result-title { font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; }
        .result-value { font-size: 22px; font-weight: bold; }
        .result-positive { color: #155724; }
        .result-negative { color: #721c24; }
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
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .info-note {
            background: #e2e3e5;
            border: 1px solid #6c757d;
            padding: 6px 10px;
            margin-top: 8px;
            font-size: 10px;
            text-align: center;
            color: #383d41;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- En-tête --}}
        <div class="header">
            <p class="company-name">{{ config('app.name') }}</p>
            <p class="doc-title">BILAN COMPTABLE MENSUEL</p>
            <p class="period">{{ $monthLabel }} · Clôturé le {{ $closing->closed_at->format('d/m/Y à H:i') }}</p>
        </div>

        {{-- 1. Chiffre d'affaires --}}
        <div class="section">
            <div class="section-title">1. Chiffre d'affaires</div>
            <table>
                <tbody>
                    <tr>
                        <td>CA TTC (ventes comptabilisées)</td>
                        <td class="text-right font-bold">{{ $money((float) $closing->revenue) }}</td>
                    </tr>
                    <tr>
                        <td>Nombre de ventes</td>
                        <td class="text-right">{{ $closing->sales_count }}</td>
                    </tr>
                    <tr>
                        <td>CA hors taxes</td>
                        <td class="text-right">{{ $money((float) $closing->revenue_ht) }}</td>
                    </tr>
                    <tr>
                        <td>TVA collectée</td>
                        <td class="text-right font-bold">{{ $money((float) $closing->vat_collected) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- 2. Achats & réceptions --}}
        <div class="section">
            <div class="section-title">2. Achats (réceptions fournisseur)</div>
            <table>
                <tbody>
                    <tr>
                        <td>Achats TTC</td>
                        <td class="text-right">{{ $money((float) $closing->purchases) }}</td>
                    </tr>
                    <tr>
                        <td>Achats hors taxes</td>
                        <td class="text-right">{{ $money((float) $closing->purchases_ht) }}</td>
                    </tr>
                    <tr>
                        <td>Nombre de réceptions</td>
                        <td class="text-right">{{ $closing->purchases_count }}</td>
                    </tr>
                    <tr>
                        <td>TVA déductible</td>
                        <td class="text-right font-bold">{{ $money((float) $closing->vat_deductible) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- 3. Dépenses d'exploitation --}}
        <div class="section">
            <div class="section-title">3. Dépenses d'exploitation</div>
            <table>
                <tbody>
                    <tr>
                        <td>Total dépenses</td>
                        <td class="text-right font-bold">{{ $money((float) $closing->expenses) }}</td>
                    </tr>
                    <tr>
                        <td>Nombre de dépenses</td>
                        <td class="text-right">{{ $closing->expenses_count }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- 4. TVA --}}
        <div class="section">
            <div class="section-title">4. Situation TVA</div>
            <table>
                <thead>
                    <tr>
                        <th>Poste</th>
                        <th class="text-right">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>TVA collectée</td>
                        <td class="text-right">{{ $money((float) $closing->vat_collected) }}</td>
                    </tr>
                    <tr>
                        <td>TVA déductible</td>
                        <td class="text-right">{{ $money((float) $closing->vat_deductible) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>TVA nette à reverser</td>
                        <td class="text-right font-bold">
                            @if ((float) $closing->vat_net >= 0)
                                <span class="badge badge-green">{{ $money((float) $closing->vat_net) }}</span>
                            @else
                                <span class="badge badge-red">{{ $money((float) $closing->vat_net) }}</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
            @if ((float) $closing->vat_net < 0)
                <div class="info-note">Crédit de TVA reportable sur la période suivante.</div>
            @endif
        </div>

        {{-- 5. Résultat --}}
        <div class="summary-box">
            <div class="summary-title">Synthèse du mois</div>

            <div class="summary-line">
                <span>Chiffre d'affaires HT</span>
                <span>{{ $money((float) $closing->revenue_ht) }}</span>
            </div>
            <div class="summary-line">
                <span>− Coût des marchandises vendues</span>
                <span>{{ $money((float) $closing->revenue_ht - (float) $closing->gross_margin) }}</span>
            </div>
            <div class="summary-line total">
                <span>= Marge brute</span>
                <span>{{ $money((float) $closing->gross_margin) }}</span>
            </div>
            <div class="summary-line">
                <span>− Dépenses d'exploitation</span>
                <span>{{ $money((float) $closing->expenses) }}</span>
            </div>
            <div class="summary-line">
                <span>− TVA à reverser</span>
                <span>{{ $money(max((float) $closing->vat_net, 0)) }}</span>
            </div>
        </div>

        <div class="result-box">
            <div class="result-title">Résultat net du mois</div>
            <div class="result-value {{ (float) $closing->net_result >= 0 ? 'result-positive' : 'result-negative' }}">
                {{ $money((float) $closing->net_result) }}
            </div>
        </div>

        @if ($closing->notes)
            <div class="info-note" style="margin-top: 12px; text-align: left;">
                📝 {{ $closing->notes }}
            </div>
        @endif

        {{-- Pied de page --}}
        <div class="footer">
            <p>Clôturé par {{ $closing->user?->name ?? '—' }} le {{ $closing->closed_at->format('d/m/Y à H:i') }}</p>
            <p>Document généré le {{ now()->format('d/m/Y à H:i') }} — {{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
