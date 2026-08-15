<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $rate
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'rate', 'is_default', 'is_active'])]
class TaxRate extends Model
{
    /**
     * Resolve the default tax rate, falling back to the first active one.
     */
    public static function defaultRate(): ?TaxRate
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('rate', 'desc')
            ->first();
    }

    /**
     * The active tax rates usable in the cash register and quotes.
     *
     * @return Collection<int, TaxRate>
     */
    public static function active(): Collection
    {
        return self::query()->where('is_active', true)->orderBy('rate')->get();
    }

    /**
     * The tax amount included in a tax-inclusive total.
     */
    public function amountIncluded(float $totalInclusive): float
    {
        $rate = (float) $this->rate;

        if ($rate <= 0) {
            return 0.0;
        }

        return round($totalInclusive - $totalInclusive / (1 + $rate / 100), 2);
    }
}
