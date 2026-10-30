<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Received = 'received';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Order Received',
            self::Preparing => 'Being Prepared',
            self::Ready => 'Ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Received => 'New',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Tailwind classes used for status badges.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Received => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            self::Preparing => 'bg-butter-100 text-butter-600 ring-butter-500/30',
            self::Ready => 'bg-mint-50 text-mint-700 ring-mint-600/20',
            self::Completed => 'bg-plum-900/5 text-plum-600 ring-plum-600/15',
            self::Cancelled => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        };
    }

    /**
     * The verb shown on the button that moves an order into this status.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Preparing => 'Start preparing',
            self::Ready => 'Mark ready',
            self::Completed => 'Complete',
            self::Cancelled => 'Cancel',
            self::Received => 'Receive',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Received => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready => self::Completed,
            default => null,
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Received, self::Preparing, self::Ready], true);
    }

    /**
     * The statuses shown on the customer's progress timeline.
     *
     * @return list<self>
     */
    public static function timeline(): array
    {
        return [self::Received, self::Preparing, self::Ready, self::Completed];
    }
}
