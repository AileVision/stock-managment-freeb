<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $year
 * @property int $month
 * @property string $status
 * @property string $revenue
 * @property int $sales_count
 * @property string $revenue_ht
 * @property string $purchases
 * @property string $purchases_ht
 * @property int $purchases_count
 * @property string $expenses
 * @property int $expenses_count
 * @property string $vat_collected
 * @property string $vat_deductible
 * @property string $vat_net
 * @property string $gross_margin
 * @property string $net_result
 * @property string|null $notes
 * @property Carbon $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'user_id', 'year', 'month', 'status',
    'revenue', 'sales_count', 'revenue_ht',
    'purchases', 'purchases_ht', 'purchases_count',
    'expenses', 'expenses_count',
    'vat_collected', 'vat_deductible', 'vat_net',
    'gross_margin', 'net_result',
    'notes', 'closed_at',
])]
class MonthlyClosing extends Model
{
    public const STATUS_CLOSED = 'closed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'revenue_ht' => 'decimal:2',
            'purchases' => 'decimal:2',
            'purchases_ht' => 'decimal:2',
            'expenses' => 'decimal:2',
            'vat_collected' => 'decimal:2',
            'vat_deductible' => 'decimal:2',
            'vat_net' => 'decimal:2',
            'gross_margin' => 'decimal:2',
            'net_result' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The user who performed the closing.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Human-readable month name.
     */
    public function monthName(): string
    {
        return Carbon::createFromDate($this->year, $this->month, 1)->translatedFormat('F Y');
    }

    /**
     * The month label used in the UI.
     */
    public function label(): string
    {
        return $this->monthName();
    }

    /**
     * Check if a given year/month is already closed.
     */
    public static function isClosed(int $year, int $month): bool
    {
        return static::query()
            ->where('year', $year)
            ->where('month', $month)
            ->exists();
    }
}
