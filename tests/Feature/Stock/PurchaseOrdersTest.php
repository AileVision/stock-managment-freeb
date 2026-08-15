<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class PurchaseOrdersTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();

        // La migration d'entrepôts insère un entrepôt par défaut (code MAIN) :
        // on le retire pour éviter les collisions de code avec les tests.
        Warehouse::query()->delete();
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

    private function order(int $userId, array $attributes = []): PurchaseOrder
    {
        return PurchaseOrder::create([
            'supplier_id' => null,
            'reference' => 'C-20260814-'.str_pad((string) mt_rand(100, 999), 4, '0', STR_PAD_LEFT),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'user_id' => $userId,
            ...$attributes,
        ]);
    }

    public function test_guests_are_redirected_to_login_for_purchase_orders(): void
    {
        $this->get(route('stock.purchase-orders.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_purchase_orders(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.purchase-orders.index'))
            ->assertForbidden();
    }

    public function test_user_can_create_a_draft_purchase_order(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.view', 'purchase_orders.create']);
        $supplier = Supplier::create(['name' => 'AgroFresh Sénégal']);
        $warehouse = Warehouse::create(['name' => 'Entrepôt principal', 'code' => 'MAIN', 'is_default' => true]);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'reference' => 'CMD-2026-100',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 12],
                ],
            ])
            ->assertRedirect(route('stock.purchase-orders.index'));

        $order = PurchaseOrder::where('reference', 'CMD-2026-100')->firstOrFail();

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $order->status);
        $this->assertSame($supplier->id, $order->supplier_id);
        $this->assertSame($warehouse->id, $order->warehouse_id);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(1, $order->items()->count());

        // Une commande en brouillon ne touche pas encore le stock.
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_purchase_order_requires_at_least_one_item(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.create']);

        $this->actingAs($user)
            ->post(route('stock.purchase-orders.store'), [
                'items' => [],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_user_can_mark_a_draft_order_as_ordered(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.view', 'purchase_orders.update']);
        $order = $this->order($user->id);

        $this->withoutExceptionHandling()->actingAs($user)
            ->post(route('stock.purchase-orders.order', $order))
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $order->fresh()->status);
    }

    public function test_only_draft_orders_can_be_marked_as_ordered(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.update']);
        $order = $this->order($user->id, ['status' => PurchaseOrder::STATUS_RECEIVED]);

        $this->actingAs($user)
            ->post(route('stock.purchase-orders.order', $order))
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $order->fresh()->status);
    }

    public function test_user_can_receive_an_order_into_a_warehouse(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.view', 'purchase_orders.receive']);
        $warehouse = Warehouse::create(['name' => 'Entrepôt principal', 'code' => 'MAIN', 'is_default' => true]);
        $product = $this->product(['stock_quantity' => 4]);

        $order = $this->order($user->id, ['status' => PurchaseOrder::STATUS_ORDERED, 'warehouse_id' => $warehouse->id]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $this->actingAs($user)
            ->post(route('stock.purchase-orders.receive', $order), [
                'items' => [
                    ['id' => $item->id, 'received_quantity' => 10],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $order->fresh()->status);
        $this->assertSame(10, $item->fresh()->received_quantity);
        $this->assertSame(14, $product->fresh()->stock_quantity);
        // Le stock initial du produit était déjà dans cet entrepôt (défaut).
        $this->assertSame(14, $product->stockAt($warehouse->id));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 10,
            'warehouse_id' => $warehouse->id,
            'reference' => $order->reference,
            'reason' => "Réception commande {$order->reference}",
        ]);
    }

    public function test_partial_reception_keeps_the_order_partial(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.receive']);
        $warehouse = Warehouse::create(['name' => 'Entrepôt principal', 'code' => 'MAIN', 'is_default' => true]);
        $product = $this->product();

        $order = $this->order($user->id, ['status' => PurchaseOrder::STATUS_ORDERED, 'warehouse_id' => $warehouse->id]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $this->actingAs($user)
            ->post(route('stock.purchase-orders.receive', $order), [
                'items' => [
                    ['id' => $item->id, 'received_quantity' => 4],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_PARTIAL, $order->fresh()->status);
        $this->assertSame(4, $item->fresh()->received_quantity);
        $this->assertSame(14, $product->fresh()->stock_quantity);
    }

    public function test_received_quantity_cannot_exceed_the_remaining(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.receive']);
        $product = $this->product();

        $order = $this->order($user->id, ['status' => PurchaseOrder::STATUS_ORDERED]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $this->actingAs($user)
            ->post(route('stock.purchase-orders.receive', $order), [
                'items' => [
                    ['id' => $item->id, 'received_quantity' => 15],
                ],
            ])
            ->assertSessionHasErrors('items.0.received_quantity');

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_draft_orders_can_be_deleted(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.view', 'purchase_orders.delete']);
        $order = $this->order($user->id);

        $this->actingAs($user)
            ->delete(route('stock.purchase-orders.destroy', $order))
            ->assertRedirect(route('stock.purchase-orders.index'));

        $this->assertDatabaseMissing('purchase_orders', ['id' => $order->id]);
    }

    public function test_received_orders_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.delete']);
        $order = $this->order($user->id, ['status' => PurchaseOrder::STATUS_RECEIVED]);

        $this->actingAs($user)
            ->delete(route('stock.purchase-orders.destroy', $order))
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
    }

    public function test_purchase_orders_index_exposes_progress(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.view']);
        $product = $this->product();

        $order = $this->order($user->id, ['status' => PurchaseOrder::STATUS_PARTIAL]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 4,
        ]);

        $this->actingAs($user)
            ->get(route('stock.purchase-orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/purchase-orders')
                ->has('orders.data', 1)
                ->where('orders.data.0.status', 'partial')
                ->where('orders.data.0.total_quantity', 10)
                ->where('orders.data.0.received_quantity', 4)
                ->where('orders.data.0.progress', 40)
                ->where('orders.data.0.items.0.remaining', 6));
    }
}
