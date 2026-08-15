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
 * @property string $tax_percent
 * @property int|null $tax_rate_id
 * @property string|null $mobile_phone
 * @property string|null $mobile_reference
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CashSession|null $session
 * @property-read Warehouse|null $warehouse
 * @property-read TaxRate|null $taxRate
 * @property-read User|null $user
 * @property-read Collection<int, SaleItem> $items
 * @property-read Invoice|null $invoice
 */
#[Fillable(['reference', 'session_id', 'warehouse_id', 'user_id', 'payment_method', 'total', 'discount_percent', 'tax_rate_id', 'tax_percent', 'mobile_phone', 'mobile_reference', 'status', 'notes'])]
class Sale extends Model
{
    /**
     * Available payment methods, keyed by stored value. Includes the Togolese
     * mobile money providers T-Money and Flooz.
     */
    public const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'card' => 'Carte bancaire',
        'transfer' => 'Virement',
        'tmoney' => 'T-Money',
        'flooz' => 'Flooz',
    ];

    /**
     * The payment methods that require a mobile phone number.
     *
     * @var array<int, string>
     */
    public const MOBILE_PAYMENT_METHODS = ['tmoney', 'flooz'];

    /**
     * The tax rate applied to the sale.
     *
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * The tax amount included in the (tax-inclusive) total.
     */
    public function taxAmount(): float
    {
        $rate = (float) $this->tax_percent;

        if ($rate <= 0) {
            return 0.0;
        }

        $total = (float) $this->total;

        return round($total - $total / (1 + $rate / 100), 2);
    }

    /**
     * Whether the sale was paid by mobile money.
     */
    public function isMobilePayment(): bool
    {
        return in_array($this->payment_method, self::MOBILE_PAYMENT_METHODS, true);
    }

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
