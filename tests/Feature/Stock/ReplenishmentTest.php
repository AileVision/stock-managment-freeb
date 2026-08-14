<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ReplenishmentTest extends TestCase
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

    public function test_guests_are_redirected_to_login_for_replenishment(): void
    {
        $this->get(route('stock.replenishment.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_replenishment(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.replenishment.index'))
            ->assertForbidden();
    }

    public function test_replenishment_lists_only_products_below_threshold_with_suggested_quantity(): void
    {
        $user = $this->userWithPermissions(['replenishment.view']);

        $this->product(['name' => 'En rupture', 'sku' => 'RUPT-001', 'stock_quantity' => 0, 'min_stock' => 10]);
        $this->product(['name' => 'Stock bas', 'sku' => 'BAS-001', 'stock_quantity' => 4, 'min_stock' => 10]);
        $this->product(['name' => 'Stock ok', 'sku' => 'OK-001', 'stock_quantity' => 30, 'min_stock' => 10]);

        $this->actingAs($user)
            ->get(route('stock.replenishment.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/replenishment')
                ->has('products', 2)
                ->where('products.0.name', 'En rupture')
                ->where('products.0.suggested_quantity', 20)
                ->where('products.1.name', 'Stock bas')
                ->where('products.1.suggested_quantity', 16));
    }

    public function test_user_can_receive_a_purchase_order(): void
    {
        $user = $this->userWithPermissions(['replenishment.create']);
        $supplier = Supplier::create(['name' => 'AgroFresh Sénégal']);
        $rice = $this->product(['name' => 'Riz', 'sku' => 'RIZ-RECEP-001', 'stock_quantity' => 4, 'min_stock' => 10]);
        $oil = $this->product(['name' => 'Huile', 'sku' => 'HUI-RECEP-001', 'stock_quantity' => 2, 'min_stock' => 8]);

        $this->actingAs($user)
            ->post(route('stock.replenishment.store'), [
                'supplier_id' => $supplier->id,
                'reference' => 'BL-2026-300',
                'items' => [
                    ['product_id' => $rice->id, 'quantity' => 16],
                    ['product_id' => $oil->id, 'quantity' => 10],
                ],
            ])
            ->assertRedirect(route('stock.replenishment.index'));

        $this->assertSame(20, $rice->fresh()->stock_quantity);
        $this->assertSame(12, $oil->fresh()->stock_quantity);

        $order = PurchaseOrder::where('reference', 'BL-2026-300')->firstOrFail();
        $this->assertSame($supplier->id, $order->supplier_id);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(2, $order->items()->count());

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $rice->id,
            'type' => 'in',
            'quantity' => 16,
            'reference' => 'BL-2026-300',
            'reason' => 'Réception commande fournisseur',
        ]);
    }

    public function test_reception_requires_at_least_one_item(): void
    {
        $user = $this->userWithPermissions(['replenishment.create']);

        $this->actingAs($user)
            ->post(route('stock.replenishment.store'), [
                'items' => [],
            ])
            ->assertSessionHasErrors('items');
    }

    public function test_reception_quantities_must_be_positive(): void
    {
        $user = $this->userWithPermissions(['replenishment.create']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.replenishment.store'), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 0],
                ],
            ])
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_user_without_create_permission_cannot_receive(): void
    {
        $user = $this->userWithPermissions(['replenishment.view']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.replenishment.store'), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(0, PurchaseOrder::count());
    }
}
