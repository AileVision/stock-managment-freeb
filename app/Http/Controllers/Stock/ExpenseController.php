<?php

namespace App\Http\Controllers\Stock;

use App\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    use ExportsCsv;

    /**
     * Display the paginated expenses list.
     */
    public function index(Request $request): Response
    {
        $expenses = Expense::query()
            ->with(['user:id,name'])
            ->when($request->date('from'), fn ($query, $from) => $query->whereDate('expense_date', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->whereDate('expense_date', '<=', $to))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->input('category')))
            ->when($request->filled('search'), fn ($query) => $query
                ->where('reference', 'like', '%'.$request->input('search').'%')
                ->orWhere('description', 'like', '%'.$request->input('search').'%')
            )
            ->latest('expense_date')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Expense $expense): array => $this->presentExpense($expense));

        $stats = $this->stats($request);

        return Inertia::render('stock/expenses', [
            'expenses' => $expenses,
            'stats' => $stats,
            'categories' => Expense::CATEGORIES,
            'filters' => [
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
                'category' => $request->input('category'),
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    /**
     * Store a new expense.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $expense = Expense::create([
            'user_id' => $request->user()->id,
            'reference' => $this->nextReference(),
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'expense_date' => $validated['expense_date'],
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dépense enregistrée avec succès.']);

        return back();
    }

    /**
     * Delete an expense.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dépense supprimée.']);

        return back();
    }

    /**
     * Export expenses as CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $expenses = Expense::query()
            ->with(['user:id,name'])
            ->when($request->date('from'), fn ($query, $from) => $query->whereDate('expense_date', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->whereDate('expense_date', '<=', $to))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->input('category')))
            ->latest('expense_date')
            ->get();

        return $this->csvResponse('depenses.csv', function ($write) use ($expenses) {
            $write(['Référence', 'Date', 'Catégorie', 'Description', 'Montant', 'Mode de paiement', 'Enregistré par']);

            foreach ($expenses as $expense) {
                $write([
                    $expense->reference,
                    $expense->expense_date->format('d/m/Y'),
                    Expense::categoryLabel($expense->category),
                    $expense->description ?? '',
                    number_format((float) $expense->amount, 2, ',', ' '),
                    Expense::PAYMENT_METHODS[$expense->payment_method] ?? $expense->payment_method,
                    $expense->user?->name ?? '',
                ]);
            }
        });
    }

    /**
     * Compute the expense stats for the current period.
     */
    private function stats(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfDay();

        $query = Expense::query()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to);

        $total = round((float) (clone $query)->sum('amount'), 2);

        $byCategory = (clone $query)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();

        return [
            'total' => $total,
            'count' => (clone $query)->count(),
            'by_category' => $byCategory,
        ];
    }

    /**
     * Build a human-readable representation of an expense for Inertia.
     *
     * @return array<string, mixed>
     */
    private function presentExpense(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'reference' => $expense->reference,
            'category' => $expense->category,
            'category_label' => Expense::categoryLabel($expense->category),
            'amount' => (float) $expense->amount,
            'payment_method' => $expense->payment_method,
            'payment_label' => Expense::PAYMENT_METHODS[$expense->payment_method] ?? $expense->payment_method,
            'expense_date' => $expense->expense_date->toDateString(),
            'description' => $expense->description,
            'notes' => $expense->notes,
            'created_at' => $expense->created_at,
            'user' => $expense->user,
        ];
    }

    /**
     * Generate a unique expense reference, e.g. DEP-20260823-0001.
     */
    private function nextReference(): string
    {
        $today = now()->format('Ymd');
        $prefix = "DEP-{$today}-";

        $lastToday = Expense::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $seq = 1;

        if ($lastToday !== null) {
            $seq = (int) substr($lastToday, -4) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
