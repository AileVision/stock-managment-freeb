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
 * @property int|null $sale_id
 * @property int|null $customer_id
 * @property int|null $user_id
 * @property int|null $warehouse_id
 * @property string $total
 * @property string|null $refund_method
 * @property string $refund_amount
 * @property string $applied_to_balance
 * @property string|null $notes
 * @property Carbon $returned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale|null $sale
 * @property-read Customer|null $customer
 * @property-read User|null $user
 * @property-read Warehouse|null $warehouse
 * @property-read Collection<int, SaleReturnItem> $items
 * @property-read CreditNote|null $creditNote
 */
#[Fillable(['reference', 'sale_id', 'customer_id', 'user_id', 'warehouse_id', 'total', 'refund_method', 'refund_amount', 'applied_to_balance', 'notes', 'returned_at'])]
class SaleReturn extends Model
{
    /**
     * The disposition of the returned value.
     */
    public const REFUND_CASH = 'cash';

    public const REFUND_CARD = 'card';

    public const REFUND_TRANSFER = 'transfer';

    public const REFUND_TMONEY = 'tmoney';

    public const REFUND_FLOOZ = 'flooz';

    public const REFUND_AVOIR = 'avoir';

    public const REFUND_NONE = 'none';

    /**
     * The refund methods keyed by stored value.
     */
    public const REFUND_METHODS = [
        'cash' => 'Espèces',
        'card' => 'Carte bancaire',
        'transfer' => 'Virement',
        'tmoney' => 'T-Money',
        'flooz' => 'Flooz',
        'avoir' => 'Avoir',
        'none' => 'Aucun remboursement',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'applied_to_balance' => 'decimal:2',
            'returned_at' => 'date',
        ];
    }

    /**
     * The sale that was returned.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * The customer who returned the goods.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The user who processed the return.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The warehouse the returned goods are restocked into.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * The returned items.
     *
     * @return HasMany<SaleReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /**
     * The credit note issued for this return, if any.
     *
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }
}
