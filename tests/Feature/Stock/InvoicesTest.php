<?php

namespace Tests\Feature\Stock;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class InvoicesTest extends TestCase
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

    private function completedSale(int $userId): Sale
    {
        $product = $this->product(['sale_price' => 150, 'stock_quantity' => 10]);

        $sale = Sale::create([
            'reference' => 'V-20260814-'.str_pad((string) mt_rand(100, 999), 4, '0', STR_PAD_LEFT),
            'user_id' => $userId,
            'payment_method' => 'card',
            'total' => 450,
            'status' => 'completed',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 150,
        ]);

        return $sale;
    }

    public function test_guests_are_redirected_to_login_for_invoices(): void
    {
        $this->get(route('stock.invoices.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_invoices(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.invoices.index'))
            ->assertForbidden();
    }

    public function test_user_can_issue_an_invoice_from_a_completed_sale(): void
    {
        $user = $this->userWithPermissions(['invoices.view', 'invoices.create']);
        $sale = $this->completedSale($user->id);

        $this->actingAs($user)
            ->post(route('stock.invoices.store'), [
                'sale_id' => $sale->id,
                'customer_name' => 'Client A',
                'customer_email' => 'client@example.com',
                'customer_address' => 'Abidjan',
                'notes' => 'À régler sous 30 jours',
            ])
            ->assertRedirect();

        $invoice = Invoice::firstOrFail();

        $this->assertStringStartsWith('F-', $invoice->reference);
        $this->assertSame($sale->id, $invoice->sale_id);
        $this->assertSame('Client A', $invoice->customer_name);
        $this->assertSame('client@example.com', $invoice->customer_email);
    }

    public function test_user_without_create_permission_cannot_issue_an_invoice(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);
        $sale = $this->completedSale($user->id);

        $this->actingAs($user)
            ->post(route('stock.invoices.store'), [
                'sale_id' => $sale->id,
                'customer_name' => 'Client A',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_a_cancelled_sale_cannot_be_invoiced(): void
    {
        $user = $this->userWithPermissions(['invoices.view', 'invoices.create']);
        $product = $this->product();

        $sale = Sale::create([
            'reference' => 'V-20260814-0900',
            'user_id' => $user->id,
            'payment_method' => 'cash',
            'total' => 150,
            'status' => 'cancelled',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 150,
        ]);

        $this->actingAs($user)
            ->post(route('stock.invoices.store'), [
                'sale_id' => $sale->id,
                'customer_name' => 'Client A',
            ])
            ->assertSessionHasErrors('sale_id');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_a_sale_cannot_be_invoiced_twice(): void
    {
        $user = $this->userWithPermissions(['invoices.view', 'invoices.create']);
        $sale = $this->completedSale($user->id);

        Invoice::create([
            'reference' => 'F-20260814-0001',
            'sale_id' => $sale->id,
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'issued_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('stock.invoices.store'), [
                'sale_id' => $sale->id,
                'customer_name' => 'Client A',
            ])
            ->assertSessionHasErrors('sale_id');

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_invoices_index_lists_invoices_and_uninvoiced_sales(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);
        $sale = $this->completedSale($user->id);

        $invoice = Invoice::create([
            'reference' => 'F-20260814-0002',
            'sale_id' => $sale->id,
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'issued_at' => now()->toDateString(),
        ]);

        $other = $this->completedSale($user->id);

        $this->actingAs($user)
            ->get(route('stock.invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/invoices')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.reference', $invoice->reference)
                ->where('invoices.data.0.sale_reference', $sale->reference)
                ->where('invoices.data.0.total', 450)
                ->has('uninvoiced_sales', 1)
                ->where('uninvoiced_sales.0.id', $other->id));
    }

    public function test_invoice_pdf_is_downloaded(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);
        $sale = $this->completedSale($user->id);

        $invoice = Invoice::create([
            'reference' => 'F-20260814-0003',
            'sale_id' => $sale->id,
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'customer_address' => 'Abidjan',
            'issued_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('stock.invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_sale_ticket_pdf_is_downloaded(): void
    {
        $user = $this->userWithPermissions(['sales.view']);
        $sale = $this->completedSale($user->id);

        $this->actingAs($user)
            ->get(route('stock.sales.ticket-pdf', $sale))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_sales_index_exposes_the_invoice_link(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'invoices.view']);
        $sale = $this->completedSale($user->id);

        $invoice = Invoice::create([
            'reference' => 'F-20260814-0004',
            'sale_id' => $sale->id,
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'issued_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('stock.sales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/sales')
                ->where('sales.data.0.invoice.id', $invoice->id)
                ->where('sales.data.0.invoice.reference', $invoice->reference));
    }
}
