<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int|null $session_id
 * @property int|null $warehouse_id
 * @property int|null $user_id
 * @property string $payment_method
 * @property string $total
 * @property string $discount_percent
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CashSession|null $session
 * @property-read Warehouse|null $warehouse
 * @property-read User|null $user
 * @property-read Collection<int, SaleItem> $items
 * @property-read Invoice|null $invoice
 */
#[Fillable(['reference', 'session_id', 'warehouse_id', 'user_id', 'payment_method', 'total', 'discount_percent', 'status', 'notes'])]
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
     * The warehouse the sale was drawn from.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

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
     * The cash register session the sale belongs to, if any.
     *
     * @return BelongsTo<CashSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /**
     * The invoice issued for this sale, if any.
     *
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
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
