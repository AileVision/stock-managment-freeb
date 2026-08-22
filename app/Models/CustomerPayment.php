<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property int|null $customer_id
 * @property int|null $user_id
 * @property string $amount
 * @property string $payment_method
 * @property string|null $mobile_phone
 * @property string|null $mobile_reference
 * @property string|null $notes
 * @property Carbon $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 * @property-read Customer|null $customer
 * @property-read User|null $user
 */
#[Fillable(['sale_id', 'customer_id', 'user_id', 'amount', 'payment_method', 'mobile_phone', 'mobile_reference', 'notes', 'paid_at'])]
class CustomerPayment extends Model
{
    /**
     * The payment methods accepted for an encaissement.
     */
    public const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'card' => 'Carte bancaire',
        'transfer' => 'Virement',
        'tmoney' => 'T-Money',
        'flooz' => 'Flooz',
        'avoir' => 'Avoir',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The credit sale this payment settles.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * The customer who paid.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The user who recorded the payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the payment was made by mobile money.
     */
    public function isMobilePayment(): bool
    {
        return in_array($this->payment_method, Sale::MOBILE_PAYMENT_METHODS, true);
    }
}
