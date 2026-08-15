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
 * @property int|null $customer_id
 * @property int|null $user_id
 * @property string $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property string|null $customer_address
 * @property string $status
 * @property string $discount_percent
 * @property string $tax_percent
 * @property int|null $tax_rate_id
 * @property Carbon|null $valid_until
 * @property string|null $notes
 * @property int|null $sale_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read TaxRate|null $taxRate
 * @property-read User|null $user
 * @property-read Collection<int, QuoteItem> $items
 * @property-read Sale|null $sale
 */
#[Fillable(['reference', 'customer_id', 'user_id', 'customer_name', 'customer_email', 'customer_phone', 'customer_address', 'status', 'discount_percent', 'tax_rate_id', 'tax_percent', 'valid_until', 'notes', 'sale_id'])]
class Quote extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
        ];
    }

    /**
     * The tax rate applied to the quote.
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

        $total = round((float) $this->items->sum(fn (QuoteItem $item): float => $item->quantity * (float) $item->unit_price), 2);
        $total -= round($total * (float) $this->discount_percent / 100, 2);

        return round($total - $total / (1 + $rate / 100), 2);
    }

    /**
     * Available statuses of a quote.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Statuses that can still be edited or deleted.
     *
     * @var array<int, string>
     */
    public const EDITABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_REJECTED,
    ];

    /**
     * The customer record linked to this quote, if any.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The user who created the quote.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The items of the quote.
     *
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * The sale created when the quote was converted.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
