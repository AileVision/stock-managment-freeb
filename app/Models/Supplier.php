<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 * @property-read Collection<int, SupplierPayment> $payments
 * @property-read Collection<int, Product> $products
 */
#[Fillable(['name', 'email', 'phone', 'address'])]
class Supplier extends Model
{
    /**
     * Get the products supplied by the supplier.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The purchase orders for this supplier.
     *
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * The payments made to this supplier.
     *
     * @return HasMany<SupplierPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * The total outstanding debt across all purchase orders.
     */
    public function outstandingBalance(): float
    {
        return round((float) $this->purchaseOrders->sum(
            fn (PurchaseOrder $order): float => max($order->balance(), 0),
        ), 2);
    }

    /**
     * The part of the outstanding balance that is past due.
     */
    public function overdueBalance(): float
    {
        return round((float) $this->purchaseOrders->sum(
            fn (PurchaseOrder $order): float => $order->isOverdue() ? $order->balance() : 0.0,
        ), 2);
    }
}
