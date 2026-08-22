<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_return_id
 * @property int $product_id
 * @property int $quantity
 * @property string $unit_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SaleReturn $saleReturn
 * @property-read Product $product
 */
#[Fillable(['sale_return_id', 'product_id', 'quantity', 'unit_price'])]
class SaleReturnItem extends Model
{
    /**
     * The return this item belongs to.
     *
     * @return BelongsTo<SaleReturn, $this>
     */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /**
     * The product returned.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
