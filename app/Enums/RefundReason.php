<?php

namespace App\Enums;

enum RefundReason: string
{
    case Damaged = 'damaged';
    case WrongItem = 'wrong_item';
    case MissingItem = 'missing_item';
    case Quality = 'quality';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'Arrived damaged',
            self::WrongItem => 'Wrong item or size',
            self::MissingItem => 'Item missing',
            self::Quality => 'Not fresh / quality issue',
            self::Other => 'Something else',
        };
    }
}
