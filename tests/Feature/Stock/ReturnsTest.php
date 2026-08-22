<?php

namespace Tests\Feature\Stock;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ReturnsTest extends TestCase
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

    private function customer(array $attributes = []): Customer
    {
        return Customer::create([
            'name' => 'Grossiste du Centre',
            ...$attributes,
        ]);
    }

    private function completedSale(Product $product, int $quantity = 4, array $attributes = []): Sale
    {
        $sale = Sale::create([
            'reference' => 'V-20260815-'.str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'user_id' => 1,
            'payment_method' => 'cash',
            'total' => (float) $product->sale_price * $quantity,
            'status' => Sale::STATUS_COMPLETED,
            ...$attributes,
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->sale_price,
        ]);

        return $sale;
    }

    public function test_guests_are_redirected_to_login_for_returns(): void
    {
        $this->get(route('stock.returns.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_returns(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.returns.index'))
            ->assertForbidden();
    }

    public function test_users_without_permission_cannot_create_a_return(): void
    {
        $user = $this->userWithPermissions(['returns.view']);
        $product = $this->product();
        $sale = $this->completedSale($product);

        $this->actingAs($user)
            ->post(route('stock.returns.store'), [
                'sale_id' => $sale->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'disposition' => 'refund',
                'refund_method' => 'cash',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('sale_returns', 0);
    }

    public function test_a_cash_refund_return_restocks_the_products(): void
    {
        $user = $this->userWithPermissions(['returns.view', 'returns.create']);
        $product = $this->product(['sale_price' => 150, 'stock_quantity' => 10]);
        $sale = $this->completedSale($product, 4);

        // La vente est créée directement en base : le stock reste à 10.
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->actingAs($user)
            ->post(route('stock.returns.store'), [
                'sale_id' => $sale->id,
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
                'disposition' => 'refund',
                'refund_method' => 'cash',
            ])
            ->assertRedirect(route('stock.returns.index'));

        // Le stock est réintégré (10 + 3).
        $this->assertSame(13, $product->fresh()->stock_quantity);

        $saleReturn = SaleReturn::firstOrFail();

        $this->assertStringStartsWith('R-', $saleReturn->reference);
        $this->assertSame(450.0, (float) $saleReturn->total);
        $this->assertSame('cash', $saleReturn->refund_method);
        $this->assertSame(450.0, (float) $saleReturn->refund_amount);
        $this->assertSame(0.0, (float) $saleReturn->applied_to_balance);

        $this->assertDatabaseHas('sale_return_items', [
            'sale_return_id' => $saleReturn->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 150,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 3,
            'reason' => "Retour client {$saleReturn->reference}",
        ]);

        // La quantité retournée de la vente d'origine est suivie.
        $this->assertSame(3, $sale->items()->first()->returned_quantity);
    }

    public function test_a_partial_return_is_allowed_but_over_return_is_rejected(): void
    {
        $user = $this->userWithPermissions(['returns.view', 'returns.create']);
        $product = $this->product();
        $sale = $this->completedSale($product, 4);

        // Premier retour partiel : 2 articles.
        $this->actingAs($user)
            ->post(route('stock.returns.store'), [
                'sale_id' => $sale->id,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'disposition' => 'refund',
                'refund_method' => 'cash',
            ])
            ->assertRedirect();

        // Seconde tentative : trop d'articles retournés (2 restants, 3 demandés).
        $this->actingAs($user)
            ->post(route('stock.returns.store'), [
                'sale_id' => $sale->id,
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
                'disposition' => 'refund',
                'refund_method' => 'cash',
            ])
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseCount('sale_returns', 1);
    }

    public function test_a_return_cannot_contain_products_not_in_the_sale(): void
    {
        $user = $this->userWithPermissions(['returns.create']);
        $product = $this->product();
        $other = $this->product(['name' => 'Autre produit']);
        $sale = $this->completedSale($product);

        $this->actingAs($user)
            ->post(route('stock.returns.store'), [
                'sale_id' => $sale->id,
                'items' => [['product_id' => $other->id, 'quantity' => 1]],
                'disposition' => 'refund',
                'refund_method' => 'cash',
            ])
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('sale_returns', 0);
    }

    public function test_an_avoir_is_issued_when_the_disposition_is_avoir(): void
    {
        $user = $this->userWithPermissions(['returns.view', 'returns.create']);
        $product = $this->product(['sale_price' => 150]);
        $sale = $this->completedSale($product, 2);

        $this->actingAs($user)
            ->post(route('stock.returns.store'), [
                'sale_id' => $sale->id,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'disposition' => 'avoir',
            ])
            ->assertRedirect(route('stock.returns.index'));

        $saleReturn = SaleReturn::firstOrFail();
        $this->assertSame('avoir', $saleReturn->refund_method);
        $this->assertSame(300.0, (float) $saleReturn->refund_amount);

        $this->assertDatabaseHas('credit_notes', [
            'sale_return_id' => $saleReturn->id,
            'amount' => 300,
            'status' => CreditNote::STATUS_ISSUED,
        ]);
    }

    public function test_a_return_reduces_the_balance_of_a_credit_sale(): void
    {
        $user = $this->userWithPermissions(['returns.view', 'returns.create']);
        $customer = $this->customer();
        $product = $this->product(['sale_price' => 150, 'stock_quantity' => 10]);
        $sale = $this->completedSale($product, 4, [
            'customer_id' => $customer->id,
            'payment_method' => 'credit',
            'credit_due_at' => now()->addDays(30)->toDateString(),
        ]);

        // La vente à crédit n'a pas d'acompte : solde = total = 600.
        $this->assertSame(600.0, $sale->fresh()->balance());

        $this->actingAs($user)
            ->post(route('stock.returns.store'), [
                'sale_id' => $sale->id,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'disposition' => 'refund',
                'refund_method' => 'cash',
            ])
            ->assertRedirect();

        $saleReturn = SaleReturn::firstOrFail();

        // La valeur du retour (300) réduit le solde impayé, rien à rembourser.
        $this->assertSame(300.0, (float) $saleReturn->applied_to_balance);
        $this->assertSame('none', $saleReturn->refund_method);
        $this->assertSame(0.0, (float) $saleReturn->refund_amount);

        // Solde = 600 - 300.
        $this->assertSame(300.0, $sale->fresh()->balance());
    }

    public function test_a_return_exceeding_the_balance_refunds_the_rest(): void
    {
        $user = $this->userWithPermissions(['returns.view', 'returns.create']);
        $customer = $this->customer();
        $product = $this->product(['sale_price' => 150, 'stock_quantity' => 10]);
        $sale = $this->completedSale($product, 4, [
            'customer_id' => $customer->id,
            'payment_method' => 'credit',
            'credit_due_at' => now()->addDays(30)->toDateString(),
        ]);

        // Un acompte de 500 a été payé : solde = 100.
        $sale->payments()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'amount' => 500,
            'payment_method' => 'cash',
        ]);

        $this->assertSame(100.0, $sale->fresh()->balance());

        $this->actingAs($user)
            ->post(route('stock.returns.store'), [
                'sale_id' => $sale->id,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'disposition' => 'refund',
                'refund_method' => 'cash',
            ])
            ->assertRedirect();

        $saleReturn = SaleReturn::firstOrFail();

        // 100 appliqué au solde, 200 remboursés en espèces.
        $this->assertSame(100.0, (float) $saleReturn->applied_to_balance);
        $this->assertSame('cash', $saleReturn->refund_method);
        $this->assertSame(200.0, (float) $saleReturn->refund_amount);

        $this->assertSame(0.0, $sale->fresh()->balance());
    }

    public function test_an_avoir_can_be_used_to_pay_a_sale_at_the_pos(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $customer = $this->customer();
        $product = $this->product(['sale_price' => 150, 'stock_quantity' => 10]);

        $note = CreditNote::create([
            'reference' => 'A-20260815-0001',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'amount' => 300,
            'status' => CreditNote::STATUS_ISSUED,
            'issued_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'payment_method' => 'avoir',
                'customer_id' => $customer->id,
            ])
            ->assertRedirect(route('stock.sales.pos'));

        $sale = Sale::firstOrFail();

        $this->assertSame('avoir', $sale->payment_method);
        $this->assertSame(300.0, $sale->paidAmount());
        $this->assertSame(0.0, $sale->balance());

        $this->assertSame(CreditNote::STATUS_USED, $note->fresh()->status);
        $this->assertSame(300.0, (float) $note->fresh()->used_amount);

        $this->assertDatabaseHas('customer_payments', [
            'sale_id' => $sale->id,
            'amount' => 300,
            'payment_method' => 'avoir',
        ]);
    }

    public function test_a_sale_cannot_be_paid_by_avoir_without_enough_avoirs(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $customer = $this->customer();
        $product = $this->product(['sale_price' => 150, 'stock_quantity' => 10]);

        CreditNote::create([
            'reference' => 'A-20260815-0001',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'amount' => 100,
            'status' => CreditNote::STATUS_ISSUED,
            'issued_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'payment_method' => 'avoir',
                'customer_id' => $customer->id,
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_returns_page_lists_returns_with_stats(): void
    {
        $user = $this->userWithPermissions(['returns.view']);
        $product = $this->product(['sale_price' => 150]);
        $sale = $this->completedSale($product, 4);

        $saleReturn = SaleReturn::create([
            'reference' => 'R-20260815-0001',
            'sale_id' => $sale->id,
            'user_id' => $user->id,
            'total' => 450,
            'refund_method' => 'cash',
            'refund_amount' => 450,
            'applied_to_balance' => 0,
            'returned_at' => now()->toDateString(),
        ]);
        $saleReturn->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 150,
        ]);

        $this->actingAs($user)
            ->get(route('stock.returns.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/returns')
                ->has('returns.data', 1)
                ->where('returns.data.0.reference', 'R-20260815-0001')
                ->where('returns.data.0.refund_method', 'cash')
                ->where('stats.returns_count', 1)
                ->where('stats.returns_value', 450));
    }
}
