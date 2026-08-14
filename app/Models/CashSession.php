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
 * @property int|null $user_id
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property string $opening_cash
 * @property string|null $expected_cash
 * @property string|null $counted_cash
 * @property string|null $difference
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, Sale> $sales
 */
#[Fillable(['user_id', 'opened_at', 'closed_at', 'opening_cash', 'expected_cash', 'counted_cash', 'difference', 'notes'])]
class CashSession extends Model
{
    /**
     * Whether the session is still open.
     */
    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * The user who opened the session.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The sales recorded during the session.
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'session_id');
    }
}
