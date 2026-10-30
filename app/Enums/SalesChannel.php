<?php

namespace App\Enums;

enum SalesChannel: string
{
    case Online = 'online';
    case Counter = 'counter';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Counter => 'Counter',
        };
    }
}
