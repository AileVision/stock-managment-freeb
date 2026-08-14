<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $product_id
 * @property int $quantity
 * @property string $unit_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 * @property-read Product $product
 */
#[Fillable(['sale_id', 'product_id', 'quantity', 'unit_price'])]
class SaleItem extends Model
{
    /**
     * The sale this item belongs to.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * The product sold.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
