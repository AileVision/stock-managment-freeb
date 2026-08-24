<?php

namespace App\Http\Controllers\Stock;

use App\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\StockAlertService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierPayablesController extends Controller
{
    use ExportsCsv;

    /**
     * Display the supplier payables dashboard: unpaid balances per supplier.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('stock/supplier-payables', $this->baseProps($request));
    }

    /**
     * Export the supplier payables list as a CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $suppliers = $this->payableSuppliers($request);

        return $this->csvResponse('dettes-fournisseurs.csv', function ($write) use ($suppliers): void {
            $write(['Fournisseur', 'Téléphone', 'Commandes', 'Total dû', 'Encaissé', 'Solde impayé', 'Dont en retard']);

            foreach ($suppliers as $supplier) {
                $write([
                    $supplier['name'],
                    $supplier['phone'] ?? '',
                    (int) $supplier['orders_count'],
                    number_format((float) $supplier['total_due'], 2, ',', ' '),
                    number_format((float) $supplier['total_paid'], 2, ',', ' '),
                    number_format((float) $supplier['balance'], 2, ',', ' '),
                    number_format((float) $supplier['overdue_balance'], 2, ',', ' '),
                ]);
            }

            $write([]);
            $write([
                'TOTAL',
                '',
                (int) $suppliers->sum('orders_count'),
                number_format((float) $suppliers->sum('total_due'), 2, ',', ' '),
                number_format((float) $suppliers->sum('total_paid'), 2, ',', ' '),
                number_format((float) $suppliers->sum('balance'), 2, ',', ' '),
                number_format((float) $suppliers->sum('overdue_balance'), 2, ',', ' '),
            ]);
        });
    }

    /**
     * Display the details of one supplier (orders, payments).
     */
    public function supplier(Request $request, Supplier $supplier): Response
    {
        $supplier->load([
            'purchaseOrders' => fn ($query) => $query
                ->with(['items.product:id,name,sku,unit,purchase_price', 'payments.user:id,name'])
                ->latest(),
        ]);

        $detail = [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'balance' => $supplier->outstandingBalance(),
            'overdue_balance' => $supplier->overdueBalance(),
            'orders' => $supplier->purchaseOrders->map(fn (PurchaseOrder $order): array => $this->presentOrder($order))->values(),
        ];

        return Inertia::render('stock/supplier-payables', [
            ...$this->baseProps($request),
            'supplier_detail' => $detail,
        ]);
    }

    /**
     * Record a payment on a purchase order.
     */
    public function storePayment(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $amount = (float) $validated['amount'];

        $order = DB::transaction(function () use ($validated, $amount, $request): PurchaseOrder {
            // Lock the order row to prevent concurrent payments from exceeding the balance.
            $order = PurchaseOrder::query()
                ->lockForUpdate()
                ->with(['items.product'])
                ->findOrFail((int) $validated['purchase_order_id']);

            $balance = $order->balance();

            if ($amount > $balance + 0.01) {
                abort(422, "Le montant dépasse le solde restant de {$balance} FCFA.");
            }

            SupplierPayment::create([
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'user_id' => $request->user()->id,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'mobile_phone' => $validated['mobile_phone'] ?? null,
                'mobile_reference' => $validated['mobile_reference'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'paid_at' => now(),
            ]);

            // Recalculate the total paid amount from all payments.
            $totalPaid = $order->payments()->sum('amount');
            $order->update(['paid_amount' => round((float) $totalPaid, 2)]);

            return $order;
        });

        // Dismiss overdue alerts if the order is now fully paid.
        $order->refresh();
        if ($order->balance() <= 0.01) {
            app(StockAlertService::class)->dismissSupplierOverdueAlerts($order);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paiement fournisseur enregistré avec succès.']);

        return back();
    }

    /**
     * Stream the payment receipt as a PDF.
     */
    public function receiptPdf(SupplierPayment $payment): \Symfony\Component\HttpFoundation\Response
    {
        $payment->load(['purchaseOrder.supplier', 'user']);
        $order = $payment->purchaseOrder;

        return Pdf::loadView('pdf.supplier-receipt', [
            'payment' => $payment,
            'order' => $order,
            'supplier' => $order->supplier,
            'balance' => round($order->balance(), 2),
        ])->stream("recu-fournisseur-{$payment->id}.pdf");
    }

    /**
     * Build the shared props of the supplier payables page.
     *
     * @return array<string, mixed>
     */
    private function baseProps(Request $request): array
    {
        $suppliers = $this->payableSuppliers($request);

        $stats = [
            'total_outstanding' => round((float) $suppliers->sum('balance'), 2),
            'overdue_total' => round((float) $suppliers->sum('overdue_balance'), 2),
            'suppliers_with_balance' => $suppliers->filter(fn (array $supplier): bool => $supplier['balance'] > 0.01)->count(),
            'overdue_orders' => (int) $suppliers->sum('overdue_orders'),
        ];

        $page = max((int) \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage(), 1);
        $perPage = 15;
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $suppliers->forPage($page, $perPage)->values(),
            $suppliers->count(),
            $perPage,
            $page,
            [
                'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ],
        );

        return [
            'suppliers' => $paginator,
            'stats' => $stats,
            'filters' => [
                'status' => $request->input('status'),
                'search' => trim((string) $request->string('search')),
            ],
        ];
    }

    /**
     * Load suppliers with purchase order activity and present their aggregates.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function payableSuppliers(Request $request): \Illuminate\Support\Collection
    {
        $status = $request->input('status');
        $search = trim((string) $request->string('search'));

        return Supplier::query()
            ->with([
                'purchaseOrders' => fn ($query) => $query
                    ->with(['items.product:id,name,sku,unit,purchase_price', 'payments.user:id,name']),
            ])
            ->whereHas('purchaseOrders')
            ->get()
            ->map(fn (Supplier $supplier): array => $this->presentSupplier($supplier))
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (array $supplier): bool => str_contains(mb_strtolower($supplier['name']), mb_strtolower($search))
                    || str_contains(mb_strtolower((string) $supplier['phone']), mb_strtolower($search)),
            ))
            ->when($status === 'outstanding', fn ($rows) => $rows->filter(fn (array $supplier): bool => $supplier['balance'] > 0.01))
            ->when($status === 'overdue', fn ($rows) => $rows->filter(fn (array $supplier): bool => $supplier['overdue_balance'] > 0.01))
            ->when($status === 'settled', fn ($rows) => $rows->filter(fn (array $supplier): bool => $supplier['balance'] <= 0.01))
            ->sortBy('name')
            ->values();
    }

    /**
     * Build a human-readable representation of a supplier with its aggregates.
     *
     * @return array<string, mixed>
     */
    private function presentSupplier(Supplier $supplier): array
    {
        $orders = $supplier->purchaseOrders;

        $totalDue = round((float) $orders->sum(
            fn (PurchaseOrder $order): float => $order->receivedAmountTtc(),
        ), 2);
        $totalPaid = round((float) $orders->sum('paid_amount'), 2);
        $balance = round((float) $orders->sum(
            fn (PurchaseOrder $order): float => max($order->balance(), 0),
        ), 2);
        $overdueBalance = round((float) $orders->sum(
            fn (PurchaseOrder $order): float => $order->isOverdue() ? $order->balance() : 0.0,
        ), 2);

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'orders_count' => $orders->count(),
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'overdue_balance' => $overdueBalance,
            'overdue_orders' => $orders->filter(fn (PurchaseOrder $order): bool => $order->isOverdue())->count(),
        ];
    }

    /**
     * Build a human-readable representation of a purchase order.
     *
     * @return array<string, mixed>
     */
    private function presentOrder(PurchaseOrder $order): array
    {
        $amountHt = $order->amountHt();
        $taxAmount = $order->taxAmount($amountHt);
        $amountTtc = round($amountHt + $taxAmount, 2);

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status,
            'created_at' => $order->created_at,
            'due_at' => $order->due_at,
            'total' => $amountTtc,
            'paid_amount' => (float) $order->paid_amount,
            'balance' => $order->balance(),
            'payment_status' => $order->paymentStatus(),
            'payment_label' => $order->paymentStatusLabel(),
            'is_overdue' => $order->isOverdue(),
            'items' => $order->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'received_quantity' => (int) $item->received_quantity,
                'unit_price' => (float) $item->product->purchase_price,
                'product' => $item->product,
            ]),
            'payments' => $order->payments->map(fn (SupplierPayment $payment): array => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'payment_label' => SupplierPayment::PAYMENT_METHODS[$payment->payment_method] ?? $payment->payment_method,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'paid_at' => $payment->paid_at,
                'user' => $payment->user,
            ])->values(),
        ];
    }
}
