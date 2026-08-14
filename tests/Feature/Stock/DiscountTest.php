<?php

namespace Tests\Feature\Stock;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class DiscountTest extends TestCase
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

    public function test_a_sale_applies_the_discount_percent(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $product = $this->product(['sale_price' => 1000, 'stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
                'payment_method' => 'cash',
                'discount_percent' => 10,
            ])
            ->assertRedirect(route('stock.sales.pos'));

        $sale = Sale::firstOrFail();

        // 3 × 1000 = 3000, moins 10 % = 2700.
        $this->assertSame(2700.0, (float) $sale->total);
        $this->assertSame(10.0, (float) $sale->discount_percent);
        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    public function test_a_sale_without_discount_keeps_the_full_total(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $product = $this->product(['sale_price' => 1000, 'stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('stock.sales.pos'));

        $sale = Sale::firstOrFail();

        $this->assertSame(2000.0, (float) $sale->total);
        $this->assertSame(0.0, (float) $sale->discount_percent);
    }

    public function test_a_discount_above_100_is_rejected(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $product = $this->product(['stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
                'discount_percent' => 150,
            ])
            ->assertSessionHasErrors('discount_percent');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_a_quote_stores_its_discount(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.create']);
        $product = $this->product(['sale_price' => 1000]);

        $this->actingAs($user)
            ->post(route('stock.quotes.store'), [
                'customer_name' => 'Client A',
                'discount_percent' => 5,
                'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 1000]],
            ])
            ->assertRedirect(route('stock.quotes.index'));

        $quote = Quote::firstOrFail();

        $this->assertSame(5.0, (float) $quote->discount_percent);
    }

    public function test_converting_a_discounted_quote_carries_the_discount(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.update']);
        $product = $this->product(['sale_price' => 1000, 'stock_quantity' => 20]);

        $quote = Quote::create([
            'reference' => 'D-20260814-0001',
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'status' => 'accepted',
            'discount_percent' => 10,
        ]);
        $quote->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Produit test',
            'quantity' => 3,
            'unit_price' => 1000,
        ]);

        $this->actingAs($user)
            ->post(route('stock.quotes.convert', $quote), ['payment_method' => 'transfer'])
            ->assertRedirect(route('stock.invoices.index'));

        $sale = Sale::firstOrFail();
        $invoice = Invoice::firstOrFail();

        // 3 × 1000 = 3000, moins 10 % = 2700.
        $this->assertSame(2700.0, (float) $sale->total);
        $this->assertSame(10.0, (float) $sale->discount_percent);
        $this->assertSame(2700.0, (float) $invoice->sale->total);
    }
}
