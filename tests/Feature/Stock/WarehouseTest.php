<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();

        // La migration d'entrepôts insère un entrepôt par défaut : on le
        // retire pour que chaque test contrôle précisément l'état de départ.
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

    private function warehouse(array $attributes = []): Warehouse
    {
        return Warehouse::create([
            'name' => 'Entrepôt '.substr(md5((string) mt_rand()), 0, 6),
            'code' => 'WH-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            ...$attributes,
        ]);
    }

    public function test_guests_are_redirected_to_login_for_warehouses(): void
    {
        $this->get(route('stock.warehouses.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_warehouses(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.warehouses.index'))
            ->assertForbidden();
    }

    public function test_user_can_create_a_warehouse(): void
    {
        $user = $this->userWithPermissions(['warehouses.view', 'warehouses.manage']);

        $this->actingAs($user)
            ->post(route('stock.warehouses.store'), [
                'name' => 'Entrepôt Annexe',
                'code' => 'ANNEXE-1',
                'address' => 'Dakar, Zone B',
            ])
            ->assertRedirect(route('stock.warehouses.index'));

        $this->assertDatabaseHas('warehouses', [
            'name' => 'Entrepôt Annexe',
            'code' => 'ANNEXE-1',
            'is_default' => false,
        ]);
    }

    public function test_warehouse_codes_must_be_unique(): void
    {
        $user = $this->userWithPermissions(['warehouses.manage']);
        $this->warehouse(['code' => 'ANNEXE-1']);

        $this->actingAs($user)
            ->post(route('stock.warehouses.store'), [
                'name' => 'Doublon',
                'code' => 'ANNEXE-1',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_products_are_attached_to_the_default_warehouse(): void
    {
        $user = $this->userWithPermissions(['warehouses.view']);
        $default = $this->warehouse(['is_default' => true]);
        $product = $this->product(['stock_quantity' => 7]);

        $this->actingAs($user)
            ->get(route('stock.warehouses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/warehouses')
                ->has('warehouses', 1)
                ->where('warehouses.0.id', $default->id)
                ->where('warehouses.0.is_default', true)
                ->where('warehouses.0.products_count', 1)
                ->where('warehouses.0.total_units', 7));

        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $product->id,
            'warehouse_id' => $default->id,
            'quantity' => 7,
        ]);
    }

    public function test_product_stock_quantity_syncs_with_warehouse_levels(): void
    {
        $default = $this->warehouse(['is_default' => true]);
        $product = $this->product(['stock_quantity' => 7]);

        $product->adjustStock($default->id, -3);

        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertSame(4, $product->stockAt($default->id));
    }

    public function test_user_can_set_the_default_warehouse(): void
    {
        $user = $this->userWithPermissions(['warehouses.manage']);
        $first = $this->warehouse(['is_default' => true]);
        $second = $this->warehouse();

        $this->actingAs($user)
            ->patch(route('stock.warehouses.set-default', $second))
            ->assertRedirect(route('stock.warehouses.index'));

        $this->assertSame(0, (int) $first->fresh()->is_default);
        $this->assertSame(1, (int) $second->fresh()->is_default);
    }

    public function test_default_warehouse_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions(['warehouses.manage']);
        $default = $this->warehouse(['is_default' => true]);

        $this->actingAs($user)
            ->delete(route('stock.warehouses.destroy', $default))
            ->assertRedirect(route('stock.warehouses.index'));

        $this->assertDatabaseHas('warehouses', ['id' => $default->id]);
    }

    public function test_non_empty_warehouse_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions(['warehouses.manage']);
        $default = $this->warehouse(['is_default' => true]);
        $other = $this->warehouse();

        $product = $this->product(['stock_quantity' => 5]);
        $product->adjustStock($other->id, 5);

        $this->actingAs($user)
            ->delete(route('stock.warehouses.destroy', $other))
            ->assertRedirect(route('stock.warehouses.index'));

        $this->assertDatabaseHas('warehouses', ['id' => $other->id]);
    }

    public function test_user_can_transfer_stock_between_warehouses(): void
    {
        $user = $this->userWithPermissions(['transfers.view', 'transfers.create']);
        $from = $this->warehouse(['is_default' => true]);
        $to = $this->warehouse();

        // Le produit naît rattaché à l'entrepôt par défaut avec son stock.
        $product = $this->product(['stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.transfers.store'), [
                'product_id' => $product->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'quantity' => 4,
                'notes' => 'Réappro Annexe',
            ])
            ->assertRedirect(route('stock.transfers.index'));

        $this->assertSame(6, $product->stockAt($from->id));
        $this->assertSame(4, $product->stockAt($to->id));
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->assertDatabaseHas('stock_transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'quantity' => 4,
            'notes' => 'Réappro Annexe',
            'user_id' => $user->id,
        ]);

        // Deux mouvements tracés : une sortie et une entrée.
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => -4,
            'warehouse_id' => $from->id,
            'reason' => 'Transfert entrepôt (sortie)',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 4,
            'warehouse_id' => $to->id,
            'reason' => 'Transfert entrepôt (entrée)',
        ]);
    }

    public function test_transfer_rejects_same_warehouse(): void
    {
        $user = $this->userWithPermissions(['transfers.create']);
        $warehouse = $this->warehouse(['is_default' => true]);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.transfers.store'), [
                'product_id' => $product->id,
                'from_warehouse_id' => $warehouse->id,
                'to_warehouse_id' => $warehouse->id,
                'quantity' => 2,
            ])
            ->assertSessionHasErrors('to_warehouse_id');

        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_transfer_rejects_insufficient_source_stock(): void
    {
        $user = $this->userWithPermissions(['transfers.create']);
        $from = $this->warehouse(['is_default' => true]);
        $to = $this->warehouse();

        $product = $this->product(['stock_quantity' => 2]);

        $this->actingAs($user)
            ->post(route('stock.transfers.store'), [
                'product_id' => $product->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'quantity' => 5,
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(2, $product->stockAt($from->id));
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_transfers_index_lists_history(): void
    {
        $user = $this->userWithPermissions(['transfers.view']);
        $from = $this->warehouse(['is_default' => true]);
        $to = $this->warehouse();
        $product = $this->product();

        StockTransfer::create([
            'product_id' => $product->id,
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'quantity' => 3,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('stock.transfers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/transfers')
                ->has('transfers.data', 1)
                ->where('transfers.data.0.quantity', 3)
                ->where('transfers.data.0.from_warehouse.id', $from->id)
                ->where('transfers.data.0.to_warehouse.id', $to->id));
    }
}
