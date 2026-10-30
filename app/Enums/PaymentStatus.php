<?php

namespace App\Enums;

/**
 * Whether an online order has been paid through the payment gateway.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Awaiting payment',
            self::Paid => 'Paid',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-butter-100 text-butter-600 ring-butter-500/30',
            self::Paid => 'bg-mint-50 text-mint-700 ring-mint-600/20',
        };
    }
}
