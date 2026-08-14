<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class SalesReportTest extends TestCase
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

    private function completedSale(int $userId, array $items, array $attributes = []): Sale
    {
        $sale = Sale::create([
            'reference' => 'V-20260814-'.str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'user_id' => $userId,
            'payment_method' => 'cash',
            'total' => $attributes['total'] ?? 0,
            'status' => $attributes['status'] ?? 'completed',
        ]);

        // created_at n'est pas fillable : on l'applique après la création.
        if (isset($attributes['created_at'])) {
            $sale->created_at = $attributes['created_at'];
            $sale->save();
        }

        foreach ($items as $item) {
            $sale->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }

        return $sale;
    }

    public function test_guests_are_redirected_to_login_for_sales_report(): void
    {
        $this->get(route('stock.reports.sales'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_sales_report(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.reports.sales'))
            ->assertForbidden();
    }

    public function test_report_computes_revenue_margin_and_top_products(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $rice = $this->product(['name' => 'Riz', 'sku' => 'RIZ-RPT', 'sale_price' => 1000, 'purchase_price' => 600]);
        $oil = $this->product(['name' => 'Huile', 'sku' => 'HUI-RPT', 'sale_price' => 2000, 'purchase_price' => 1200]);

        $this->completedSale($user->id, [
            ['product_id' => $rice->id, 'quantity' => 1, 'unit_price' => 1000],
        ], ['total' => 1000]);
        $this->completedSale($user->id, [
            ['product_id' => $oil->id, 'quantity' => 2, 'unit_price' => 2000],
        ], ['total' => 4000]);

        $this->actingAs($user)
            ->get(route('stock.reports.sales'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/reports/sales')
                ->where('summary.revenue', 5000)
                ->where('summary.sales_count', 2)
                ->where('summary.average_basket', 2500)
                // Coût = 600 + 2 × 1200 = 3000 → marge 2000.
                ->where('summary.margin', 2000)
                ->where('summary.margin_rate', 40)
                ->has('top_products', 2)
                ->where('top_products.0.name', 'Huile')
                ->where('top_products.0.quantity', 2)
                ->where('top_products.0.revenue', 4000)
                ->where('top_products.0.margin', 1600)
                ->where('top_products.1.name', 'Riz')
                ->has('by_payment', 1)
                ->where('by_payment.0.payment_method', 'cash')
                ->where('by_payment.0.revenue', 5000)
                ->has('daily', 1));
    }

    public function test_report_respects_the_date_range(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $product = $this->product(['sale_price' => 1000, 'purchase_price' => 500]);

        $this->completedSale($user->id, [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ], ['total' => 1000, 'created_at' => now()->subDays(40)]);

        $this->completedSale($user->id, [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000],
        ], ['total' => 2000, 'created_at' => now()->subDays(5)]);

        $this->actingAs($user)
            ->get(route('stock.reports.sales', [
                'from' => now()->subDays(10)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/reports/sales')
                ->where('summary.sales_count', 1)
                ->where('summary.revenue', 2000)
                ->has('top_products', 1));
    }

    public function test_cancelled_sales_are_excluded_from_the_report(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $product = $this->product(['sale_price' => 1000, 'purchase_price' => 500]);

        $this->completedSale($user->id, [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ], ['total' => 1000]);

        $this->completedSale($user->id, [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ], ['total' => 1000, 'status' => 'cancelled']);

        $this->actingAs($user)
            ->get(route('stock.reports.sales'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/reports/sales')
                ->where('summary.sales_count', 1)
                ->where('summary.revenue', 1000));
    }
}
