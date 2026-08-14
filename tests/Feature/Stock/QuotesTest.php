<?php

namespace Tests\Feature\Stock;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class QuotesTest extends TestCase
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

    private function quotePayload(array $items = []): array
    {
        return [
            'customer_name' => 'Client A',
            'customer_email' => 'client@example.com',
            'customer_phone' => '0102030405',
            'customer_address' => 'Abidjan',
            'valid_until' => '2026-09-30',
            'notes' => 'Remise à valider',
            'items' => $items,
        ];
    }

    public function test_guests_are_redirected_to_login_for_quotes(): void
    {
        $this->get(route('stock.quotes.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_quotes(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.quotes.index'))
            ->assertForbidden();
    }

    public function test_user_can_create_a_quote_with_items(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.create']);
        $rice = $this->product(['name' => 'Riz', 'sku' => 'RIZ-DEVIS-001', 'sale_price' => 150]);

        $this->actingAs($user)
            ->post(route('stock.quotes.store'), $this->quotePayload([
                ['product_id' => $rice->id, 'quantity' => 10, 'unit_price' => 140],
            ]))
            ->assertRedirect(route('stock.quotes.index'));

        $quote = Quote::firstOrFail();

        $this->assertStringStartsWith('D-', $quote->reference);
        $this->assertSame('draft', $quote->status);
        $this->assertSame('Client A', $quote->customer_name);

        $this->assertDatabaseHas('quote_items', [
            'quote_id' => $quote->id,
            'product_id' => $rice->id,
            'product_name' => 'Riz',
            'quantity' => 10,
            'unit_price' => 140,
        ]);
    }

    public function test_quote_requires_at_least_one_item(): void
    {
        $user = $this->userWithPermissions(['quotes.create']);

        $this->actingAs($user)
            ->post(route('stock.quotes.store'), $this->quotePayload())
            ->assertSessionHasErrors('items');
    }

    public function test_user_without_create_permission_cannot_create_a_quote(): void
    {
        $user = $this->userWithPermissions(['quotes.view']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.quotes.store'), $this->quotePayload([
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 150],
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_user_can_update_an_editable_quote(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.create', 'quotes.update']);
        $rice = $this->product(['name' => 'Riz', 'sku' => 'RIZ-DEVIS-002', 'sale_price' => 150]);
        $oil = $this->product(['name' => 'Huile', 'sku' => 'HUI-DEVIS-002', 'sale_price' => 500]);

        $this->actingAs($user)->post(route('stock.quotes.store'), $this->quotePayload([
            ['product_id' => $rice->id, 'quantity' => 5, 'unit_price' => 150],
        ]));

        $quote = Quote::firstOrFail();

        $this->actingAs($user)
            ->patch(route('stock.quotes.update', $quote), $this->quotePayload([
                ['product_id' => $oil->id, 'quantity' => 3, 'unit_price' => 480],
            ]))
            ->assertRedirect(route('stock.quotes.index'));

        $this->assertSame(1, $quote->fresh()->items()->count());
        $this->assertDatabaseHas('quote_items', [
            'quote_id' => $quote->id,
            'product_name' => 'Huile',
            'quantity' => 3,
            'unit_price' => 480,
        ]);
    }

    public function test_quote_status_can_be_moved_through_lifecycle(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.update']);
        $quote = Quote::create([
            'reference' => 'D-20260814-0001',
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'status' => 'draft',
        ]);

        $this->actingAs($user)->patch(route('stock.quotes.status', $quote), ['status' => 'sent']);
        $this->assertSame('sent', $quote->fresh()->status);

        $this->actingAs($user)->patch(route('stock.quotes.status', $quote), ['status' => 'accepted']);
        $this->assertSame('accepted', $quote->fresh()->status);
    }

    public function test_converting_an_accepted_quote_creates_sale_and_invoice(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.update']);
        $rice = $this->product(['name' => 'Riz', 'sku' => 'RIZ-CONV-001', 'sale_price' => 150, 'stock_quantity' => 20]);

        $quote = Quote::create([
            'reference' => 'D-20260814-0002',
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'customer_email' => 'client@example.com',
            'status' => 'accepted',
        ]);
        $quote->items()->create([
            'product_id' => $rice->id,
            'product_name' => 'Riz',
            'quantity' => 4,
            'unit_price' => 140,
        ]);

        $this->actingAs($user)
            ->post(route('stock.quotes.convert', $quote), ['payment_method' => 'transfer'])
            ->assertRedirect(route('stock.invoices.index'));

        $sale = Sale::firstOrFail();
        $invoice = Invoice::firstOrFail();

        $this->assertSame('completed', $sale->status);
        $this->assertSame(560, (int) $sale->total);
        $this->assertStringStartsWith('V-', $sale->reference);
        $this->assertStringStartsWith('F-', $invoice->reference);
        $this->assertSame($sale->id, $invoice->sale_id);
        $this->assertSame('Client A', $invoice->customer_name);
        $this->assertSame(16, $rice->fresh()->stock_quantity);

        $this->assertSame('converted', $quote->fresh()->status);
        $this->assertSame($sale->id, $quote->fresh()->sale_id);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $rice->id,
            'type' => 'out',
            'quantity' => -4,
            'reason' => "Vente issue du devis {$quote->reference}",
            'user_id' => $user->id,
        ]);
    }

    public function test_only_accepted_quotes_can_be_converted(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.update']);
        $quote = Quote::create([
            'reference' => 'D-20260814-0003',
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->post(route('stock.quotes.convert', $quote), ['payment_method' => 'cash'])
            ->assertRedirect();

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_conversion_is_rejected_when_stock_is_insufficient(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.update']);
        $rice = $this->product(['name' => 'Riz', 'sku' => 'RIZ-CONV-002', 'sale_price' => 150, 'stock_quantity' => 2]);

        $quote = Quote::create([
            'reference' => 'D-20260814-0004',
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'status' => 'accepted',
        ]);
        $quote->items()->create([
            'product_id' => $rice->id,
            'product_name' => 'Riz',
            'quantity' => 10,
            'unit_price' => 140,
        ]);

        $response = $this->actingAs($user)
            ->post(route('stock.quotes.convert', $quote), ['payment_method' => 'cash']);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(2, $rice->fresh()->stock_quantity);
    }

    public function test_converted_quote_cannot_be_updated_or_deleted(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.update', 'quotes.delete']);
        $quote = Quote::create([
            'reference' => 'D-20260814-0005',
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'status' => 'converted',
        ]);

        $this->actingAs($user)
            ->patch(route('stock.quotes.update', $quote), $this->quotePayload())
            ->assertRedirect();

        $this->actingAs($user)
            ->delete(route('stock.quotes.destroy', $quote))
            ->assertRedirect();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
    }

    public function test_quotes_can_be_filtered_by_status(): void
    {
        $user = $this->userWithPermissions(['quotes.view']);

        Quote::create([
            'reference' => 'D-20260814-0006',
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'status' => 'draft',
        ]);
        Quote::create([
            'reference' => 'D-20260814-0007',
            'user_id' => $user->id,
            'customer_name' => 'Client B',
            'status' => 'accepted',
        ]);

        $this->actingAs($user)
            ->get(route('stock.quotes.index', ['status' => 'accepted']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/quotes')
                ->has('quotes.data', 1)
                ->where('quotes.data.0.reference', 'D-20260814-0007'));
    }
}
