<?php

namespace App\Http\Controllers\Stock;

use App\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\MonthlyClosing;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\TaxRate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyClosingController extends Controller
{
    use ExportsCsv;

    /**
     * Display the list of monthly closings.
     */
    public function index(Request $request): Response
    {
        $closings = MonthlyClosing::query()
            ->with(['user:id,name'])
            ->latest('year')
            ->latest('month')
            ->get()
            ->map(fn (MonthlyClosing $closing): array => $this->presentClosing($closing));

        // Proposed next month to close.
        $lastClosed = MonthlyClosing::query()->latest('year')->latest('month')->first();
        $proposal = $this->proposeNext($lastClosed);

        return Inertia::render('stock/monthly-closings', [
            'closings' => $closings,
            'proposal' => $proposal,
        ]);
    }

    /**
     * Preview the financial data for a given month before closing.
     */
    public function preview(Request $request): Response
    {
        $year = (int) $request->input('year', now()->subMonth()->year);
        $month = (int) $request->input('month', now()->subMonth()->month);

        $data = $this->computeMonthData($year, $month);
        $data['already_closed'] = MonthlyClosing::isClosed($year, $month);

        return response()->json($data);
    }

    /**
     * Close a month: compute all aggregates and lock them.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $year = (int) $request->input('year');
        $month = (int) $request->input('month');

        if (MonthlyClosing::isClosed($year, $month)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Ce mois est déjà clôturé.',
            ]);

            return back();
        }

        $data = $this->computeMonthData($year, $month);

        $closing = DB::transaction(function () use ($data, $year, $month, $request) {
            return MonthlyClosing::create([
                'user_id' => $request->user()->id,
                'year' => $year,
                'month' => $month,
                'status' => MonthlyClosing::STATUS_CLOSED,
                'revenue' => $data['revenue'],
                'sales_count' => $data['sales_count'],
                'revenue_ht' => $data['revenue_ht'],
                'purchases' => $data['purchases_ttc'],
                'purchases_ht' => $data['purchases_ht'],
                'purchases_count' => $data['purchases_count'],
                'expenses' => $data['expenses'],
                'expenses_count' => $data['expenses_count'],
                'vat_collected' => $data['vat_collected'],
                'vat_deductible' => $data['vat_deductible'],
                'vat_net' => $data['vat_net'],
                'gross_margin' => $data['gross_margin'],
                'net_result' => $data['net_result'],
                'notes' => $request->input('notes'),
                'closed_at' => now(),
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Clôture de {$closing->monthName()} effectuée avec succès.",
        ]);

        return back();
    }

    /**
     * Stream the closing report as PDF.
     */
    public function pdf(MonthlyClosing $closing): SymfonyResponse
    {
        $closing->load('user:id,name');

        return Pdf::loadView('pdf.bilan-mensuel', [
            'closing' => $closing,
        ])->setPaper('a4', 'portrait')
            ->stream("bilan-{$closing->year}-{$closing->month}.pdf");
    }

    /**
     * Export the closing list as CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $closings = MonthlyClosing::query()
            ->with(['user:id,name'])
            ->latest('year')
            ->latest('month')
            ->get();

        return $this->csvResponse('clotures-comptables.csv', function ($write) use ($closings) {
            $write(['Mois', 'Clôturé le', 'Par', 'CA TTC', 'CA HT', 'Achats TTC', 'Dépenses', 'TVA collectée', 'TVA déductible', 'TVA nette', 'Marge brute', 'Résultat net']);

            foreach ($closings as $closing) {
                $write([
                    $closing->monthName(),
                    $closing->closed_at->format('d/m/Y H:i'),
                    $closing->user?->name ?? '',
                    number_format((float) $closing->revenue, 2, ',', ' '),
                    number_format((float) $closing->revenue_ht, 2, ',', ' '),
                    number_format((float) $closing->purchases, 2, ',', ' '),
                    number_format((float) $closing->expenses, 2, ',', ' '),
                    number_format((float) $closing->vat_collected, 2, ',', ' '),
                    number_format((float) $closing->vat_deductible, 2, ',', ' '),
                    number_format((float) $closing->vat_net, 2, ',', ' '),
                    number_format((float) $closing->gross_margin, 2, ',', ' '),
                    number_format((float) $closing->net_result, 2, ',', ' '),
                ]);
            }
        });
    }

    /**
     * Compute all financial aggregates for a given month.
     *
     * @return array<string, mixed>
     */
    private function computeMonthData(int $year, int $month): array
    {
        $from = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $to = (clone $from)->endOfMonth();

        // ── Ventes ────────────────────────────────────────────────
        $sales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->with(['items.product:id,purchase_price'])
            ->get();

        // Exclut les ventes à crédit non soldées du CA.
        $includedSales = $sales->filter(
            fn (Sale $sale): bool => ! $sale->isCredit() || $sale->balance() <= 0.01,
        );
        $excludedSales = $sales->filter(
            fn (Sale $sale): bool => $sale->isCredit() && $sale->balance() > 0.01,
        );

        $revenue = round((float) $includedSales->sum('total'), 2);
        $salesCount = $includedSales->count();

        // TVA collectée.
        $vatCollected = round((float) $includedSales->sum(fn (Sale $sale): float => $sale->taxAmount()), 2);
        $revenueHt = round($revenue - $vatCollected, 2);

        // Coût des ventes.
        $costOfGoods = round((float) $includedSales->flatMap(
            fn (Sale $sale) => $sale->items->map(
                fn (SaleItem $item): float => $item->quantity * (float) $item->product->purchase_price,
            ),
        )->sum(), 2);

        $grossMargin = round($revenueHt - $costOfGoods, 2);

        // ── Achats (réceptions) ───────────────────────────────────
        $receivedOrders = PurchaseOrder::query()
            ->with(['items.product:id,purchase_price'])
            ->whereIn('status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        $purchasesHt = 0.0;
        $purchasesVat = 0.0;

        foreach ($receivedOrders as $order) {
            $ht = $order->receivedAmountHt();
            $purchasesHt += $ht;
            $purchasesVat += $order->taxAmount($ht);
        }

        $purchasesHt = round($purchasesHt, 2);
        $purchasesVat = round($purchasesVat, 2);
        $purchasesTtc = round($purchasesHt + $purchasesVat, 2);

        // ── Dépenses ─────────────────────────────────────────────
        $expensesTotal = round((float) Expense::query()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->sum('amount'), 2);

        $expensesCount = Expense::query()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->count();

        // ── TVA nette ────────────────────────────────────────────
        $vatNet = round($vatCollected - $purchasesVat, 2);

        // ── Résultat net ─────────────────────────────────────────
        $netResult = round($grossMargin - $purchasesVat - $expensesTotal, 2);

        return [
            'revenue' => $revenue,
            'sales_count' => $salesCount,
            'revenue_ht' => $revenueHt,
            'purchases_ht' => $purchasesHt,
            'purchases_ttc' => $purchasesTtc,
            'purchases_count' => $receivedOrders->count(),
            'expenses' => $expensesTotal,
            'expenses_count' => $expensesCount,
            'vat_collected' => $vatCollected,
            'vat_deductible' => $purchasesVat,
            'vat_net' => $vatNet,
            'gross_margin' => $grossMargin,
            'net_result' => $netResult,
            'credit_excluded' => round((float) $excludedSales->sum('total'), 2),
            'credit_excluded_count' => $excludedSales->count(),
        ];
    }

    /**
     * Propose the next month to close based on the last closing.
     *
     * @return array{year: int, month: int, label: string}|null
     */
    private function proposeNext(?MonthlyClosing $lastClosed): ?array
    {
        if ($lastClosed !== null) {
            $next = Carbon::createFromDate($lastClosed->year, $lastClosed->month, 1)->addMonth();
        } else {
            $next = now()->subMonth()->startOfMonth();
        }

        // Don't propose a future month.
        if ($next->isAfter(now())) {
            return null;
        }

        return [
            'year' => $next->year,
            'month' => $next->month,
            'label' => $next->translatedFormat('F Y'),
        ];
    }

    /**
     * Build a human-readable representation of a closing for Inertia.
     *
     * @return array<string, mixed>
     */
    private function presentClosing(MonthlyClosing $closing): array
    {
        return [
            'id' => $closing->id,
            'year' => $closing->year,
            'month' => $closing->month,
            'label' => $closing->monthName(),
            'status' => $closing->status,
            'revenue' => (float) $closing->revenue,
            'sales_count' => $closing->sales_count,
            'revenue_ht' => (float) $closing->revenue_ht,
            'purchases' => (float) $closing->purchases,
            'purchases_ht' => (float) $closing->purchases_ht,
            'purchases_count' => $closing->purchases_count,
            'expenses' => (float) $closing->expenses,
            'expenses_count' => $closing->expenses_count,
            'vat_collected' => (float) $closing->vat_collected,
            'vat_deductible' => (float) $closing->vat_deductible,
            'vat_net' => (float) $closing->vat_net,
            'gross_margin' => (float) $closing->gross_margin,
            'net_result' => (float) $closing->net_result,
            'notes' => $closing->notes,
            'closed_at' => $closing->closed_at,
            'user' => $closing->user,
        ];
    }
}
