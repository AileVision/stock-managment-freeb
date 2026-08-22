<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Quote> $quotes
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, Sale> $sales
 * @property-read Collection<int, CustomerPayment> $payments
 * @property-read Collection<int, SaleReturn> $returns
 * @property-read Collection<int, CreditNote> $creditNotes
 */
#[Fillable(['name', 'email', 'phone', 'address', 'notes'])]
class Customer extends Model
{
    /**
     * The quotes addressed to this customer.
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * The invoices issued for this customer.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The sales made to this customer (credit sales mainly).
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * The payments received from this customer.
     *
     * @return HasMany<CustomerPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * The returns recorded for this customer.
     *
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * The credit notes (avoirs) granted to this customer.
     *
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * The total value of the credit notes still available for this customer.
     */
    public function availableAvoirs(): float
    {
        return round((float) $this->creditNotes->sum(
            fn (CreditNote $note): float => $note->remaining(),
        ), 2);
    }

    /**
     * The unpaid balance owed by this customer across all credit sales.
     */
    public function outstandingBalance(): float
    {
        return round((float) $this->sales->sum(
            fn (Sale $sale): float => max($sale->balance(), 0),
        ), 2);
    }

    /**
     * Consume the customer's available avoirs (oldest first) to settle a sale
     * or record a payment, and log a CustomerPayment of type « avoir ».
     */
    public function consumeAvoirs(float $amount, Sale $sale, int $userId): void
    {
        $notes = CreditNote::query()
            ->where('customer_id', $this->id)
            ->where('status', CreditNote::STATUS_ISSUED)
            ->whereRaw('used_amount < amount')
            ->orderBy('issued_at')
            ->lockForUpdate()
            ->get();

        $remaining = $amount;

        foreach ($notes as $note) {
            if ($remaining <= 0.01) {
                break;
            }

            $usable = min($note->remaining(), $remaining);
            $note->increment('used_amount', $usable);

            if ($note->remaining() <= 0.01) {
                $note->update(['status' => CreditNote::STATUS_USED]);
            }

            $remaining = round($remaining - $usable, 2);
        }

        $sale->payments()->create([
            'customer_id' => $this->id,
            'user_id' => $userId,
            'amount' => $amount,
            'payment_method' => 'avoir',
            'notes' => 'Règlement par avoir',
            'paid_at' => now(),
        ]);
    }

    /**
     * The part of the outstanding balance that is past due.
     */
    public function overdueBalance(): float
    {
        return round((float) $this->sales->sum(
            fn (Sale $sale): float => $sale->isOverdue() ? $sale->balance() : 0.0,
        ), 2);
    }
}
