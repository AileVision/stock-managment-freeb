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
 * @property int|null $supplier_id
 * @property string|null $reference
 * @property string $status
 * @property int|null $warehouse_id
 * @property int|null $tax_rate_id
 * @property string $tax_percent
 * @property int|null $user_id
 * @property string|null $due_at
 * @property string $paid_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier|null $supplier
 * @property-read Warehouse|null $warehouse
 * @property-read TaxRate|null $taxRate
 * @property-read User|null $user
 * @property-read Collection<int, PurchaseOrderItem> $items
 * @property-read Collection<int, SupplierPayment> $payments
 */
#[Fillable(['supplier_id', 'reference', 'status', 'warehouse_id', 'tax_rate_id', 'tax_percent', 'user_id', 'due_at', 'paid_amount'])]
class PurchaseOrder extends Model
{
    /**
     * Order lifecycle statuses.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_RECEIVED = 'received';

    /**
     * Payment statuses for supplier orders.
     */
    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PAID = 'paid';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'paid_amount' => 'decimal:2',
        ];
    }

    /**
     * Whether this order was placed on credit (payment due later).
     */
    public function isCredit(): bool
    {
        return $this->due_at !== null;
    }

    /**
     * The outstanding balance of the order.
     */
    public function balance(): float
    {
        $total = (float) $this->receivedAmountTtc();

        return round(max($total - (float) $this->paid_amount, 0), 2);
    }

    /**
     * The payment status of the order.
     */
    public function paymentStatus(): string
    {
        $balance = $this->balance();

        if ($balance <= 0.01) {
            return self::PAYMENT_PAID;
        }

        if ((float) $this->paid_amount > 0.01) {
            return self::PAYMENT_PARTIAL;
        }

        return self::PAYMENT_UNPAID;
    }

    /**
     * Human-readable payment status label.
     */
    public function paymentStatusLabel(): string
    {
        return match ($this->paymentStatus()) {
            self::PAYMENT_PAID => 'Soldé',
            self::PAYMENT_PARTIAL => 'Acompte versé',
            self::PAYMENT_UNPAID => 'Impayé',
        };
    }

    /**
     * Whether the order is past its due date.
     */
    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && $this->balance() > 0.01;
    }

    /**
     * The TTC amount of the received quantities (for supplier debt calculation).
     */
    public function receivedAmountTtc(): float
    {
        $ht = $this->receivedAmountHt();
        $tax = $this->taxAmount($ht);

        return round($ht + $tax, 2);
    }

    /**
     * The supplier of the order.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The warehouse where the order is received.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * The user who recorded the reception.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The tax rate applied to the purchase order.
     *
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * The tax rate as a percentage (0 when none).
     */
    public function taxPercent(): float
    {
        return (float) $this->tax_percent;
    }

    /**
     * The tax amount on a given tax-exclusive amount.
     */
    public function taxAmount(float $amountHt): float
    {
        $rate = $this->taxPercent();

        if ($rate <= 0 || $amountHt <= 0) {
            return 0.0;
        }

        return round($amountHt * $rate / 100, 2);
    }

    /**
     * The estimated tax-exclusive amount of the whole order (ordered quantities
     * × current purchase price).
     */
    public function amountHt(): float
    {
        return round((float) $this->items->sum(
            fn (PurchaseOrderItem $item): float => (int) $item->quantity * (float) $item->product->purchase_price,
        ), 2);
    }

    /**
     * The estimated tax-exclusive amount of the received quantities.
     */
    public function receivedAmountHt(): float
    {
        return round((float) $this->items->sum(
            fn (PurchaseOrderItem $item): float => (int) $item->received_quantity * (float) $item->product->purchase_price,
        ), 2);
    }

    /**
     * The products received in this order.
     *
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * The payments made on this order.
     *
     * @return HasMany<SupplierPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
