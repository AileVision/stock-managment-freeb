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
 * @property int|null $customer_id
 * @property string|null $credit_due_at
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
 * @property-read Customer|null $customer
 * @property-read Collection<int, SaleItem> $items
 * @property-read Collection<int, CustomerPayment> $payments
 * @property-read Collection<int, SaleReturn> $returns
 * @property-read Invoice|null $invoice
 */
#[Fillable(['reference', 'session_id', 'warehouse_id', 'user_id', 'customer_id', 'credit_due_at', 'payment_method', 'total', 'discount_percent', 'tax_rate_id', 'tax_percent', 'mobile_phone', 'mobile_reference', 'status', 'notes'])]
class Sale extends Model
{
    /**
     * Available payment methods, keyed by stored value. Includes the Togolese
     * mobile money providers T-Money and Flooz, plus « À crédit » (vente B2B)
     * and « Avoir » (règlement par avoir client).
     */
    public const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'card' => 'Carte bancaire',
        'transfer' => 'Virement',
        'tmoney' => 'T-Money',
        'flooz' => 'Flooz',
        'credit' => 'À crédit',
        'avoir' => 'Avoir',
    ];

    /**
     * The payment methods that require a mobile phone number.
     *
     * @var array<int, string>
     */
    public const MOBILE_PAYMENT_METHODS = ['tmoney', 'flooz'];

    /**
     * The payment methods that require a customer (credit sales and avoirs).
     *
     * @var array<int, string>
     */
    public const CUSTOMER_PAYMENT_METHODS = ['credit', 'avoir'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_due_at' => 'date',
        ];
    }

    /**
     * The credit status of a sale: settled, outstanding or overdue.
     */
    public const CREDIT_PAID = 'paid';

    public const CREDIT_OUTSTANDING = 'outstanding';

    public const CREDIT_OVERDUE = 'overdue';

    /**
     * Whether the sale was made on credit (B2B / grossiste).
     */
    public function isCredit(): bool
    {
        return $this->payment_method === 'credit';
    }

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
     * The customer the sale was made to (credit sales).
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The payments (acomptes, soldes) received on this sale.
     *
     * @return HasMany<CustomerPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * The returns recorded against this sale.
     *
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * The total amount already paid on this sale.
     */
    public function paidAmount(): float
    {
        return round((float) $this->payments->sum('amount'), 2);
    }

    /**
     * The value of the goods returned on this sale.
     */
    public function returnedValue(): float
    {
        return round((float) $this->returns->sum('total'), 2);
    }

    /**
     * The part of the returns applied to the unpaid balance.
     */
    public function returnedAppliedToBalance(): float
    {
        return round((float) $this->returns->sum('applied_to_balance'), 2);
    }

    /**
     * The remaining balance owed on a credit sale: total minus payments minus
     * the value of the returns applied to the balance.
     */
    public function balance(): float
    {
        if (! $this->isCredit()) {
            return 0.0;
        }

        return round((float) $this->total - $this->paidAmount() - $this->returnedAppliedToBalance(), 2);
    }

    /**
     * Whether the credit sale is past its due date with an unpaid balance.
     */
    public function isOverdue(): bool
    {
        return $this->isCredit()
            && $this->balance() > 0.01
            && $this->credit_due_at !== null
            && $this->credit_due_at->isBefore(today());
    }

    /**
     * The credit status of the sale: paid, outstanding or overdue.
     */
    public function creditStatus(): string
    {
        if (! $this->isCredit()) {
            return self::CREDIT_PAID;
        }

        if ($this->isOverdue()) {
            return self::CREDIT_OVERDUE;
        }

        return $this->balance() <= 0.01 ? self::CREDIT_PAID : self::CREDIT_OUTSTANDING;
    }

    /**
     * The credit status labels keyed by stored value.
     */
    public const CREDIT_STATUS_LABELS = [
        'paid' => 'Soldé',
        'outstanding' => 'En cours',
        'overdue' => 'En retard',
    ];

    /**
     * The human-readable label of the credit status.
     */
    public function creditStatusLabel(): string
    {
        return self::CREDIT_STATUS_LABELS[$this->creditStatus()] ?? $this->creditStatus();
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
