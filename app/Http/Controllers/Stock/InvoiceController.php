<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoiceController extends Controller
{
    /**
     * Display the paginated list of invoices.
     */
    public function index(Request $request): Response
    {
        $invoices = Invoice::query()
            ->with(['user:id,name', 'sale:id,reference,total,status'])
            ->when($request->date('from'), fn ($query, $from) => $query->whereDate('issued_at', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->whereDate('issued_at', '<=', $to))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->string('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => $this->presentInvoice($invoice));

        return Inertia::render('stock/invoices', [
            'invoices' => $invoices,
            // Ventes validées et non encore facturées, pour le dialogue « Facturer ».
            'uninvoiced_sales' => Sale::query()
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereDoesntHave('invoice')
                ->latest()
                ->limit(50)
                ->get(['id', 'reference', 'total', 'created_at', 'payment_method']),
            // Carnet d'adresses pour pré-remplir le client de la facture.
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'email']),
            'filters' => [
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    /**
     * Issue an invoice from a completed sale.
     */
    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Invoice::create([
            'reference' => $this->nextReference(),
            'sale_id' => $validated['sale_id'],
            'customer_id' => $validated['customer_id'] ?? null,
            'user_id' => $request->user()->id,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'] ?? null,
            'customer_address' => $validated['customer_address'] ?? null,
            'issued_at' => now()->toDateString(),
            'notes' => $validated['notes'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Facture émise avec succès.']);

        return back();
    }

    /**
     * Stream the invoice as a printable PDF.
     */
    public function pdf(Invoice $invoice): SymfonyResponse
    {
        $invoice->load('sale.items.product:id,name,sku,unit');

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait')
            ->stream("facture-{$invoice->reference}.pdf");
    }

    /**
     * Stream a single PDF containing all the filtered invoices.
     */
    public function exportPdf(Request $request): SymfonyResponse
    {
        $invoices = Invoice::query()
            ->with(['sale.items.product:id,name,sku,unit'])
            ->when($request->date('from'), fn ($query, $from) => $query->whereDate('issued_at', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->whereDate('issued_at', '<=', $to))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->string('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        return Pdf::loadView('pdf.invoices', ['invoices' => $invoices])
            ->setPaper('a4', 'portrait')
            ->stream('factures.pdf');
    }

    /**
     * Build a human-readable representation of an invoice for Inertia.
     *
     * @return array<string, mixed>
     */
    private function presentInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'reference' => $invoice->reference,
            'sale_id' => $invoice->sale_id,
            'sale_reference' => $invoice->sale?->reference,
            'customer_name' => $invoice->customer_name,
            'customer_email' => $invoice->customer_email,
            'customer_address' => $invoice->customer_address,
            'issued_at' => $invoice->issued_at,
            'total' => (float) ($invoice->sale->total ?? 0),
            'discount_percent' => (float) ($invoice->sale->discount_percent ?? 0),
            'notes' => $invoice->notes,
            'created_at' => $invoice->created_at,
            'user' => $invoice->user,
        ];
    }

    /**
     * Generate a unique invoice reference, e.g. F-20260814-0001.
     */
    private function nextReference(): string
    {
        $nextId = (int) DB::table('invoices')->lockForUpdate()->max('id') + 1;

        return 'F-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }
}
