<?php

namespace App\Http\Controllers\Stock;

use App\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxRateRequest;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\TaxRate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxRateController extends Controller
{
    /**
     * Display the tax rates and the net VAT summary for the period.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('stock/taxes', [
            'tax_rates' => TaxRate::query()
                ->orderBy('rate')
                ->orderBy('name')
                ->get()
                ->map(fn (TaxRate $taxRate): array => [
                    'id' => $taxRate->id,
                    'name' => $taxRate->name,
                    'rate' => (float) $taxRate->rate,
                    'is_default' => (bool) $taxRate->is_default,
                    'is_active' => (bool) $taxRate->is_active,
                ]),
            'vat' => $this->vatSummary($request),
        ]);
    }

    /**
     * Compute the net VAT to remit on the period: collected on completed sales
     * minus deductible on received purchase orders, broken down by rate.
     *
     * @return array<string, mixed>
     */
    private function vatSummary(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfDay();

        $sales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        $collectedByRate = [];
        foreach ($sales as $sale) {
            $rate = (float) $sale->tax_percent;
            $collectedByRate[$rate] = ($collectedByRate[$rate] ?? 0.0) + $sale->taxAmount();
        }

        $receivedOrders = PurchaseOrder::query()
            ->with(['items.product:id,purchase_price'])
            ->whereIn('status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        $deductibleByRate = [];
        foreach ($receivedOrders as $order) {
            $rate = $order->taxPercent();
            $deductibleByRate[$rate] = ($deductibleByRate[$rate] ?? 0.0) + $order->taxAmount($order->receivedAmountHt());
        }

        $rates = array_keys($collectedByRate + $deductibleByRate);
        sort($rates);

        $byRate = collect($rates)->map(function ($rate) use ($collectedByRate, $deductibleByRate): array {
            $rate = (float) $rate;
            $collected = round((float) ($collectedByRate[$rate] ?? 0.0), 2);
            $deductible = round((float) ($deductibleByRate[$rate] ?? 0.0), 2);

            return [
                'rate' => $rate,
                'name' => $rate > 0 ? "TVA {$rate} %" : 'TVA 0 %',
                'collected' => $collected,
                'deductible' => $deductible,
                'net' => round($collected - $deductible, 2),
            ];
        })->values();

        $collected = round((float) $byRate->sum('collected'), 2);
        $deductible = round((float) $byRate->sum('deductible'), 2);

        return [
            'collected' => $collected,
            'deductible' => $deductible,
            'net' => round($collected - $deductible, 2),
            'by_rate' => $byRate,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ];
    }

    /**
     * Store a newly created tax rate.
     */
    public function store(StoreTaxRateRequest $request): RedirectResponse
    {
        TaxRate::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taux de TVA créé avec succès.']);

        return back();
    }

    /**
     * Update the specified tax rate.
     */
    public function update(StoreTaxRateRequest $request, TaxRate $taxRate): RedirectResponse
    {
        $taxRate->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taux de TVA mis à jour avec succès.']);

        return back();
    }

    /**
     * Set the default tax rate (previous default loses its flag).
     */
    public function setDefault(TaxRate $taxRate): RedirectResponse
    {
        TaxRate::query()->update(['is_default' => false]);
        $taxRate->update(['is_default' => true, 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taux de TVA par défaut mis à jour.']);

        return back();
    }

    /**
     * Remove the specified tax rate when nothing uses it.
     */
    public function destroy(TaxRate $taxRate): RedirectResponse
    {
        if ($taxRate->is_default) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Le taux par défaut ne peut pas être supprimé.']);

            return back();
        }

        $taxRate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taux de TVA supprimé avec succès.']);

        return back();
    }

    /**
     * Export the VAT declaration as a formal PDF.
     */
    public function vatDeclarationPdf(Request $request): SymfonyResponse
    {
        $data = $this->vatDeclarationData($request);

        return Pdf::loadView('pdf.declaration-tva', $data)
            ->setPaper('a4', 'portrait')
            ->stream('declaration-tva-'.str_replace('/', '-', $data['filters']['from']).'-'.str_replace('/', '-', $data['filters']['to']).'.pdf');
    }

    /**
     * Export the VAT declaration as CSV.
     */
    public function vatDeclarationCsv(Request $request): StreamedResponse
    {
        $data = $this->vatDeclarationData($request);

        return $this->csvResponse('declaration-tva.csv', function ($write) use ($data) {
            $write(['Déclaration de TVA']);
            $write(['Période', $data['filters']['from'], '→', $data['filters']['to']]);
            $write([]);

            // En-tête : identification
            $write(['ENTREPRISE', config('app.name')]);
            $write(['Période de la déclaration', $data['filters']['from'].' au '.$data['filters']['to']]);
            $write([]);

            // Section TVA collectée
            $write(['TVA COLLECTÉE (opérations imposables)']);
            $write(['Taux', 'Base HT', 'TVA collectée', 'Montant TTC']);

            foreach ($data['collected_by_rate'] as $row) {
                $write([
                    $row['name'],
                    number_format($row['base'], 2, ',', ' '),
                    number_format($row['vat'], 2, ',', ' '),
                    number_format($row['ttc'], 2, ',', ' '),
                ]);
            }

            $write(['Total',
                number_format($data['total_collected_base'], 2, ',', ' '),
                number_format($data['total_collected'], 2, ',', ' '),
                number_format($data['total_collected_ttc'], 2, ',', ' '),
            ]);
            $write([]);

            // Section TVA déductible
            $write(['TVA DÉDUCTIBLE (achats et charges)']);
            $write(['Taux', 'Base HT', 'TVA déductible', 'Montant TTC']);

            foreach ($data['deductible_by_rate'] as $row) {
                $write([
                    $row['name'],
                    number_format($row['base'], 2, ',', ' '),
                    number_format($row['vat'], 2, ',', ' '),
                    number_format($row['ttc'], 2, ',', ' '),
                ]);
            }

            $write(['Total',
                number_format($data['total_deductible_base'], 2, ',', ' '),
                number_format($data['total_deductible'], 2, ',', ' '),
                number_format($data['total_deductible_ttc'], 2, ',', ' '),
            ]);
            $write([]);

            // Résultat
            $write(['RÉSULTAT DE LA DÉCLARATION']);
            $write(['TVA collectée', number_format($data['total_collected'], 2, ',', ' ')]);
            $write(['TVA déductible', number_format($data['total_deductible'], 2, ',', ' ')]);
            $write(['TVA nette à reverser', number_format($data['net_vat'], 2, ',', ' ')]);
            $write([]);

            if ($data['net_vat'] < 0) {
                $write(['Le solde est négatif : crédit de TVA reportable sur la période suivante.']);
            }
        });
    }

    /**
     * Compute the detailed VAT declaration data.
     *
     * @return array<string, mixed>
     */
    private function vatDeclarationData(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfDay();

        // Ventes complétées sur la période.
        $sales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        // Exclut les ventes à crédit non encaissées du calcul de la TVA.
        $excludedSales = $sales->filter(
            fn (Sale $sale): bool => $sale->isCredit() && $sale->balance() > 0.01,
        );
        $includedSales = $sales->filter(
            fn (Sale $sale): bool => ! $sale->isCredit() || $sale->balance() <= 0.01,
        );

        // TVA collectée par taux.
        $collectedByRate = [];
        foreach ($includedSales as $sale) {
            $rate = (float) $sale->tax_percent;
            $base = $rate > 0 ? round((float) $sale->total / (1 + $rate / 100), 2) : (float) $sale->total;
            $vat = $rate > 0 ? $sale->taxAmount() : 0.0;

            if (! isset($collectedByRate[$rate])) {
                $collectedByRate[$rate] = ['name' => $rate > 0 ? "TVA {$rate} %" : 'TVA 0 %', 'rate' => $rate, 'base' => 0.0, 'vat' => 0.0, 'ttc' => 0.0];
            }

            $collectedByRate[$rate]['base'] += $base;
            $collectedByRate[$rate]['vat'] += $vat;
            $collectedByRate[$rate]['ttc'] += (float) $sale->total;
        }

        $collectedRates = array_values($collectedByRate);
        usort($collectedRates, fn (array $a, array $b): int => $b['rate'] <=> $a['rate']);

        // Arrondir.
        foreach ($collectedRates as &$row) {
            $row['base'] = round($row['base'], 2);
            $row['vat'] = round($row['vat'], 2);
            $row['ttc'] = round($row['ttc'], 2);
        }
        unset($row);

        $totalCollected = round(array_sum(array_column($collectedRates, 'vat')), 2);
        $totalCollectedBase = round(array_sum(array_column($collectedRates, 'base')), 2);
        $totalCollectedTtc = round(array_sum(array_column($collectedRates, 'ttc')), 2);

        // Réceptions fournisseur.
        $receivedOrders = PurchaseOrder::query()
            ->with(['items.product:id,purchase_price'])
            ->whereIn('status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        // TVA déductible par taux.
        $deductibleByRate = [];
        foreach ($receivedOrders as $order) {
            $rate = $order->taxPercent();
            $amountHt = $order->receivedAmountHt();
            $vat = $order->taxAmount($amountHt);

            if (! isset($deductibleByRate[$rate])) {
                $deductibleByRate[$rate] = ['name' => $rate > 0 ? "TVA {$rate} %" : 'TVA 0 %', 'rate' => $rate, 'base' => 0.0, 'vat' => 0.0, 'ttc' => 0.0];
            }

            $deductibleByRate[$rate]['base'] += $amountHt;
            $deductibleByRate[$rate]['vat'] += $vat;
            $deductibleByRate[$rate]['ttc'] += round($amountHt + $vat, 2);
        }

        $deductibleRates = array_values($deductibleByRate);
        usort($deductibleRates, fn (array $a, array $b): int => $b['rate'] <=> $a['rate']);

        foreach ($deductibleRates as &$row) {
            $row['base'] = round($row['base'], 2);
            $row['vat'] = round($row['vat'], 2);
            $row['ttc'] = round($row['ttc'], 2);
        }
        unset($row);

        $totalDeductible = round(array_sum(array_column($deductibleRates, 'vat')), 2);
        $totalDeductibleBase = round(array_sum(array_column($deductibleRates, 'base')), 2);
        $totalDeductibleTtc = round(array_sum(array_column($deductibleRates, 'ttc')), 2);

        $netVat = round($totalCollected - $totalDeductible, 2);

        return [
            'collected_by_rate' => $collectedRates,
            'deductible_by_rate' => $deductibleRates,
            'total_collected' => $totalCollected,
            'total_collected_base' => $totalCollectedBase,
            'total_collected_ttc' => $totalCollectedTtc,
            'total_deductible' => $totalDeductible,
            'total_deductible_base' => $totalDeductibleBase,
            'total_deductible_ttc' => $totalDeductibleTtc,
            'net_vat' => $netVat,
            'credit_excluded_count' => $excludedSales->count(),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ];
    }
}
