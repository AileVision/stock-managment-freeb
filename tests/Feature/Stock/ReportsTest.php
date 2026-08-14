<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ReportsTest extends TestCase
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

    public function test_guests_are_redirected_to_login_for_reports(): void
    {
        $this->get(route('stock.reports.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_reports(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.reports.index'))
            ->assertForbidden();
    }

    public function test_reports_page_shows_valuation_by_category(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $food = Category::create(['name' => 'Alimentation', 'slug' => 'alimentation', 'color' => '#f59e0b']);
        $this->product(['name' => 'Riz', 'category_id' => $food->id, 'stock_quantity' => 10, 'purchase_price' => 100]);
        $this->product(['name' => 'Huile', 'category_id' => $food->id, 'stock_quantity' => 5, 'purchase_price' => 200]);
        // Sans catégorie.
        $this->product(['name' => 'Divers', 'stock_quantity' => 3, 'purchase_price' => 50]);

        $this->actingAs($user)
            ->get(route('stock.reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/reports')
                ->where('summary.total_products', 3)
                ->where('summary.total_units', 18)
                ->where('summary.stock_value', 2150)
                ->has('categories', 2)
                ->where('categories.0.name', 'Alimentation')
                ->where('categories.0.stock_value', 2000)
                ->where('categories.0.percentage', 93)
                ->where('categories.1.name', 'Sans catégorie')
                ->where('categories.1.stock_value', 150));
    }

    public function test_reports_movement_summary_respects_date_range(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $product = $this->product();

        $in = $product->stockMovements()->create(['type' => 'in', 'quantity' => 10, 'reason' => 'Réception']);
        $in->forceFill(['created_at' => now()->subDays(10)])->save();

        $recentIn = $product->stockMovements()->create(['type' => 'in', 'quantity' => 5, 'reason' => 'Réception']);
        $recentIn->forceFill(['created_at' => now()->subDay()])->save();

        $out = $product->stockMovements()->create(['type' => 'out', 'quantity' => -3, 'reason' => 'Vente']);
        $out->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($user)
            ->get(route('stock.reports.index', [
                'from' => now()->subDays(3)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('movements.total', 2)
                ->where('movements.in_count', 1)
                ->where('movements.out_count', 1)
                ->where('movements.in_quantity', 5)
                ->where('movements.out_quantity', 3));
    }

    public function test_user_can_export_products_as_csv(): void
    {
        $user = $this->userWithPermissions(['reports.export']);
        $category = Category::create(['name' => 'Alimentation', 'slug' => 'alimentation']);
        $this->product(['name' => 'Riz parfumé', 'sku' => 'RIZ-CSV-001', 'category_id' => $category->id, 'stock_quantity' => 4, 'purchase_price' => 100]);

        $response = $this->actingAs($user)
            ->get(route('stock.reports.products.export'))
            ->assertOk()
            ->assertDownload('produits.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('RIZ-CSV-001', $content);
        $this->assertStringContainsString('Riz parfumé', $content);
        $this->assertStringContainsString('Alimentation', $content);
        $this->assertStringContainsString('400,00', $content);
    }

    public function test_user_can_export_movements_as_csv_with_filters(): void
    {
        $user = $this->userWithPermissions(['reports.export']);
        $product = $this->product(['name' => 'Riz', 'sku' => 'RIZ-MVT-001']);
        $movement = $product->stockMovements()->create([
            'type' => 'out',
            'quantity' => -2,
            'reason' => 'Vente au comptoir',
            'reference' => 'BL-2026-200',
            'user_id' => $user->id,
        ]);
        $movement->forceFill(['created_at' => now()->subDay()])->save();

        $old = $product->stockMovements()->create(['type' => 'in', 'quantity' => 50, 'reason' => 'Ancien']);
        $old->forceFill(['created_at' => now()->subMonth()])->save();

        $response = $this->actingAs($user)
            ->get(route('stock.reports.movements.export', [
                'from' => now()->subDays(3)->toDateString(),
                'type' => 'out',
            ]))
            ->assertOk()
            ->assertDownload('mouvements.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('RIZ-MVT-001', $content);
        $this->assertStringContainsString('Vente au comptoir', $content);
        $this->assertStringContainsString('BL-2026-200', $content);
        $this->assertStringNotContainsString('Ancien', $content);
    }

    public function test_user_without_export_permission_cannot_export(): void
    {
        $user = $this->userWithPermissions(['reports.view']);

        $this->actingAs($user)
            ->get(route('stock.reports.products.export'))
            ->assertForbidden();
    }
}
