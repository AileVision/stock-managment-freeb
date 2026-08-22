<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int|null $sale_return_id
 * @property int|null $customer_id
 * @property int|null $user_id
 * @property string $amount
 * @property string $used_amount
 * @property string $status
 * @property Carbon $issued_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SaleReturn|null $saleReturn
 * @property-read Customer|null $customer
 * @property-read User|null $user
 */
#[Fillable(['reference', 'sale_return_id', 'customer_id', 'user_id', 'amount', 'used_amount', 'status', 'issued_at', 'notes'])]
class CreditNote extends Model
{
    public const STATUS_ISSUED = 'issued';

    public const STATUS_USED = 'used';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'used_amount' => 'decimal:2',
            'issued_at' => 'date',
        ];
    }

    /**
     * The remaining amount still usable on future sales.
     */
    public function remaining(): float
    {
        return round((float) $this->amount - (float) $this->used_amount, 2);
    }

    /**
     * The return that generated this avoir, if any.
     *
     * @return BelongsTo<SaleReturn, $this>
     */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /**
     * The customer the avoir was granted to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The user who issued the avoir.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
