<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashSessionRequest;
use App\Http\Requests\OpenCashSessionRequest;
use App\Models\CashSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashSessionController extends Controller
{
    /**
     * Display the cash register sessions.
     */
    public function index(Request $request): Response
    {
        $sessions = CashSession::query()
            ->with('user:id,name')
            ->withCount(['sales as completed_sales_count' => fn ($query) => $query->where('status', 'completed')])
            ->withSum(
                ['sales as cash_total' => fn ($query) => $query->where('payment_method', 'cash')->where('status', 'completed')],
                'total',
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('stock/cash-sessions', [
            'sessions' => $sessions->through(fn (CashSession $session): array => $this->presentSession($session)),
        ]);
    }

    /**
     * Open a new cash register session for the current user.
     */
    public function store(OpenCashSessionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        CashSession::create([
            'user_id' => $request->user()->id,
            'opened_at' => now(),
            'opening_cash' => $validated['opening_cash'],
            'notes' => $validated['notes'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Session de caisse ouverte avec succès.']);

        return back();
    }

    /**
     * Close an open session: expected vs counted cash difference.
     */
    public function close(CloseCashSessionRequest $request, CashSession $cashSession): RedirectResponse
    {
        if (! $cashSession->isOpen()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cette session est déjà clôturée.']);

            return back();
        }

        $validated = $request->validated();

        // Fonds attendu = fonds d'ouverture + encaissements espèces validés,
        // moins les ventes espèces annulées (l'argent a été rendu au client).
        $expectedCash = (float) $cashSession->opening_cash
            + (float) $cashSession->sales()
                ->where('payment_method', 'cash')
                ->where('status', 'completed')
                ->sum('total')
            - (float) $cashSession->sales()
                ->where('payment_method', 'cash')
                ->where('status', 'cancelled')
                ->sum('total');

        $countedCash = (float) $validated['counted_cash'];

        $cashSession->update([
            'closed_at' => now(),
            'expected_cash' => round($expectedCash, 2),
            'counted_cash' => round($countedCash, 2),
            'difference' => round($countedCash - $expectedCash, 2),
            'notes' => $validated['notes'] ?? $cashSession->notes,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Session de caisse clôturée.']);

        return back();
    }

    /**
     * Build a human-readable representation of a session for Inertia.
     *
     * @return array<string, mixed>
     */
    private function presentSession(CashSession $session): array
    {
        return [
            'id' => $session->id,
            'opened_at' => $session->opened_at,
            'closed_at' => $session->closed_at,
            'opening_cash' => (float) $session->opening_cash,
            'expected_cash' => $session->expected_cash !== null ? (float) $session->expected_cash : null,
            'counted_cash' => $session->counted_cash !== null ? (float) $session->counted_cash : null,
            'difference' => $session->difference !== null ? (float) $session->difference : null,
            'cash_total' => round((float) ($session->cash_total ?? 0), 2),
            'sales_count' => $session->completed_sales_count ?? 0,
            'is_open' => $session->isOpen(),
            'notes' => $session->notes,
            'user' => $session->user,
        ];
    }
}
