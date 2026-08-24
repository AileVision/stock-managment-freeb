<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string|null $message
 * @property string|null $link
 * @property array|null $data
 * @property bool $is_read
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'type', 'title', 'message', 'link', 'data', 'is_read', 'read_at'])]
class Notification extends Model
{
    public const TYPE_STOCK_LOW = 'stock_low';
    public const TYPE_STOCK_OUT = 'stock_out';
    public const TYPE_RECEIVABLE_OVERDUE = 'receivable_overdue';
    public const TYPE_SUPPLIER_OVERDUE = 'supplier_overdue';

    public const TYPES = [
        self::TYPE_STOCK_LOW => 'Stock bas',
        self::TYPE_STOCK_OUT => 'Rupture de stock',
        self::TYPE_RECEIVABLE_OVERDUE => 'Créance en retard',
        self::TYPE_SUPPLIER_OVERDUE => 'Dettes fournisseurs en retard',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * The user who receives this notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the notification as read.
     */
    public function markRead(): void
    {
        if (! $this->is_read) {
            $this->update(['is_read' => true, 'read_at' => now()]);
        }
    }

    /**
     * Human-readable type label.
     */
    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
