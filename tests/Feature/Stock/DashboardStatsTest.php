<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
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

    public function test_dashboard_computes_stock_statistics(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);

        // En stock, au-dessus du seuil.
        $this->product(['name' => 'A', 'stock_quantity' => 10, 'min_stock' => 5, 'purchase_price' => 100]);
        // Stock bas (0 < stock <= seuil).
        $this->product(['name' => 'B', 'stock_quantity' => 4, 'min_stock' => 5, 'purchase_price' => 200]);
        // En rupture.
        $this->product(['name' => 'C', 'stock_quantity' => 0, 'min_stock' => 10, 'purchase_price' => 300]);
        // Pas de seuil défini : jamais en alerte.
        $this->product(['name' => 'D', 'stock_quantity' => 5, 'min_stock' => 0, 'purchase_price' => 150]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('stats.total_products', 4)
                ->where('stats.total_units', 19)
                ->where('stats.stock_value', 2550)
                ->where('stats.out_of_stock', 1)
                ->where('stats.low_stock', 1));
    }

    public function test_dashboard_lists_low_stock_products_sorted_by_severity(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);
        $category = Category::create(['name' => 'Alimentation', 'slug' => 'alimentation']);

        // Ratio stock/seuil : 0,5 (3/6) puis 0,4 (2/5) : le plus critique en premier.
        $this->product(['name' => 'Moins critique', 'stock_quantity' => 3, 'min_stock' => 6, 'category_id' => $category->id]);
        $this->product(['name' => 'Plus critique', 'stock_quantity' => 2, 'min_stock' => 5, 'category_id' => $category->id]);
        $this->product(['name' => 'Sain', 'stock_quantity' => 20, 'min_stock' => 5]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('low_stock_products', 2)
                ->where('low_stock_products.0.name', 'Plus critique')
                ->where('low_stock_products.1.name', 'Moins critique')
                ->where('low_stock_products.0.category.name', 'Alimentation'));
    }

    public function test_dashboard_lists_the_ten_most_recent_movements(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);
        $product = $this->product();

        $ids = [];
        $base = now()->subMinutes(12);

        for ($i = 1; $i <= 12; $i++) {
            $movement = $product->stockMovements()->create([
                'type' => 'in',
                'quantity' => $i,
                'reason' => "Mouvement $i",
                'user_id' => $user->id,
            ]);
            $movement->forceFill(['created_at' => $base->copy()->addMinutes($i)])->save();
            $ids[] = $movement->id;
        }

        $latestIds = array_reverse($ids); // Les plus récents d'abord.

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recent_movements', 10)
                ->where('recent_movements.0.id', $latestIds[0])
                ->where('recent_movements.9.id', $latestIds[9])
                ->where('recent_movements.0.user.name', $user->name));
    }

    public function test_dashboard_shows_empty_states_without_data(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_products', 0)
                ->where('stats.stock_value', 0)
                ->has('low_stock_products', 0)
                ->has('recent_movements', 0));
    }
}
