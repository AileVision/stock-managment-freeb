<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 */
#[Fillable(['name', 'sku', 'barcode', 'category_id', 'supplier_id', 'unit', 'purchase_price', 'sale_price', 'stock_quantity', 'min_stock', 'description'])]
class Product extends Model
{
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
