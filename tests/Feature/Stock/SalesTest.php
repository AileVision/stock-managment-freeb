<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class SalesTest extends TestCase
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

    public function test_guests_are_redirected_to_login_for_sales(): void
    {
        $this->get(route('stock.sales.index'))->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_to_login_for_pos(): void
    {
        $this->get(route('stock.sales.pos'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_sales(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.sales.index'))
            ->assertForbidden();
    }

    public function test_users_without_permission_cannot_view_pos(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.sales.pos'))
            ->assertForbidden();
    }

    public function test_pos_lists_products_with_prices_and_stock(): void
    {
        $user = $this->userWithPermissions(['sales.view']);
        $product = $this->product(['name' => 'Riz', 'sku' => 'RIZ-POS-001', 'barcode' => '6111000012345']);

        $this->actingAs($user)
            ->get(route('stock.sales.pos'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/pos')
                ->has('products', 1)
                ->where('products.0.id', $product->id)
                ->where('products.0.name', 'Riz')
                ->where('products.0.barcode', '6111000012345')
                ->where('products.0.sale_price', '150.00')
                ->where('products.0.stock_quantity', 10));
    }

    public function test_user_can_record_a_sale_and_stock_is_decreased(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $rice = $this->product(['name' => 'Riz', 'sku' => 'RIZ-VENTE-001', 'sale_price' => 150, 'stock_quantity' => 10]);
        $oil = $this->product(['name' => 'Huile', 'sku' => 'HUI-VENTE-001', 'sale_price' => 500, 'stock_quantity' => 4]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [
                    ['product_id' => $rice->id, 'quantity' => 2],
                    ['product_id' => $oil->id, 'quantity' => 1],
                ],
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('stock.sales.pos'));

        $this->assertSame(8, $rice->fresh()->stock_quantity);
        $this->assertSame(3, $oil->fresh()->stock_quantity);

        $sale = Sale::firstOrFail();

        $this->assertSame('completed', $sale->status);
        $this->assertSame(800, (int) $sale->total);
        $this->assertStringStartsWith('V-', $sale->reference);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $rice->id,
            'quantity' => 2,
            'unit_price' => 150,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $rice->id,
            'type' => 'out',
            'quantity' => -2,
            'reason' => 'Vente en caisse',
            'reference' => $sale->reference,
            'user_id' => $user->id,
        ]);
    }

    public function test_sale_requires_at_least_one_item(): void
    {
        $user = $this->userWithPermissions(['sales.create']);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [],
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('items');
    }

    public function test_sale_requires_a_valid_payment_method(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'bitcoin',
            ])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_sale_is_rejected_when_stock_is_insufficient(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $product = $this->product(['name' => 'Riz', 'sku' => 'RIZ-INS-001', 'stock_quantity' => 3]);

        $response = $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
                'payment_method' => 'cash',
            ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_user_without_create_permission_cannot_record_a_sale(): void
    {
        $user = $this->userWithPermissions(['sales.view']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_cancelling_a_sale_restores_stock(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create', 'sales.delete']);
        $product = $this->product(['sale_price' => 150, 'stock_quantity' => 10]);

        $this->actingAs($user)->post(route('stock.sales.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
            'payment_method' => 'card',
        ]);

        $sale = Sale::firstOrFail();
        $this->assertSame(6, $product->fresh()->stock_quantity);

        $this->actingAs($user)
            ->delete(route('stock.sales.destroy', $sale))
            ->assertRedirect();

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 4,
            'reason' => "Annulation vente {$sale->reference}",
            'user_id' => $user->id,
        ]);
    }

    public function test_user_without_delete_permission_cannot_cancel_a_sale(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $product = $this->product(['stock_quantity' => 10]);

        $this->actingAs($user)->post(route('stock.sales.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ]);

        $sale = Sale::firstOrFail();

        $this->actingAs($user)
            ->delete(route('stock.sales.destroy', $sale))
            ->assertForbidden();

        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_cancelled_sale_cannot_be_cancelled_twice(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create', 'sales.delete']);
        $product = $this->product(['stock_quantity' => 10]);

        $this->actingAs($user)->post(route('stock.sales.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ]);

        $sale = Sale::firstOrFail();

        // Première annulation : le stock est restitué.
        $this->actingAs($user)
            ->delete(route('stock.sales.destroy', $sale))
            ->assertRedirect();

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);

        // Seconde tentative : la vente est déjà annulée, pas de double restitution.
        $this->actingAs($user)
            ->delete(route('stock.sales.destroy', $sale))
            ->assertRedirect();

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_sales_can_be_filtered_by_status_and_payment_method(): void
    {
        $user = $this->userWithPermissions(['sales.view']);
        $product = $this->product(['stock_quantity' => 10]);

        $completed = Sale::create([
            'reference' => 'V-20260814-0001',
            'user_id' => $user->id,
            'payment_method' => 'cash',
            'total' => 150,
            'status' => 'completed',
        ]);
        $completed->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 150,
        ]);

        $cancelled = Sale::create([
            'reference' => 'V-20260814-0002',
            'user_id' => $user->id,
            'payment_method' => 'card',
            'total' => 300,
            'status' => 'cancelled',
        ]);
        $cancelled->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 150,
        ]);

        $this->actingAs($user)
            ->get(route('stock.sales.index', ['status' => 'cancelled', 'payment_method' => 'card']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/sales')
                ->has('sales.data', 1)
                ->where('sales.data.0.reference', 'V-20260814-0002')
                ->where('sales.data.0.status', 'cancelled'));
    }
}
