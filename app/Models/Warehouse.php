<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $address
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'code', 'address', 'is_default'])]
class Warehouse extends Model
{
    /**
     * Resolve the default warehouse id, falling back to the first one.
     */
    public static function defaultId(): ?int
    {
        $default = static::query()->where('is_default', true)->value('id');

        if ($default !== null) {
            return (int) $default;
        }

        $first = static::query()->value('id');

        return $first !== null ? (int) $first : null;
    }

    /**
     * The stock levels of this warehouse.
     *
     * @return HasMany<ProductWarehouse, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(ProductWarehouse::class);
    }

    /**
     * The transfers that left this warehouse.
     *
     * @return HasMany<StockTransfer, $this>
     */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
    }

    /**
     * The transfers that arrived in this warehouse.
     *
     * @return HasMany<StockTransfer, $this>
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }
}
