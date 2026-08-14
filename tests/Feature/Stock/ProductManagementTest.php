<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ProductManagementTest extends TestCase
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

    public function test_user_can_update_a_product_without_touching_stock(): void
    {
        $user = $this->userWithPermissions(['products.update']);
        $product = $this->product(['stock_quantity' => 7]);

        $this->actingAs($user)
            ->patch(route('stock.products.update', $product), [
                'name' => 'Nouveau nom',
                'sku' => $product->sku,
                'unit' => 'pcs',
                'purchase_price' => 120,
                'sale_price' => 180,
                'stock_quantity' => 999,
            ])
            ->assertRedirect(route('stock.products.index'));

        $product->refresh();

        $this->assertSame('Nouveau nom', $product->name);
        $this->assertSame(120.0, (float) $product->purchase_price);
        $this->assertSame(7, $product->stock_quantity);
        $this->assertSame(0, $product->stockMovements()->count());
    }

    public function test_user_can_delete_a_product_and_its_movements(): void
    {
        $user = $this->userWithPermissions(['products.delete']);
        $product = $this->product();
        $product->stockMovements()->create([
            'type' => 'in',
            'quantity' => 10,
            'reason' => 'Stock initial',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('stock.products.destroy', $product))
            ->assertRedirect(route('stock.products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    public function test_products_can_be_searched_by_name(): void
    {
        $user = $this->userWithPermissions(['products.view']);
        $this->product(['name' => 'Riz parfumé', 'sku' => 'RIZ-001']);
        $this->product(['name' => 'Savon', 'sku' => 'SAV-001']);

        $this->actingAs($user)
            ->get(route('stock.products.index', ['search' => 'riz']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/products')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Riz parfumé'));
    }

    public function test_products_can_be_searched_by_sku(): void
    {
        $user = $this->userWithPermissions(['products.view']);
        $this->product(['name' => 'Riz', 'sku' => 'RIZ-UNIQUE-001']);
        $this->product(['name' => 'Huile', 'sku' => 'HUI-001']);

        $this->actingAs($user)
            ->get(route('stock.products.index', ['search' => 'RIZ-UNIQUE']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
    }

    public function test_products_can_be_filtered_by_category(): void
    {
        $user = $this->userWithPermissions(['products.view']);
        $category = Category::create(['name' => 'Alimentation', 'slug' => 'alimentation']);
        $this->product(['name' => 'Riz', 'category_id' => $category->id]);
        $this->product(['name' => 'Savon']);

        $this->actingAs($user)
            ->get(route('stock.products.index', ['category_id' => $category->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
    }

    public function test_products_can_be_filtered_by_supplier(): void
    {
        $user = $this->userWithPermissions(['products.view']);
        $supplier = Supplier::create(['name' => 'AgroFresh']);
        $this->product(['name' => 'Riz', 'supplier_id' => $supplier->id]);
        $this->product(['name' => 'Savon']);

        $this->actingAs($user)
            ->get(route('stock.products.index', ['supplier_id' => $supplier->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
    }

    public function test_products_can_be_filtered_by_low_stock(): void
    {
        $user = $this->userWithPermissions(['products.view']);
        $this->product(['name' => 'En rupture', 'stock_quantity' => 0, 'min_stock' => 5]);
        $this->product(['name' => 'Stock bas', 'stock_quantity' => 3, 'min_stock' => 5]);
        $this->product(['name' => 'Stock ok', 'stock_quantity' => 50, 'min_stock' => 5]);

        $this->actingAs($user)
            ->get(route('stock.products.index', ['low_stock' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
    }

    public function test_products_index_returns_categories_and_suppliers_for_filters(): void
    {
        $user = $this->userWithPermissions(['products.view']);

        $this->actingAs($user)
            ->get(route('stock.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/products')
                ->has('categories')
                ->has('suppliers')
                ->has('filters'));
    }
}
