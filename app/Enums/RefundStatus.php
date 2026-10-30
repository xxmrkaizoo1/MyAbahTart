<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Awaiting review',
            self::Approved => 'Refunded',
            self::Rejected => 'Declined',
        };
    }

    /**
     * Tailwind classes used for status badges.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Requested => 'bg-butter-100 text-butter-600 ring-butter-500/30',
            self::Approved => 'bg-mint-50 text-mint-700 ring-mint-600/20',
            self::Rejected => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        };
    }
}
