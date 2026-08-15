<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $product_id
 * @property int $quantity
 * @property int $received_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read Product $product
 */
#[Fillable(['purchase_order_id', 'product_id', 'quantity', 'received_quantity'])]
class PurchaseOrderItem extends Model
{
    /**
     * The order this item belongs to.
     *
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * The product received.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The quantity still missing (ordered minus already received).
     */
    public function remaining(): int
    {
        return max((int) $this->quantity - (int) $this->received_quantity, 0);
    }
}
