<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int|null $user_id
 * @property string $payment_method
 * @property string $total
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, SaleItem> $items
 */
#[Fillable(['reference', 'user_id', 'payment_method', 'total', 'status', 'notes'])]
class Sale extends Model
{
    /**
     * Available payment methods, keyed by stored value.
     */
    public const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'card' => 'Carte bancaire',
        'transfer' => 'Virement',
    ];

    /**
     * Whether the sale has been cancelled (stock restored).
     */
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The user who recorded the sale.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The products sold in this sale.
     *
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
