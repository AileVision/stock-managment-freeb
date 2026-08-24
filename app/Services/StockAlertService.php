<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;

class StockAlertService
{
    /**
     * Check a product's stock level and create a notification if needed.
     * Called after every stock movement.
     */
    public function checkProduct(Product $product): void
    {
        if ($product->min_stock <= 0) {
            return;
        }

        $quantity = (int) $product->stock_quantity;

        // Determine the alert type.
        if ($quantity === 0) {
            $type = Notification::TYPE_STOCK_OUT;
        } elseif ($quantity <= $product->min_stock) {
            $type = Notification::TYPE_STOCK_LOW;
        } else {
            // Stock is back above threshold — dismiss any open alert for this product.
            $this->dismissOpenAlerts($product);

            return;
        }

        // Don't duplicate: skip if an unread alert of the same type already exists.
        $existing = Notification::query()
            ->where('type', $type)
            ->where('is_read', false)
            ->whereJsonContainsPath('data', 'product_id', 'exact', (string) $product->id)
            ->exists();

        if ($existing) {
            return;
        }

        // Create alerts for all admin/admin users.
        $users = User::whereHas('roles', function ($query): void {
            $query->whereIn('name', ['superadmin', 'admin']);
        })->get();

        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $type === Notification::TYPE_STOCK_OUT
                    ? "Rupture de stock : {$product->name}"
                    : "Stock bas : {$product->name}",
                'message' => $type === Notification::TYPE_STOCK_OUT
                    ? "Le produit « {$product->name} » ({$product->sku}) est en rupture de stock."
                    : "Le produit « {$product->name} » ({$product->sku}) est à {$quantity} {$product->unit} (seuil : {$product->min_stock}).",
                'link' => '/stock/products?low_stock=1',
                'data' => ['product_id' => $product->id],
            ]);
        }
    }

    /**
     * Dismiss open low/out alerts for a product when stock is replenished.
     */
    public function dismissOpenAlerts(Product $product): void
    {
        Notification::query()
            ->whereIn('type', [Notification::TYPE_STOCK_LOW, Notification::TYPE_STOCK_OUT])
            ->where('is_read', false)
            ->whereJsonContainsPath('data', 'product_id', 'exact', (string) $product->id)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * Bulk-check all products and create any missing alerts.
     * Useful for the daily scheduled check.
     */
    public function checkAll(): int
    {
        $products = Product::query()
            ->where('min_stock', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->get();

        $created = 0;

        foreach ($products as $product) {
            $before = Notification::query()->where('is_read', false)->count();
            $this->checkProduct($product);
            $after = Notification::query()->where('is_read', false)->count();

            if ($after > $before) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Check all overdue credit sales and create notifications for admin users.
     * Dismisses alerts for sales that are no longer overdue.
     */
    public function checkOverdueReceivables(): int
    {
        $overdueSales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('payment_method', 'credit')
            ->whereNotNull('credit_due_at')
            ->where('credit_due_at', '<', now())
            ->with(['customer', 'payments', 'returns'])
            ->get()
            ->filter(fn (Sale $sale): bool => $sale->balance() > 0.01)
            ->values();

        $created = 0;

        foreach ($overdueSales as $sale) {
            $created += $this->createOverdueReceivableAlert($sale);
        }

        // Dismiss alerts for sales that are no longer overdue.
        $this->dismissStaleReceivableAlerts($overdueSales->pluck('id'));

        return $created;
    }

    /**
     * Create an alert for a single overdue sale (if not already alerted).
     */
    private function createOverdueReceivableAlert(Sale $sale): int
    {
        $existing = Notification::query()
            ->where('type', Notification::TYPE_RECEIVABLE_OVERDUE)
            ->where('is_read', false)
            ->whereRaw("json_extract(data, '$.sale_id') = ?", [$sale->id])
            ->exists();

        if ($existing) {
            return 0;
        }

        $customerName = $sale->customer?->name ?? 'Client inconnu';
        $balance = number_format($sale->balance(), 0, ',', ' ');
        $daysPast = (int) today()->diffInDays($sale->credit_due_at);

        $users = $this->adminUsers();

        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => Notification::TYPE_RECEIVABLE_OVERDUE,
                'title' => "Créance en retard : {$customerName}",
                'message' => "La vente {$sale->reference} ({$customerName}) est en retard de {$daysPast} jour(s). Solde restant : {$balance} FCFA.",
                'link' => '/stock/receivables?status=overdue',
                'data' => ['sale_id' => $sale->id, 'customer_id' => $sale->customer_id],
            ]);
        }

        return count($users);
    }

    /**
     * Dismiss receivable overdue alerts for sales that are no longer overdue.
     */
    private function dismissStaleReceivableAlerts($activeSaleIds): void
    {
        $activeIds = $activeSaleIds->map(fn (int $id): string => (string) $id)->values()->all();

        Notification::query()
            ->where('type', Notification::TYPE_RECEIVABLE_OVERDUE)
            ->where('is_read', false)
            ->each(function (Notification $notification) use ($activeIds): void {
                $saleId = (string) ($notification->data['sale_id'] ?? '');

                if (! in_array($saleId, $activeIds, true)) {
                    $notification->update(['is_read' => true, 'read_at' => now()]);
                }
            });
    }

    /**
     * Dismiss overdue alerts for a specific sale when it is fully paid.
     */
    public function dismissReceivableOverdueAlerts(Sale $sale): void
    {
        Notification::query()
            ->where('type', Notification::TYPE_RECEIVABLE_OVERDUE)
            ->where('is_read', false)
            ->whereRaw("json_extract(data, '$.sale_id') = ?", [$sale->id])
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * Check all overdue supplier purchase orders and create notifications.
     */
    public function checkOverdueSupplierPayables(): int
    {
        $overdueOrders = PurchaseOrder::query()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->with(['supplier', 'items.product', 'payments'])
            ->get()
            ->filter(fn (PurchaseOrder $order): bool => $order->balance() > 0.01)
            ->values();

        $created = 0;

        foreach ($overdueOrders as $order) {
            $created += $this->createOverdueSupplierAlert($order);
        }

        // Dismiss alerts for orders that are no longer overdue.
        $this->dismissStaleSupplierAlerts($overdueOrders->pluck('id'));

        return $created;
    }

    /**
     * Create an alert for a single overdue purchase order.
     */
    private function createOverdueSupplierAlert(PurchaseOrder $order): int
    {
        $existing = Notification::query()
            ->where('type', Notification::TYPE_SUPPLIER_OVERDUE)
            ->where('is_read', false)
            ->whereRaw("json_extract(data, '$.purchase_order_id') = ?", [$order->id])
            ->exists();

        if ($existing) {
            return 0;
        }

        $supplierName = $order->supplier?->name ?? 'Fournisseur inconnu';
        $balance = number_format($order->balance(), 0, ',', ' ');
        $daysPast = (int) today()->diffInDays($order->due_at);

        $users = $this->adminUsers();

        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => Notification::TYPE_SUPPLIER_OVERDUE,
                'title' => "Dette fournisseur : {$supplierName}",
                'message' => "La commande {$order->reference} ({$supplierName}) est en retard de {$daysPast} jour(s). Solde restant : {$balance} FCFA.",
                'link' => '/stock/supplier-payables?status=overdue',
                'data' => ['purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id],
            ]);
        }

        return count($users);
    }

    /**
     * Dismiss supplier overdue alerts for orders that are no longer overdue.
     */
    private function dismissStaleSupplierAlerts($activeOrderIds): void
    {
        $activeIds = $activeOrderIds->map(fn (int $id): string => (string) $id)->values()->all();

        Notification::query()
            ->where('type', Notification::TYPE_SUPPLIER_OVERDUE)
            ->where('is_read', false)
            ->each(function (Notification $notification) use ($activeIds): void {
                $orderId = (string) ($notification->data['purchase_order_id'] ?? '');

                if (! in_array($orderId, $activeIds, true)) {
                    $notification->update(['is_read' => true, 'read_at' => now()]);
                }
            });
    }

    /**
     * Dismiss overdue alerts for a specific purchase order when it is fully paid.
     */
    public function dismissSupplierOverdueAlerts(PurchaseOrder $order): void
    {
        Notification::query()
            ->where('type', Notification::TYPE_SUPPLIER_OVERDUE)
            ->where('is_read', false)
            ->whereRaw("json_extract(data, '$.purchase_order_id') = ?", [$order->id])
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * Get the unread notification count for a user.
     */
    public function unreadCount(User $user): int
    {
        return (int) Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get admin/superadmin users who receive notifications.
     */
    private function adminUsers()
    {
        return User::whereHas('roles', function ($query): void {
            $query->whereIn('name', ['superadmin', 'admin']);
        })->get();
    }
}
