<?php

namespace Tests\Feature\Stock;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ReceivablesTest extends TestCase
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

    private function customer(array $attributes = []): Customer
    {
        return Customer::create([
            'name' => 'Grossiste du Centre',
            'phone' => '0708091011',
            ...$attributes,
        ]);
    }

    private function creditSale(Customer $customer, array $attributes = []): Sale
    {
        return Sale::create([
            'reference' => 'V-20260815-'.str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'user_id' => $customer->id,
            'payment_method' => 'credit',
            'total' => 1500,
            'status' => Sale::STATUS_COMPLETED,
            'credit_due_at' => now()->addDays(30)->toDateString(),
            ...$attributes,
        ]);
    }

    public function test_guests_are_redirected_to_login_for_receivables(): void
    {
        $this->get(route('stock.receivables.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_receivables(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.receivables.index'))
            ->assertForbidden();
    }

    public function test_user_can_record_a_credit_sale_with_customer_and_due_date(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create']);
        $customer = $this->customer();
        $product = $this->product(['sale_price' => 150, 'stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 4]],
                'payment_method' => 'credit',
                'customer_id' => $customer->id,
                'credit_due_at' => now()->addDays(30)->toDateString(),
            ])
            ->assertRedirect(route('stock.sales.pos'));

        $sale = Sale::firstOrFail();

        $this->assertSame('credit', $sale->payment_method);
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame(600, (int) $sale->total);
        $this->assertSame(600.0, $sale->balance());
        $this->assertSame('outstanding', $sale->creditStatus());
    }

    public function test_credit_sale_requires_a_customer_and_a_due_date(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'credit',
            ])
            ->assertSessionHasErrors(['customer_id', 'credit_due_at']);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_credit_sale_rejects_a_past_due_date(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $customer = $this->customer();
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'credit',
                'customer_id' => $customer->id,
                'credit_due_at' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors('credit_due_at');
    }

    public function test_credit_sale_accepts_a_down_payment(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $customer = $this->customer();
        $product = $this->product(['sale_price' => 150, 'stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.sales.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 4]],
                'payment_method' => 'credit',
                'customer_id' => $customer->id,
                'credit_due_at' => now()->addDays(30)->toDateString(),
                'down_payment' => 200,
                'down_payment_method' => 'cash',
            ])
            ->assertRedirect();

        $sale = Sale::firstOrFail();

        $this->assertSame(200.0, $sale->paidAmount());
        $this->assertSame(400.0, $sale->balance());

        $this->assertDatabaseHas('customer_payments', [
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'amount' => 200,
            'payment_method' => 'cash',
        ]);
    }

    public function test_receivables_page_lists_outstanding_balances(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'receivables.view']);
        $customer = $this->customer();
        $sale = $this->creditSale($customer, ['total' => 1500]);

        $this->actingAs($user)
            ->get(route('stock.receivables.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/receivables')
                ->has('customers.data', 1)
                ->where('customers.data.0.name', $customer->name)
                ->where('customers.data.0.balance', 1500)
                ->where('stats.total_outstanding', 1500)
                ->where('stats.overdue_total', 0));
    }

    public function test_overdue_sales_are_flagged(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'receivables.view']);
        $customer = $this->customer();
        $this->creditSale($customer, [
            'total' => 1000,
            'credit_due_at' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('stock.receivables.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/receivables')
                ->has('customers.data', 1)
                ->where('customers.data.0.overdue_balance', 1000)
                ->where('customers.data.0.overdue_sales', 1)
                ->where('stats.overdue_total', 1000));
    }

    public function test_user_can_record_a_payment_on_a_credit_sale(): void
    {
        $user = $this->userWithPermissions(['receivables.view', 'receivables.manage']);
        $customer = $this->customer();
        $sale = $this->creditSale($customer, ['total' => 1500]);

        $this->actingAs($user)
            ->post(route('stock.receivables.payments.store'), [
                'sale_id' => $sale->id,
                'amount' => 500,
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $this->assertSame(500.0, $sale->fresh()->paidAmount());
        $this->assertSame(1000.0, $sale->fresh()->balance());

        $this->assertDatabaseHas('customer_payments', [
            'sale_id' => $sale->id,
            'amount' => 500,
            'payment_method' => 'cash',
        ]);
    }

    public function test_payment_cannot_exceed_the_remaining_balance(): void
    {
        $user = $this->userWithPermissions(['receivables.manage']);
        $customer = $this->customer();
        $sale = $this->creditSale($customer, ['total' => 1500]);

        $this->actingAs($user)
            ->post(route('stock.receivables.payments.store'), [
                'sale_id' => $sale->id,
                'amount' => 2000,
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_payment_cannot_be_recorded_on_a_paid_sale(): void
    {
        $user = $this->userWithPermissions(['receivables.manage']);
        $customer = $this->customer();
        $sale = $this->creditSale($customer, ['total' => 0]);

        $this->actingAs($user)
            ->post(route('stock.receivables.payments.store'), [
                'sale_id' => $sale->id,
                'amount' => 100,
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_a_sale_with_payments_cannot_be_cancelled(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.delete', 'receivables.manage']);
        $customer = $this->customer();
        $sale = $this->creditSale($customer, ['total' => 1500]);

        $this->actingAs($user)->post(route('stock.receivables.payments.store'), [
            'sale_id' => $sale->id,
            'amount' => 500,
            'payment_method' => 'cash',
        ]);

        $this->actingAs($user)
            ->delete(route('stock.sales.destroy', $sale))
            ->assertRedirect();

        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_a_credit_sale_can_be_paid_by_avoir(): void
    {
        $user = $this->userWithPermissions(['receivables.view', 'receivables.manage']);
        $customer = $this->customer();
        $sale = $this->creditSale($customer, ['total' => 1500]);

        $note = CreditNote::create([
            'reference' => 'A-20260815-0001',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'amount' => 1000,
            'status' => CreditNote::STATUS_ISSUED,
            'issued_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('stock.receivables.payments.store'), [
                'sale_id' => $sale->id,
                'amount' => 1000,
                'payment_method' => 'avoir',
            ])
            ->assertRedirect();

        $this->assertSame(1000.0, $sale->fresh()->paidAmount());
        $this->assertSame(500.0, $sale->fresh()->balance());

        $this->assertSame(1000.0, (float) $note->fresh()->used_amount);
        $this->assertSame(CreditNote::STATUS_USED, $note->fresh()->status);

        $this->assertDatabaseHas('customer_payments', [
            'sale_id' => $sale->id,
            'amount' => 1000,
            'payment_method' => 'avoir',
        ]);
    }

    public function test_payment_by_avoir_requires_available_avoirs(): void
    {
        $user = $this->userWithPermissions(['receivables.manage']);
        $customer = $this->customer();
        $sale = $this->creditSale($customer, ['total' => 1500]);

        $this->actingAs($user)
            ->post(route('stock.receivables.payments.store'), [
                'sale_id' => $sale->id,
                'amount' => 500,
                'payment_method' => 'avoir',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_relance_pdf_is_generated_for_a_customer_with_balance(): void
    {
        $user = $this->userWithPermissions(['receivables.view', 'receivables.manage']);
        $customer = $this->customer();
        $this->creditSale($customer, [
            'total' => 1200,
            'credit_due_at' => now()->subDays(3)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('stock.receivables.relance', $customer))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_receivables_can_be_exported_as_csv_with_filters(): void
    {
        $user = $this->userWithPermissions(['receivables.view', 'receivables.manage']);
        $customer = $this->customer(['name' => 'Grossiste Export']);
        $this->creditSale($customer, [
            'total' => 2000,
            'credit_due_at' => now()->subDays(3)->toDateString(),
        ]);
        $this->creditSale($this->customer(['name' => 'Sans Solde']), [
            'total' => 500,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('stock.receivables.export', ['status' => 'overdue']))
            ->assertOk()
            ->assertDownload('creances.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Client', $content);
        $this->assertStringContainsString('Grossiste Export', $content);
        $this->assertStringContainsString('2 000,00', $content);
        $this->assertStringNotContainsString('Sans Solde', $content);
    }

    public function test_payment_receipt_pdf_is_generated_after_an_encaissement(): void
    {
        $user = $this->userWithPermissions(['receivables.view', 'receivables.manage']);
        $customer = $this->customer();
        $sale = $this->creditSale($customer, ['total' => 1500]);

        $this->actingAs($user)
            ->post(route('stock.receivables.payments.store'), [
                'sale_id' => $sale->id,
                'amount' => 500,
                'payment_method' => 'cash',
                'notes' => 'Acompte',
            ])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.payment_receipt', (int) $sale->payments()->latest('id')->value('id'));

        $payment = $sale->payments()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('stock.receivables.payments.receipt', $payment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_payment_receipt_requires_the_manage_permission(): void
    {
        $user = $this->userWithPermissions(['receivables.view']);
        $customer = $this->customer();
        $sale = $this->creditSale($customer);
        $payment = $sale->payments()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'amount' => 500,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('stock.receivables.payments.receipt', $payment))
            ->assertForbidden();
    }
}
