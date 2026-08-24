<?php

namespace Tests\Feature\Stock;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockAlertService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StockAlertService::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        return $user;
    }

    private function superadminUser(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate('superadmin');
        $user->assignRole('superadmin');

        return $user;
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::create([
            'name' => 'Client Test',
            ...$attributes,
        ]);
    }

    private function supplier(array $attributes = []): Supplier
    {
        return Supplier::create([
            'name' => 'Fournisseur Test',
            ...$attributes,
        ]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create([
            'name' => 'Produit test',
            'sku' => 'TEST-' . strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'unit' => 'pcs',
            'purchase_price' => 100,
            'sale_price' => 150,
            'stock_quantity' => 10,
            ...$attributes,
        ]);
    }

    private function creditSale(Customer $customer, array $attributes = []): Sale
    {
        return Sale::create([
            'reference' => 'V-20260815-' . str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'user_id' => $customer->id,
            'payment_method' => 'credit',
            'total' => 1500,
            'status' => Sale::STATUS_COMPLETED,
            'credit_due_at' => now()->addDays(30)->toDateString(),
            ...$attributes,
        ]);
    }

    // ─── checkOverdueReceivables ───────────────────────────────────

    public function test_creates_notification_for_overdue_credit_sale(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customer();

        // Sale with due date in the past → overdue.
        $sale = $this->creditSale($customer, [
            'credit_due_at' => now()->subDays(5)->toDateString(),
            'total' => 5000,
        ]);

        $created = $this->service->checkOverdueReceivables();

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => Notification::TYPE_RECEIVABLE_OVERDUE,
            'is_read' => false,
        ]);

        $notification = Notification::where('type', Notification::TYPE_RECEIVABLE_OVERDUE)
            ->where('user_id', $admin->id)
            ->first();

        $this->assertStringContainsString($customer->name, $notification->title);
        $this->assertStringContainsString($sale->reference, $notification->message);
        $this->assertSame('/stock/receivables?status=overdue', $notification->link);
        $this->assertSame($sale->id, $notification->data['sale_id']);
    }

    public function test_no_notification_for_sale_not_yet_due(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customer();

        // Sale with due date in the future → not overdue.
        $this->creditSale($customer, [
            'credit_due_at' => now()->addDays(10)->toDateString(),
        ]);

        $created = $this->service->checkOverdueReceivables();

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_no_notification_for_fully_paid_sale(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customer();

        // Overdue sale but fully paid (balance = 0).
        $sale = $this->creditSale($customer, [
            'credit_due_at' => now()->subDays(5)->toDateString(),
            'total' => 500,
        ]);

        // Record payment equal to total.
        $sale->payments()->create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'amount' => 500,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $created = $this->service->checkOverdueReceivables();

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_creates_notification_for_partially_paid_overdue_sale(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customer();

        // Overdue sale with partial payment → still has balance.
        $sale = $this->creditSale($customer, [
            'credit_due_at' => now()->subDays(5)->toDateString(),
            'total' => 5000,
        ]);

        $sale->payments()->create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'amount' => 2000,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $created = $this->service->checkOverdueReceivables();

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_RECEIVABLE_OVERDUE,
            'is_read' => false,
        ]);

        $notification = Notification::where('type', Notification::TYPE_RECEIVABLE_OVERDUE)->first();
        $this->assertStringContainsString('3 000', $notification->message);
    }

    public function test_does_not_duplicate_existing_notification(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customer();

        $sale = $this->creditSale($customer, [
            'credit_due_at' => now()->subDays(5)->toDateString(),
        ]);

        // First call creates the notification.
        $this->service->checkOverdueReceivables();
        $this->assertDatabaseCount('notifications', 1);

        // Second call should not duplicate.
        $created = $this->service->checkOverdueReceivables();
        $this->assertSame(0, $created);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_creates_notifications_for_multiple_admin_users(): void
    {
        $admin1 = $this->adminUser();
        $admin2 = $this->adminUser();
        $customer = $this->customer();

        $this->creditSale($customer, [
            'credit_due_at' => now()->subDays(5)->toDateString(),
        ]);

        $created = $this->service->checkOverdueReceivables();

        // 2 admin users → 2 notifications.
        $this->assertSame(2, $created);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin1->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin2->id]);
    }

    public function test_dismisses_stale_notifications_for_paid_sales(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customer();

        // Create an overdue sale and generate notification.
        $sale = $this->creditSale($customer, [
            'credit_due_at' => now()->subDays(5)->toDateString(),
        ]);
        $this->service->checkOverdueReceivables();
        $this->assertDatabaseCount('notifications', 1);

        // Pay off the sale completely.
        $sale->payments()->create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'amount' => $sale->total,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        // Running again should dismiss the stale notification.
        $this->service->checkOverdueReceivables();

        $notification = Notification::where('type', Notification::TYPE_RECEIVABLE_OVERDUE)->first();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    // ─── dismissReceivableOverdueAlerts ────────────────────────────

    public function test_dismiss_receivable_overdue_alerts_on_payment(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customer();

        $sale = $this->creditSale($customer, [
            'credit_due_at' => now()->subDays(5)->toDateString(),
        ]);

        // Create the notification.
        $this->service->checkOverdueReceivables();
        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_RECEIVABLE_OVERDUE,
            'is_read' => false,
        ]);

        // Dismiss when paid.
        $this->service->dismissReceivableOverdueAlerts($sale);

        $notification = Notification::where('type', Notification::TYPE_RECEIVABLE_OVERDUE)->first();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    // ─── checkOverdueSupplierPayables ──────────────────────────────

    public function test_creates_notification_for_overdue_supplier_order(): void
    {
        $admin = $this->adminUser();
        $supplier = $this->supplier();
        $product = $this->product();

        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'reference' => 'CMD-TEST-001',
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'due_at' => now()->subDays(10),
            'paid_amount' => 0,
            'user_id' => $admin->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 10,
        ]);

        $created = $this->service->checkOverdueSupplierPayables();

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => Notification::TYPE_SUPPLIER_OVERDUE,
            'is_read' => false,
        ]);

        $notification = Notification::where('type', Notification::TYPE_SUPPLIER_OVERDUE)
            ->where('user_id', $admin->id)
            ->first();

        $this->assertStringContainsString($supplier->name, $notification->title);
        $this->assertStringContainsString($order->reference, $notification->message);
        $this->assertSame('/stock/supplier-payables?status=overdue', $notification->link);
        $this->assertSame($order->id, $notification->data['purchase_order_id']);
    }

    public function test_no_notification_for_supplier_order_not_yet_due(): void
    {
        $admin = $this->adminUser();
        $supplier = $this->supplier();
        $product = $this->product();

        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'reference' => 'CMD-TEST-002',
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'due_at' => now()->addDays(10),
            'paid_amount' => 0,
            'user_id' => $admin->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'received_quantity' => 5,
        ]);

        $created = $this->service->checkOverdueSupplierPayables();

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_no_notification_for_fully_paid_supplier_order(): void
    {
        $admin = $this->adminUser();
        $supplier = $this->supplier();
        $product = $this->product();

        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'reference' => 'CMD-TEST-003',
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'due_at' => now()->subDays(5),
            'paid_amount' => 1000,
            'user_id' => $admin->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 10,
        ]);

        $created = $this->service->checkOverdueSupplierPayables();

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_creates_notification_for_partially_paid_overdue_supplier_order(): void
    {
        $admin = $this->adminUser();
        $supplier = $this->supplier();
        $product = $this->product();

        // Order total: 10 × 100 = 1000, partially paid → balance 500.
        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'reference' => 'CMD-TEST-004',
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'due_at' => now()->subDays(5),
            'paid_amount' => 500,
            'user_id' => $admin->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 10,
        ]);

        $created = $this->service->checkOverdueSupplierPayables();

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_SUPPLIER_OVERDUE,
            'is_read' => false,
        ]);
    }

    public function test_does_not_duplicate_supplier_overdue_notification(): void
    {
        $admin = $this->adminUser();
        $supplier = $this->supplier();
        $product = $this->product();

        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'reference' => 'CMD-TEST-005',
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'due_at' => now()->subDays(5),
            'paid_amount' => 0,
            'user_id' => $admin->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 10,
        ]);

        $this->service->checkOverdueSupplierPayables();
        $this->assertDatabaseCount('notifications', 1);

        $created = $this->service->checkOverdueSupplierPayables();
        $this->assertSame(0, $created);
        $this->assertDatabaseCount('notifications', 1);
    }

    // ─── dismissSupplierOverdueAlerts ──────────────────────────────

    public function test_dismiss_supplier_overdue_alerts_on_payment(): void
    {
        $admin = $this->adminUser();
        $supplier = $this->supplier();
        $product = $this->product();

        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'reference' => 'CMD-TEST-006',
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'due_at' => now()->subDays(5),
            'paid_amount' => 0,
            'user_id' => $admin->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 10,
        ]);

        $this->service->checkOverdueSupplierPayables();
        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_SUPPLIER_OVERDUE,
            'is_read' => false,
        ]);

        $this->service->dismissSupplierOverdueAlerts($order);

        $notification = Notification::where('type', Notification::TYPE_SUPPLIER_OVERDUE)->first();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    public function test_dismisses_stale_supplier_notifications_when_fully_paid(): void
    {
        $admin = $this->adminUser();
        $supplier = $this->supplier();
        $product = $this->product();

        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'reference' => 'CMD-TEST-007',
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'due_at' => now()->subDays(5),
            'paid_amount' => 0,
            'user_id' => $admin->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 10,
        ]);

        // Create notification.
        $this->service->checkOverdueSupplierPayables();
        $this->assertDatabaseCount('notifications', 1);

        // Pay off the order (update paid_amount).
        $order->update(['paid_amount' => 1000]);

        // Running again should dismiss the stale notification.
        $this->service->checkOverdueSupplierPayables();

        $notification = Notification::where('type', Notification::TYPE_SUPPLIER_OVERDUE)->first();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    // ─── unreadCount ───────────────────────────────────────────────

    public function test_unread_count_returns_correct_number(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customer();

        $this->creditSale($customer, [
            'credit_due_at' => now()->subDays(5)->toDateString(),
        ]);

        $this->service->checkOverdueReceivables();

        $this->assertSame(1, $this->service->unreadCount($admin));

        // Mark as read → count should be 0.
        $this->service->dismissReceivableOverdueAlerts(Sale::first());
        $this->assertSame(0, $this->service->unreadCount($admin));
    }

    // ─── no notifications for non-admin users ──────────────────────

    public function test_creates_notification_for_superadmin_users(): void
    {
        $superadmin = $this->superadminUser();
        $customer = $this->customer();

        $this->creditSale($customer, [
            'credit_due_at' => now()->subDays(5)->toDateString(),
        ]);

        $created = $this->service->checkOverdueReceivables();

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $superadmin->id,
            'type' => Notification::TYPE_RECEIVABLE_OVERDUE,
        ]);
    }

    public function test_does_not_create_notification_for_regular_user(): void
    {
        $user = User::factory()->create(); // No role assigned.
        $customer = $this->customer();

        $this->creditSale($customer, [
            'credit_due_at' => now()->subDays(5)->toDateString(),
        ]);

        $created = $this->service->checkOverdueReceivables();

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('notifications', 0);
    }
}
