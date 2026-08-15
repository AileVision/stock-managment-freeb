<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $sku
 * @property string|null $barcode
 * @property int|null $category_id
 * @property int|null $supplier_id
 * @property string $unit
 * @property string $purchase_price
 * @property string $sale_price
 * @property int $stock_quantity
 * @property int $min_stock
 * @property string|null $description
 * @property bool $is_low_stock
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $category
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, ProductWarehouse> $productWarehouses
 */
#[Fillable(['name', 'sku', 'barcode', 'category_id', 'supplier_id', 'unit', 'purchase_price', 'sale_price', 'stock_quantity', 'min_stock', 'description'])]
class Product extends Model
{
    /**
     * Register the hook that attaches new products to the default warehouse.
     */
    protected static function booted(): void
    {
        static::created(function (Product $product): void {
            $defaultId = Warehouse::defaultId();

            if ($defaultId === null) {
                return;
            }

            $product->productWarehouses()->firstOrCreate(
                ['warehouse_id' => $defaultId],
                ['quantity' => max((int) $product->stock_quantity, 0)],
            );
        });
    }

    /**
     * Available measurement units, keyed by stored value.
     */
    public const UNITS = [
        'pcs' => 'Pièce',
        'box' => 'Boîte',
        'pack' => 'Paquet',
        'kg' => 'Kilogramme',
        'g' => 'Gramme',
        'l' => 'Litre',
        'ml' => 'Millilitre',
        'm' => 'Mètre',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }

    /**
     * Whether the product has fallen at or below its restock threshold.
     *
     * @return Attribute<bool, never>
     */
    protected function isLowStock(): Attribute
    {
        return Attribute::get(fn (): bool => $this->min_stock > 0 && $this->stock_quantity <= $this->min_stock);
    }

    /**
     * The per-warehouse stock levels of the product.
     *
     * @return HasMany<ProductWarehouse, $this>
     */
    public function productWarehouses(): HasMany
    {
        return $this->hasMany(ProductWarehouse::class);
    }

    /**
     * The available quantity in a given warehouse.
     */
    public function stockAt(int $warehouseId): int
    {
        return (int) $this->productWarehouses()
            ->where('warehouse_id', $warehouseId)
            ->value('quantity');
    }

    /**
     * Move stock in or out of a warehouse. The total stock_quantity is
     * recomputed automatically by the ProductWarehouse hooks.
     */
    public function adjustStock(int $warehouseId, int $delta): void
    {
        $level = $this->productWarehouses()->firstOrNew(['warehouse_id' => $warehouseId]);
        $level->quantity = max((int) $level->quantity + $delta, 0);
        $level->save();
    }

    /**
     * Get the category that the product belongs to.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the supplier of the product.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the stock movements for the product.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
