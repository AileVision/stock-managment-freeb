<?php

namespace Tests\Feature\Stock;

use App\Models\CashSession;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class CashSessionTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
    }

    private function product(array $attributes = []): Product
    {
        return Product::create([
            'name' => 'Produit test',
            'sku' => 'TEST-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'unit' => 'pcs',
            'purchase_price' => 100,
            'sale_price' => 150,
            'stock_quantity' => 10,
            ...$attributes,
        ]);
    }

    private function cashSale(CashSession $session, int $userId, int $total, string $payment = 'cash', string $status = 'completed'): Sale
    {
        return Sale::create([
            'reference' => 'V-'.strtoupper(substr(md5((string) mt_rand()), 0, 8)),
            'session_id' => $session->id,
            'user_id' => $userId,
            'payment_method' => $payment,
            'total' => $total,
            'status' => $status,
        ]);
    }

    public function test_guests_are_redirected_to_login_for_sessions(): void
    {
        $this->get(route('stock.sessions.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_sessions(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.sessions.index'))
            ->assertForbidden();
    }

    public function test_user_can_open_a_cash_session(): void
    {
        $user = $this->userWithPermissions(['sessions.view', 'sessions.open']);

        $this->actingAs($user)
            ->post(route('stock.sessions.store'), ['opening_cash' => 5000])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_sessions', [
            'user_id' => $user->id,
            'opening_cash' => 5000,
        ]);

        $session = CashSession::firstOrFail();
        $this->assertTrue($session->isOpen());
    }

    public function test_user_cannot_open_two_sessions_at_once(): void
    {
        $user = $this->userWithPermissions(['sessions.view', 'sessions.open']);

        CashSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 1000,
        ]);

        $this->actingAs($user)
            ->post(route('stock.sessions.store'), ['opening_cash' => 2000])
            ->assertSessionHasErrors('opening_cash');

        $this->assertDatabaseCount('cash_sessions', 1);
    }

    public function test_user_without_open_permission_cannot_open_a_session(): void
    {
        $user = $this->userWithPermissions(['sessions.view']);

        $this->actingAs($user)
            ->post(route('stock.sessions.store'), ['opening_cash' => 1000])
            ->assertForbidden();

        $this->assertDatabaseCount('cash_sessions', 0);
    }

    public function test_closing_a_session_computes_expected_cash_and_difference(): void
    {
        $user = $this->userWithPermissions(['sessions.view', 'sessions.open', 'sessions.close']);

        $session = CashSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 10000,
        ]);

        $this->cashSale($session, $user->id, 3000);
        $this->cashSale($session, $user->id, 1500);
        // Une vente carte ne compte pas dans la caisse espèces.
        $this->cashSale($session, $user->id, 9999, 'card');

        $this->actingAs($user)
            ->post(route('stock.sessions.close', $session), ['counted_cash' => 14800])
            ->assertRedirect();

        $session->refresh();

        $this->assertNotNull($session->closed_at);
        $this->assertSame(14500.0, (float) $session->expected_cash);
        $this->assertSame(14800.0, (float) $session->counted_cash);
        $this->assertSame(300.0, (float) $session->difference);
    }

    public function test_closing_a_session_subtracts_cancelled_cash_sales(): void
    {
        $user = $this->userWithPermissions(['sessions.view', 'sessions.open', 'sessions.close']);

        $session = CashSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 5000,
        ]);

        $this->cashSale($session, $user->id, 3000);
        // Vente espèces annulée : l'argent a été rendu.
        $this->cashSale($session, $user->id, 2000, 'cash', 'cancelled');

        $this->actingAs($user)
            ->post(route('stock.sessions.close', $session), ['counted_cash' => 6000])
            ->assertRedirect();

        $this->assertSame(6000.0, (float) $session->fresh()->expected_cash);
    }

    public function test_a_closed_session_cannot_be_closed_twice(): void
    {
        $user = $this->userWithPermissions(['sessions.view', 'sessions.open', 'sessions.close']);

        $session = CashSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'closed_at' => now(),
            'opening_cash' => 1000,
            'expected_cash' => 1000,
            'counted_cash' => 1000,
            'difference' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('stock.sessions.close', $session), ['counted_cash' => 5000])
            ->assertRedirect();

        $this->assertSame(1000.0, (float) $session->fresh()->counted_cash);
    }

    public function test_pos_shows_the_open_session(): void
    {
        $user = $this->userWithPermissions(['sales.view']);

        $session = CashSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 1000,
        ]);
        $this->cashSale($session, $user->id, 2500);

        $this->actingAs($user)
            ->get(route('stock.sales.pos'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('stock/pos')
                ->where('open_session.id', $session->id)
                ->where('open_session.is_open', true)
                ->where('open_session.cash_total', 2500)
                ->where('open_session.sales_count', 1));
    }

    public function test_a_sale_can_be_recorded_within_a_session(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $product = $this->product(['stock_quantity' => 10]);

        $session = CashSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 1000,
        ]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'payment_method' => 'cash',
                'session_id' => $session->id,
            ])
            ->assertRedirect(route('stock.sales.pos'));

        $this->assertDatabaseHas('sales', [
            'session_id' => $session->id,
            'total' => 300,
        ]);
    }

    public function test_a_sale_cannot_be_recorded_in_a_closed_session(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $product = $this->product(['stock_quantity' => 10]);

        $session = CashSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'closed_at' => now(),
            'opening_cash' => 1000,
        ]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
                'session_id' => $session->id,
            ])
            ->assertSessionHasErrors('session_id');

        $this->assertDatabaseCount('sales', 0);
    }
}
