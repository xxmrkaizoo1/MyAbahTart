<?php

namespace App\Enums;

enum ProductSize: string
{
    case Small = 'S';
    case Medium = 'M';
    case Large = 'L';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small',
            self::Medium => 'Medium',
            self::Large => 'Large',
        };
    }
}
