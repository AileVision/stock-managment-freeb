<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user->givePermissionTo($permissions);

        return $user;
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

    public function test_guests_are_redirected_to_login_for_products(): void
    {
        $this->get(route('stock.products.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_products(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('stock.products.index'))
            ->assertForbidden();
    }

    public function test_user_can_create_a_product_with_initial_stock(): void
    {
        $user = $this->userWithPermissions(['products.create', 'products.view']);
        $category = Category::create(['name' => 'Alimentation', 'slug' => 'alimentation']);

        $this->actingAs($user)
            ->post(route('stock.products.store'), [
                'name' => 'Riz parfumé 5kg',
                'sku' => 'RIZ-TEST-001',
                'category_id' => $category->id,
                'unit' => 'kg',
                'purchase_price' => 3200,
                'sale_price' => 3800,
                'stock_quantity' => 25,
                'min_stock' => 5,
            ])
            ->assertRedirect(route('stock.products.index'));

        $this->assertDatabaseHas('products', ['sku' => 'RIZ-TEST-001', 'stock_quantity' => 25]);

        $product = Product::where('sku', 'RIZ-TEST-001')->firstOrFail();
        $this->assertSame(1, $product->stockMovements()->count());
        $this->assertSame(25, $product->stockMovements()->first()?->quantity);
    }

    public function test_sku_must_be_unique(): void
    {
        $user = $this->userWithPermissions(['products.create']);
        $this->product(['sku' => 'DUP-001']);

        $this->actingAs($user)
            ->post(route('stock.products.store'), [
                'name' => 'Doublon',
                'sku' => 'DUP-001',
                'unit' => 'pcs',
                'purchase_price' => 100,
                'sale_price' => 150,
            ])
            ->assertSessionHasErrors('sku');
    }

    public function test_user_can_record_a_stock_out_movement(): void
    {
        $user = $this->userWithPermissions(['movements.create']);
        $product = $this->product(['stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.movements.store'), [
                'product_id' => $product->id,
                'type' => 'out',
                'quantity' => 4,
                'reason' => 'Vente au comptoir',
            ])
            ->assertRedirect();

        $this->assertSame(6, $product->fresh()->stock_quantity);
    }

    public function test_stock_out_is_rejected_when_insufficient(): void
    {
        $user = $this->userWithPermissions(['movements.create']);
        $product = $this->product(['stock_quantity' => 2]);

        $this->actingAs($user)
            ->post(route('stock.movements.store'), [
                'product_id' => $product->id,
                'type' => 'out',
                'quantity' => 5,
                'reason' => 'Vente',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_user_can_record_an_adjustment_movement(): void
    {
        $user = $this->userWithPermissions(['movements.create']);
        $product = $this->product(['stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.movements.store'), [
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => -3,
                'reason' => 'Inventaire physique',
            ])
            ->assertRedirect();

        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    public function test_user_without_delete_permission_cannot_delete_product(): void
    {
        $user = $this->userWithPermissions(['products.view']);
        $product = $this->product();

        $this->actingAs($user)
            ->delete(route('stock.products.destroy', $product))
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
