<?php

namespace Tests\Feature\Stock;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class TaxAndMobilePaymentsTest extends TestCase
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

    public function test_tax_rates_are_seeded_with_the_togolese_default(): void
    {
        $this->assertDatabaseHas('tax_rates', ['name' => 'TVA 18 %', 'rate' => 18.00, 'is_default' => true]);
        $this->assertDatabaseHas('tax_rates', ['name' => 'TVA 0 %', 'rate' => 0.00]);

        $this->assertSame(18.0, (float) TaxRate::defaultRate()->rate);
    }

    public function test_a_sale_applies_the_default_vat_rate(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $product = $this->product(['sale_price' => 1180, 'stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('stock.sales.pos'));

        $sale = Sale::firstOrFail();

        // Prix TTC : 1180 avec 18 % de TVA incluse ⇒ 180 de TVA, 1000 HT.
        $this->assertSame(1180.0, (float) $sale->total);
        $this->assertSame(18.0, (float) $sale->tax_percent);
        $this->assertNotNull($sale->tax_rate_id);
        $this->assertSame(180.0, $sale->taxAmount());
    }

    public function test_a_sale_without_tax_keeps_a_zero_vat_amount(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $product = $this->product(['sale_price' => 1000, 'stock_quantity' => 10]);
        $zeroRate = TaxRate::query()->where('rate', 0)->firstOrFail();

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
                'tax_rate_id' => $zeroRate->id,
            ])
            ->assertRedirect(route('stock.sales.pos'));

        $sale = Sale::firstOrFail();

        $this->assertSame(1000.0, (float) $sale->total);
        $this->assertSame(0.0, (float) $sale->tax_percent);
        $this->assertSame(0.0, $sale->taxAmount());
    }

    public function test_an_invalid_tax_rate_is_rejected(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
                'tax_rate_id' => 9999,
            ])
            ->assertSessionHasErrors('tax_rate_id');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_a_mobile_payment_sale_records_the_phone_number(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $product = $this->product(['sale_price' => 1000, 'stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'payment_method' => 'tmoney',
                'mobile_phone' => '+228 90 00 00 00',
                'mobile_reference' => 'TM-123456',
            ])
            ->assertRedirect(route('stock.sales.pos'));

        $sale = Sale::firstOrFail();

        $this->assertSame('tmoney', $sale->payment_method);
        $this->assertSame('+228 90 00 00 00', $sale->mobile_phone);
        $this->assertSame('TM-123456', $sale->mobile_reference);
        $this->assertTrue($sale->isMobilePayment());
    }

    public function test_a_mobile_payment_sale_requires_a_phone_number(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $product = $this->product(['stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'flooz',
            ])
            ->assertSessionHasErrors('mobile_phone');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_an_invalid_mobile_phone_is_rejected(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'tmoney',
                'mobile_phone' => 'not-a-phone',
            ])
            ->assertSessionHasErrors('mobile_phone');
    }

    public function test_cash_payments_do_not_require_a_phone_number(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $product = $this->product(['stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('stock.sales.pos'));

        $sale = Sale::firstOrFail();

        $this->assertNull($sale->mobile_phone);
    }

    public function test_the_pos_page_lists_the_active_tax_rates(): void
    {
        $user = $this->userWithPermissions(['sales.view']);

        $this->actingAs($user)
            ->get(route('stock.sales.pos'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/pos')
                ->has('tax_rates', 2)
                ->where('tax_rates.1.name', 'TVA 18 %')
                ->where('tax_rates.1.rate', 18)
                ->where('tax_rates.1.is_default', true));
    }

    public function test_a_quote_stores_its_tax_rate(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.create']);
        $product = $this->product(['sale_price' => 1180]);

        $defaultRate = TaxRate::defaultRate();

        $this->actingAs($user)
            ->post(route('stock.quotes.store'), [
                'customer_name' => 'Client A',
                'tax_rate_id' => $defaultRate->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1180]],
            ])
            ->assertRedirect(route('stock.quotes.index'));

        $quote = Quote::firstOrFail();

        $this->assertSame($defaultRate->id, $quote->tax_rate_id);
        $this->assertSame(18.0, (float) $quote->tax_percent);
        $this->assertSame(180.0, $quote->taxAmount());
    }

    public function test_converting_a_quote_carries_the_tax_to_the_sale_and_invoice(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.update']);
        $product = $this->product(['sale_price' => 1180, 'stock_quantity' => 20]);

        $defaultRate = TaxRate::defaultRate();

        $quote = Quote::create([
            'reference' => 'D-20260814-0001',
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'status' => 'accepted',
            'tax_rate_id' => $defaultRate->id,
            'tax_percent' => 18.0,
        ]);
        $quote->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Produit test',
            'quantity' => 1,
            'unit_price' => 1180,
        ]);

        $this->actingAs($user)
            ->post(route('stock.quotes.convert', $quote), ['payment_method' => 'tmoney', 'mobile_phone' => '+228 90 00 00 00'])
            ->assertRedirect(route('stock.invoices.index'));

        $sale = Sale::firstOrFail();
        $invoice = Invoice::firstOrFail();

        $this->assertSame(18.0, (float) $sale->tax_percent);
        $this->assertSame('tmoney', $sale->payment_method);
        $this->assertSame('+228 90 00 00 00', $sale->mobile_phone);
        $this->assertSame(180.0, $sale->taxAmount());
        $this->assertSame(1180.0, (float) $invoice->sale->total);
    }

    public function test_converting_a_quote_to_a_mobile_payment_requires_a_phone(): void
    {
        $user = $this->userWithPermissions(['quotes.view', 'quotes.update']);
        $product = $this->product(['stock_quantity' => 20]);

        $quote = Quote::create([
            'reference' => 'D-20260814-0002',
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'status' => 'accepted',
        ]);
        $quote->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Produit test',
            'quantity' => 1,
            'unit_price' => 1180,
        ]);

        $this->actingAs($user)
            ->post(route('stock.quotes.convert', $quote), ['payment_method' => 'flooz'])
            ->assertSessionHasErrors('mobile_phone');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_taxes_page_lists_rates_and_requires_permission(): void
    {
        $user = $this->userWithPermissions(['taxes.view']);

        $this->actingAs($user)
            ->get(route('stock.taxes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/taxes')
                ->has('tax_rates', 2));

        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.taxes.index'))
            ->assertForbidden();
    }

    public function test_a_tax_rate_can_be_created_updated_and_set_as_default(): void
    {
        $user = $this->userWithPermissions(['taxes.view', 'taxes.manage']);

        // Création.
        $this->actingAs($user)
            ->post(route('stock.taxes.store'), [
                'name' => 'TVA réduite',
                'rate' => 10,
            ])
            ->assertRedirect();

        $rate = TaxRate::where('name', 'TVA réduite')->firstOrFail();
        $this->assertSame(10.0, (float) $rate->rate);

        // Mise à jour.
        $this->actingAs($user)
            ->patch(route('stock.taxes.update', $rate), [
                'name' => 'TVA réduite (alimentaire)',
                'rate' => 5,
            ])
            ->assertRedirect();

        $this->assertSame('TVA réduite (alimentaire)', $rate->fresh()->name);
        $this->assertSame(5.0, (float) $rate->fresh()->rate);

        // Défaut : l'ancien taux par défaut perd son drapeau.
        $this->actingAs($user)
            ->patch(route('stock.taxes.set-default', $rate))
            ->assertRedirect();

        $this->assertTrue((bool) $rate->fresh()->is_default);
        $this->assertFalse((bool) TaxRate::where('name', 'TVA 18 %')->first()->is_default);
    }

    public function test_the_default_tax_rate_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions(['taxes.view', 'taxes.manage']);
        $defaultRate = TaxRate::defaultRate();

        $this->actingAs($user)
            ->delete(route('stock.taxes.destroy', $defaultRate))
            ->assertRedirect();

        $this->assertDatabaseHas('tax_rates', ['id' => $defaultRate->id]);
    }

    public function test_users_without_manage_permission_cannot_manage_taxes(): void
    {
        $user = $this->userWithPermissions(['taxes.view']);

        $this->actingAs($user)
            ->post(route('stock.taxes.store'), ['name' => 'TVA X', 'rate' => 5])
            ->assertForbidden();
    }

    public function test_financial_report_computes_revenue_vat_and_margins(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $product = $this->product(['purchase_price' => 100, 'sale_price' => 1180, 'stock_quantity' => 50]);

        // Deux ventes TTC à 18 % : 1180 + 2360 = 3540 TTC, soit 540 de TVA et 3000 HT.
        $this->actingAs($this->userWithPermissions(['sales.create']))
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ]);
        $this->actingAs($this->userWithPermissions(['sales.create']))
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'payment_method' => 'tmoney',
                'mobile_phone' => '+228 90 00 00 00',
            ]);

        // Une commande réceptionnée de 3 unités à 100 HT = 300 d'achats, TVA
        // 18 % ajoutée : 54 de TVA déductible et 354 TTC.
        $supplier = Supplier::create(['name' => 'Fournisseur test']);
        $defaultRate = TaxRate::defaultRate();
        $order = PurchaseOrder::create([
            'reference' => 'CA-20260814-0001',
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'tax_rate_id' => $defaultRate->id,
            'tax_percent' => 18,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'received_quantity' => 3,
        ]);

        $this->actingAs($user)
            ->get(route('stock.reports.financial'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/reports/financial')
                ->where('summary.revenue', 3540)
                ->where('summary.revenue_ht', 3000)
                ->where('summary.vat_collected', 540)
                ->where('summary.sales_count', 2)
                ->where('summary.purchases', 300)
                ->where('summary.purchases_vat', 54)
                ->where('summary.purchases_ttc', 354)
                ->where('summary.cost_of_goods', 300)
                ->where('summary.gross_margin', 2700)
                ->has('vat_by_rate', 1)
                ->where('vat_by_rate.0.vat', 540)
                ->has('purchases_vat_by_rate', 1)
                ->where('purchases_vat_by_rate.0.vat', 54)
                ->where('purchases_vat_by_rate.0.ttc', 354)
                ->where('purchase_orders.0.amount_ht', 300)
                ->where('purchase_orders.0.vat', 54)
                ->where('purchase_orders.0.amount_ttc', 354)
                ->where('purchase_orders.0.rate_name', 'TVA 18 %'));
    }

    public function test_a_purchase_order_stores_its_tax_rate(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.view', 'purchase_orders.create']);
        $product = $this->product(['purchase_price' => 100]);
        $defaultRate = TaxRate::defaultRate();

        $this->actingAs($user)
            ->post(route('stock.purchase-orders.store'), [
                'tax_rate_id' => $defaultRate->id,
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
            ])
            ->assertRedirect(route('stock.purchase-orders.index'));

        $order = PurchaseOrder::firstOrFail();

        $this->assertSame($defaultRate->id, $order->tax_rate_id);
        $this->assertSame(18.0, (float) $order->tax_percent);
        // 5 × 100 HT = 500, TVA 18 % = 90.
        $this->assertSame(500.0, $order->amountHt());
        $this->assertSame(90.0, $order->taxAmount($order->amountHt()));
    }

    public function test_an_invalid_purchase_order_tax_rate_is_rejected(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.create']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.purchase-orders.store'), [
                'tax_rate_id' => 9999,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('tax_rate_id');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_purchase_orders_page_lists_the_active_tax_rates(): void
    {
        $user = $this->userWithPermissions(['purchase_orders.view']);

        $this->actingAs($user)
            ->get(route('stock.purchase-orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/purchase-orders')
                ->has('tax_rates', 2)
                ->where('tax_rates.1.name', 'TVA 18 %')
                ->where('tax_rates.1.is_default', true));
    }

    public function test_financial_report_exports_a_csv(): void
    {
        $user = $this->userWithPermissions(['reports.view', 'reports.export']);
        $product = $this->product(['sale_price' => 1180, 'stock_quantity' => 10]);

        $this->actingAs($this->userWithPermissions(['sales.create']))
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ]);

        $this->actingAs($user)
            ->get(route('stock.reports.financial.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('rapport-financier.csv');
    }

    public function test_sales_history_exposes_tax_and_mobile_details(): void
    {
        $user = $this->userWithPermissions(['sales.view']);
        $product = $this->product(['sale_price' => 1180, 'stock_quantity' => 10]);

        $sale = Sale::create([
            'reference' => 'V-20260814-0001',
            'user_id' => $user->id,
            'payment_method' => 'tmoney',
            'total' => 1180,
            'tax_percent' => 18.0,
            'mobile_phone' => '+228 90 00 00 00',
            'status' => 'completed',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1180,
        ]);

        $this->actingAs($user)
            ->get(route('stock.sales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/sales')
                ->where('sales.data.0.payment_method', 'tmoney')
                ->where('sales.data.0.mobile_phone', '+228 90 00 00 00')
                ->where('sales.data.0.tax_percent', 18)
                ->where('sales.data.0.tax_amount', 180));
    }
}
