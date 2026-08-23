<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; }
        .container { padding: 20px; max-width: 600px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 15px; }
        .header h1 { font-size: 18px; color: #2563eb; margin-bottom: 4px; }
        .header p { font-size: 10px; color: #666; }
        .title { text-align: center; font-size: 14px; font-weight: bold; margin: 15px 0; text-transform: uppercase; color: #2563eb; }
        .info-grid { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .info-block { width: 48%; }
        .info-block h3 { font-size: 10px; text-transform: uppercase; color: #666; margin-bottom: 4px; }
        .info-block p { font-size: 11px; margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; }
        td { padding: 6px 8px; font-size: 11px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; border-top: 2px solid #2563eb; }
        .summary { margin: 15px 0; padding: 12px; background: #f0f9ff; border-radius: 6px; border: 1px solid #bae6fd; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .summary-row:last-child { margin-bottom: 0; }
        .summary-label { font-size: 11px; color: #555; }
        .summary-value { font-size: 11px; font-weight: bold; }
        .footer { margin-top: 25px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        .stamp { margin-top: 20px; text-align: right; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name', 'Stock Management') }}</h1>
            <p>Reçu de paiement fournisseur</p>
        </div>

        <div class="title">Reçu N° {{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</div>

        <div class="info-grid">
            <div class="info-block">
                <h3>Fournisseur</h3>
                <p><strong>{{ $supplier->name ?? '—' }}</strong></p>
                @if($supplier->phone)
                    <p>Tél : {{ $supplier->phone }}</p>
                @endif
                @if($supplier->address)
                    <p>{{ $supplier->address }}</p>
                @endif
            </div>
            <div class="info-block">
                <h3>Détails du paiement</h3>
                <p>Date : <strong>{{ $payment->paid_at->format('d/m/Y H:i') }}</strong></p>
                <p>Commande : <strong>{{ $order->reference }}</strong></p>
                <p>Mode : <strong>{{ App\Models\SupplierPayment::PAYMENT_METHODS[$payment->payment_method] ?? $payment->payment_method }}</strong></p>
                @if($payment->reference)
                    <p>Réf. : {{ $payment->reference }}</p>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Paiement sur commande {{ $order->reference }}</td>
                    <td class="text-right">{{ number_format((float) $payment->amount, 2, ',', ' ') }} FCFA</td>
                </tr>
            </tbody>
        </table>

        <div class="summary">
            <div class="summary-row">
                <span class="summary-label">Montant payé :</span>
                <span class="summary-value">{{ number_format((float) $payment->amount, 2, ',', ' ') }} FCFA</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Solde restant :</span>
                <span class="summary-value">{{ number_format($balance, 2, ',', ' ') }} FCFA</span>
            </div>
        </div>

        @if($payment->notes)
            <div style="margin-top: 15px;">
                <p style="font-size: 10px; color: #666; text-transform: uppercase;">Notes</p>
                <p style="font-size: 11px;">{{ $payment->notes }}</p>
            </div>
        @endif

        <div class="stamp">
            <p>Enregistré par : {{ $payment->user->name ?? '—' }}</p>
            <p>Le {{ $payment->paid_at->format('d/m/Y à H:i') }}</p>
        </div>

        <div class="footer">
            <p>{{ config('app.name') }} — Reçu de paiement fournisseur</p>
            <p>Ce document ne constitue pas une facture.</p>
        </div>
    </div>
</body>
</html>
