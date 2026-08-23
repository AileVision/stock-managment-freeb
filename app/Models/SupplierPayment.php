<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $supplier_id
 * @property int|null $user_id
 * @property string $amount
 * @property string $payment_method
 * @property string|null $mobile_phone
 * @property string|null $mobile_reference
 * @property string|null $reference
 * @property string|null $notes
 * @property Carbon $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read Supplier|null $supplier
 * @property-read User|null $user
 */
#[Fillable(['purchase_order_id', 'supplier_id', 'user_id', 'amount', 'payment_method', 'mobile_phone', 'mobile_reference', 'reference', 'notes', 'paid_at'])]
class SupplierPayment extends Model
{
    /**
     * Payment methods accepted for supplier payments.
     */
    public const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'card' => 'Carte bancaire',
        'transfer' => 'Virement',
        'tmoney' => 'T-Money',
        'flooz' => 'Flooz',
    ];

    /**
     * Mobile money payment methods.
     *
     * @var array<int, string>
     */
    public const MOBILE_PAYMENT_METHODS = ['tmoney', 'flooz'];

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
     * The purchase order this payment settles.
     *
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * The supplier who received the payment.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
}
