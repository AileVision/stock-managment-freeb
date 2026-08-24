<?php

namespace App\Http\Controllers\Stock;

use App\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerPaymentRequest;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Services\StockAlertService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivablesController extends Controller
{
    use ExportsCsv;

    /**
     * Display the receivables dashboard: unpaid balances per customer, with
     * the overdue amounts highlighted for the relances.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('stock/receivables', $this->baseProps($request));
    }

    /**
     * Export the receivables list as a CSV for the accounting, honoring the
     * same filters as the page.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $customers = $this->receivableCustomers($request);

        return $this->csvResponse('creances.csv', function ($write) use ($customers): void {
            $write(['Client', 'Téléphone', 'E-mail', 'Ventes à crédit', 'Total dû', 'Encaissé', 'Solde impayé', 'Dont en retard', 'Avoirs disponibles']);

            foreach ($customers as $customer) {
                $write([
                    $customer['name'],
                    $customer['phone'] ?? '',
                    $customer['email'] ?? '',
                    (int) $customer['sales_count'],
                    number_format((float) $customer['total_due'], 2, ',', ' '),
                    number_format((float) $customer['total_paid'], 2, ',', ' '),
                    number_format((float) $customer['balance'], 2, ',', ' '),
                    number_format((float) $customer['overdue_balance'], 2, ',', ' '),
                    number_format((float) $customer['available_avoirs'], 2, ',', ' '),
                ]);
            }

            $write([]);
            $write([
                'TOTAL',
                '',
                '',
                (int) $customers->sum('sales_count'),
                number_format((float) $customers->sum('total_due'), 2, ',', ' '),
                number_format((float) $customers->sum('total_paid'), 2, ',', ' '),
                number_format((float) $customers->sum('balance'), 2, ',', ' '),
                number_format((float) $customers->sum('overdue_balance'), 2, ',', ' '),
                number_format((float) $customers->sum('available_avoirs'), 2, ',', ' '),
            ]);
        });
    }

    /**
     * Display the details of one customer (credit sales, payments, avoirs).
     */
    public function customer(Request $request, Customer $customer): Response
    {
        $customer->load([
            'sales' => fn ($query) => $query
                ->where('payment_method', 'credit')
                ->where('status', Sale::STATUS_COMPLETED)
                ->with(['items.product:id,name,sku,unit', 'payments.user:id,name', 'returns'])
                ->latest(),
            'creditNotes' => fn ($query) => $query->with('user:id,name')->latest('issued_at'),
        ]);

        $detail = [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'balance' => $customer->outstandingBalance(),
            'overdue_balance' => $customer->overdueBalance(),
            'available_avoirs' => $customer->availableAvoirs(),
            'sales' => $customer->sales->map(fn (Sale $sale): array => $this->presentSale($sale))->values(),
            'credit_notes' => $customer->creditNotes->map(fn (CreditNote $note): array => [
                'id' => $note->id,
                'reference' => $note->reference,
                'amount' => (float) $note->amount,
                'used_amount' => (float) $note->used_amount,
                'remaining' => $note->remaining(),
                'status' => $note->status,
                'issued_at' => $note->issued_at,
                'notes' => $note->notes,
                'user' => $note->user,
            ])->values(),
        ];

        return Inertia::render('stock/receivables', [
            ...$this->baseProps($request),
            'customer_detail' => $detail,
        ]);
    }

    /**
     * Record a payment (acompte or balance) on a credit sale.
     */
    public function storePayment(StoreCustomerPaymentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $sale = Sale::query()->with('customer')->findOrFail((int) $validated['sale_id']);
        $amount = (float) $validated['amount'];

        DB::transaction(function () use ($validated, $amount, $sale, $request): void {
            // Paiement par avoir : on consomme les avoirs du client.
            if ($validated['payment_method'] === 'avoir') {
                $sale->customer?->consumeAvoirs($amount, $sale, $request->user()->id);

                return;
            }

            $sale->payments()->create([
                'customer_id' => $sale->customer_id,
                'user_id' => $request->user()->id,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'mobile_phone' => $validated['mobile_phone'] ?? null,
                'mobile_reference' => $validated['mobile_reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'paid_at' => now(),
            ]);
        });

        // Dismiss overdue alerts if the sale is now fully paid.
        $sale->refresh();
        if ($sale->creditStatus() === Sale::CREDIT_PAID) {
            app(StockAlertService::class)->dismissReceivableOverdueAlerts($sale);
        }

        // L'identifiant du paiement est transmis au front pour imprimer le reçu.
        Inertia::flash('payment_receipt', (int) $sale->payments()->latest('id')->value('id'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Encaissement enregistré avec succès.']);

        return back();
    }

    /**
     * Stream the encaissement receipt (reçu) as a printable PDF.
     */
    public function receiptPdf(CustomerPayment $payment): SymfonyResponse
    {
        $payment->load(['sale.customer', 'user']);
        $sale = $payment->sale;

        return Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'sale' => $sale,
            'customer' => $sale->customer,
            'balance' => round((float) $sale->balance(), 2),
        ])->stream("recu-encaissement-{$payment->id}.pdf");
    }

    /**
     * Stream the customer statement (relevé de compte) as a PDF, used for the
     * relances of late payments.
     */
    public function relancePdf(Customer $customer): SymfonyResponse
    {
        $customer->load([
            'sales' => fn ($query) => $query
                ->where('payment_method', 'credit')
                ->where('status', Sale::STATUS_COMPLETED)
                ->with('payments')
                ->latest(),
        ]);

        $rows = $customer->sales
            ->map(fn (Sale $sale): array => [
                'reference' => $sale->reference,
                'created_at' => $sale->created_at,
                'due_at' => $sale->credit_due_at,
                'total' => (float) $sale->total,
                'paid' => $sale->paidAmount(),
                'balance' => $sale->balance(),
                'overdue' => $sale->isOverdue(),
                'days_late' => $sale->isOverdue() && $sale->credit_due_at !== null
                    ? (int) $sale->credit_due_at->diffInDays(today())
                    : 0,
            ])
            ->filter(fn (array $row): bool => $row['balance'] > 0.01)
            ->values();

        $balance = round((float) $rows->sum('balance'), 2);

        return Pdf::loadView('pdf.releve', [
            'customer' => $customer,
            'rows' => $rows,
            'balance' => $balance,
        ])->stream("releve-{$customer->name}.pdf");
    }

    /**
     * Build the shared props of the receivables page.
     *
     * @return array<string, mixed>
     */
    private function baseProps(Request $request): array
    {
        $customers = $this->receivableCustomers($request);

        $stats = [
            'total_outstanding' => round((float) $customers->sum('balance'), 2),
            'overdue_total' => round((float) $customers->sum('overdue_balance'), 2),
            'customers_with_balance' => $customers->filter(fn (array $customer): bool => $customer['balance'] > 0.01)->count(),
            'overdue_sales' => (int) $customers->sum('overdue_sales'),
        ];

        $page = max((int) LengthAwarePaginator::resolveCurrentPage(), 1);
        $perPage = 15;
        $paginator = new LengthAwarePaginator(
            $customers->forPage($page, $perPage)->values(),
            $customers->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ],
        );

        return [
            'customers' => $paginator,
            'stats' => $stats,
            'filters' => [
                'status' => $request->input('status'),
                'search' => trim((string) $request->string('search')),
            ],
        ];
    }

    /**
     * Load the customers with credit activity, honoring the status and search
     * filters, and present their receivables aggregates.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function receivableCustomers(Request $request): Collection
    {
        $status = $request->input('status');
        $search = trim((string) $request->string('search'));

        return Customer::query()
            ->with([
                'sales' => fn ($query) => $query
                    ->where('payment_method', 'credit')
                    ->where('status', Sale::STATUS_COMPLETED)
                    ->with(['payments', 'returns']),
                'creditNotes' => fn ($query) => $query->where('status', CreditNote::STATUS_ISSUED),
            ])
            ->whereHas('sales', fn ($query) => $query
                ->where('payment_method', 'credit')
                ->where('status', Sale::STATUS_COMPLETED))
            ->get()
            ->map(fn (Customer $customer): array => $this->presentCustomer($customer))
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (array $customer): bool => str_contains(mb_strtolower($customer['name']), mb_strtolower($search))
                    || str_contains(mb_strtolower((string) $customer['phone']), mb_strtolower($search)),
            ))
            ->when($status === 'outstanding', fn ($rows) => $rows->filter(fn (array $customer): bool => $customer['balance'] > 0.01))
            ->when($status === 'overdue', fn ($rows) => $rows->filter(fn (array $customer): bool => $customer['overdue_balance'] > 0.01))
            ->when($status === 'settled', fn ($rows) => $rows->filter(fn (array $customer): bool => $customer['balance'] <= 0.01))
            ->sortBy('name')
            ->values();
    }

    /**
     * Build a human-readable representation of a customer with its aggregates.
     *
     * @return array<string, mixed>
     */
    private function presentCustomer(Customer $customer): array
    {
        $sales = $customer->sales;

        $totalDue = round((float) $sales->sum('total'), 2);
        $totalPaid = round((float) $sales->sum(fn (Sale $sale): float => $sale->paidAmount()), 2);
        $balance = round((float) $sales->sum(fn (Sale $sale): float => max($sale->balance(), 0)), 2);
        $overdueBalance = round((float) $sales->sum(fn (Sale $sale): float => $sale->isOverdue() ? $sale->balance() : 0.0), 2);

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'sales_count' => $sales->count(),
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'overdue_balance' => $overdueBalance,
            'overdue_sales' => $sales->filter(fn (Sale $sale): bool => $sale->isOverdue())->count(),
            'available_avoirs' => round((float) $customer->creditNotes->sum(fn (CreditNote $note): float => $note->remaining()), 2),
        ];
    }

    /**
     * Build a human-readable representation of a credit sale for the detail.
     *
     * @return array<string, mixed>
     */
    private function presentSale(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'reference' => $sale->reference,
            'created_at' => $sale->created_at,
            'credit_due_at' => $sale->credit_due_at,
            'total' => (float) $sale->total,
            'paid_amount' => $sale->paidAmount(),
            'returned_value' => $sale->returnedValue(),
            'balance' => $sale->balance(),
            'credit_status' => $sale->creditStatus(),
            'credit_label' => $sale->creditStatusLabel(),
            'items' => $sale->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'returned_quantity' => $item->returned_quantity,
                'unit_price' => (float) $item->unit_price,
                'product' => $item->product,
            ]),
            'payments' => $sale->payments->map(fn (CustomerPayment $payment): array => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'payment_label' => CustomerPayment::PAYMENT_METHODS[$payment->payment_method] ?? $payment->payment_method,
                'mobile_phone' => $payment->mobile_phone,
                'mobile_reference' => $payment->mobile_reference,
                'notes' => $payment->notes,
                'paid_at' => $payment->paid_at,
                'user' => $payment->user,
            ])->values(),
            'returns' => $sale->returns->map(fn ($saleReturn): array => [
                'reference' => $saleReturn->reference,
                'total' => (float) $saleReturn->total,
                'applied_to_balance' => (float) $saleReturn->applied_to_balance,
                'refund_method' => $saleReturn->refund_method,
                'refund_amount' => (float) $saleReturn->refund_amount,
                'returned_at' => $saleReturn->returned_at,
            ])->values(),
        ];
    }
}
