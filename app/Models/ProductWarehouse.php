<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Warehouse $warehouse
 */
#[Fillable(['product_id', 'warehouse_id', 'quantity'])]
class ProductWarehouse extends Model
{
    /**
     * The table name is singular by design (product_warehouse).
     */
    protected $table = 'product_warehouse';

    /**
     * Register the hooks that keep products.stock_quantity in sync with the
     * sum of per-warehouse quantities.
     */
    protected static function booted(): void
    {
        static::saved(fn (ProductWarehouse $level) => self::syncProductTotal($level));

        static::deleted(fn (ProductWarehouse $level) => self::syncProductTotal($level));
    }

    /**
     * Recompute the product total from its stock levels.
     */
    private static function syncProductTotal(ProductWarehouse $level): void
    {
        $total = (int) ProductWarehouse::query()
            ->where('product_id', $level->product_id)
            ->sum('quantity');

        $product = Product::query()->find($level->product_id);

        if ($product !== null && (int) $product->stock_quantity !== $total) {
            $product->stock_quantity = $total;
            $product->saveQuietly();
        }
    }

    /**
     * The product this level belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The warehouse this level belongs to.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
