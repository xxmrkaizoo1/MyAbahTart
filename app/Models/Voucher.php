<?php

namespace App\Models;

use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'description', 'type', 'value', 'min_subtotal', 'is_active', 'expires_at'])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENT = 'percent';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', strtoupper(trim($code)))->first();
    }

    public function isUsable(): bool
    {
        return $this->is_active && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function discountFor(float $subtotal): float
    {
        if (! $this->isUsable() || $subtotal < (float) $this->min_subtotal) {
            return 0.0;
        }

        $discount = $this->type === self::TYPE_PERCENT
            ? $subtotal * (float) $this->value / 100
            : (float) $this->value;

        return round(min($discount, $subtotal), 2);
    }

    public function valueLabel(): string
    {
        return $this->type === self::TYPE_PERCENT
            ? rtrim(rtrim($this->value, '0'), '.').'%'
            : 'RM '.number_format((float) $this->value, 2);
    }
}
