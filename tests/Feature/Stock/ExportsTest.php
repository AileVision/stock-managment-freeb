<?php

namespace Tests\Feature\Stock;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ExportsTest extends TestCase
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
            'stock_quantity' => 50,
            ...$attributes,
        ]);
    }

    private function completedSale(int $userId, array $attributes = []): Sale
    {
        $product = $this->product();

        $sale = Sale::create([
            'reference' => 'V-20260814-'.str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'user_id' => $userId,
            'payment_method' => 'card',
            'total' => 150,
            'status' => 'completed',
            ...$attributes,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 150,
        ]);

        return $sale;
    }

    public function test_sales_report_can_be_exported_as_csv(): void
    {
        $user = $this->userWithPermissions(['reports.view', 'reports.export']);
        $this->completedSale($user->id);
        $this->completedSale($user->id);

        $response = $this->actingAs($user)
            ->get(route('stock.reports.sales.export'))
            ->assertOk()
            ->assertDownload('rapport-ventes.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Chiffre d’affaires', $content);
        $this->assertStringContainsString('Nombre de ventes', $content);
        $this->assertStringContainsString('Marge brute', $content);
        $this->assertStringContainsString('CA PAR JOUR', $content);
        $this->assertStringContainsString('MEILLEURES VENTES', $content);
    }

    public function test_sales_report_can_be_exported_as_pdf(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $this->completedSale($user->id);

        $this->actingAs($user)
            ->get(route('stock.reports.sales.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_sales_report_csv_respects_the_date_range(): void
    {
        $user = $this->userWithPermissions(['reports.export']);
        $oldProduct = $this->product(['name' => 'Produit ancien', 'sku' => 'ANCIEN-CSV-001']);
        $oldSale = $this->completedSale($user->id);
        $oldSale->items()->update(['product_id' => $oldProduct->id]);
        $oldSale->forceFill(['created_at' => now()->subDays(10)])->save();

        $recentProduct = $this->product(['name' => 'Produit récent', 'sku' => 'RECENT-CSV-001']);
        $recentSale = $this->completedSale($user->id);
        $recentSale->items()->update(['product_id' => $recentProduct->id]);
        $recentSale->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->actingAs($user)
            ->get(route('stock.reports.sales.export', [
                'from' => now()->subDays(3)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk();

        $content = $response->streamedContent();

        // 1 vente récente, pas l'ancienne.
        $this->assertStringContainsString('Nombre de ventes', $content);
        $this->assertStringContainsString('RECENT-CSV-001', $content);
        $this->assertStringNotContainsString('ANCIEN-CSV-001', $content);
    }

    public function test_invoices_can_be_exported_as_a_single_pdf(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);

        $first = $this->completedSale($user->id);
        $second = $this->completedSale($user->id);

        Invoice::create([
            'reference' => 'F-20260814-0001',
            'sale_id' => $first->id,
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'issued_at' => now()->toDateString(),
        ]);
        Invoice::create([
            'reference' => 'F-20260814-0002',
            'sale_id' => $second->id,
            'user_id' => $user->id,
            'customer_name' => 'Client B',
            'issued_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('stock.invoices.export-pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_invoice_batch_export_respects_filters(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);

        $oldSale = $this->completedSale($user->id);
        $oldInvoice = Invoice::create([
            'reference' => 'F-20260801-0001',
            'sale_id' => $oldSale->id,
            'user_id' => $user->id,
            'customer_name' => 'Client Ancien',
            'issued_at' => now()->subDays(10)->toDateString(),
        ]);
        $oldInvoice->forceFill(['issued_at' => now()->subDays(10)->toDateString()])->save();

        $recentSale = $this->completedSale($user->id);
        Invoice::create([
            'reference' => 'F-20260814-0003',
            'sale_id' => $recentSale->id,
            'user_id' => $user->id,
            'customer_name' => 'Client Récent',
            'issued_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('stock.invoices.export-pdf', [
                'from' => now()->subDays(3)->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_without_export_permission_cannot_export_sales_report(): void
    {
        $user = $this->userWithPermissions(['reports.view']);

        $this->actingAs($user)
            ->get(route('stock.reports.sales.export'))
            ->assertForbidden();
    }
}
