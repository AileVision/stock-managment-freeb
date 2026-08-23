<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $reference
 * @property string $category
 * @property string $amount
 * @property string $payment_method
 * @property Carbon $expense_date
 * @property string|null $description
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'reference', 'category', 'amount', 'payment_method', 'expense_date', 'description', 'notes'])]
class Expense extends Model
{
    /**
     * Predefined expense categories for a Togolese retail business.
     */
    public const CATEGORIES = [
        'loyer' => 'Loyer',
        'salaires' => 'Salaires',
        'electricite' => 'Électricité',
        'eau' => 'Eau',
        'transport' => 'Transport',
        'fournitures' => 'Fournitures de bureau',
        'entretien' => 'Entretien & réparations',
        'communication' => 'Communication & internet',
        'fiscalite' => 'Fiscalité & taxes',
        'divers' => 'Divers',
    ];

    /**
     * Payment methods for expenses.
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
            'expense_date' => 'date',
        ];
    }

    /**
     * The user who recorded the expense.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The human-readable label for a category slug.
     */
    public static function categoryLabel(string $slug): string
    {
        return self::CATEGORIES[$slug] ?? $slug;
    }

    /**
     * Whether this expense was paid via mobile money.
     */
    public function isMobilePayment(): bool
    {
        return in_array($this->payment_method, self::MOBILE_PAYMENT_METHODS, true);
    }
}
