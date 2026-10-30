<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case EWallet = 'e_wallet';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::EWallet => 'E-wallet / QR',
            self::Card => 'Card',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'ticket',
            self::EWallet => 'phone',
            self::Card => 'tag',
        };
    }
}
