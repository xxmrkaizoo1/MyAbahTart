<?php

namespace App\Enums;

use App\Services\Storefront;

enum DeliveryMethod: string
{
    case Standard = 'standard';
    case Express = 'express';
    case Pickup = 'pickup';

    /**
     * The name admins gave this option on the Delivery page.
     */
    public function label(): string
    {
        return $this->setting('label');
    }

    public function description(): string
    {
        return $this->setting('description');
    }

    public function icon(): string
    {
        return match ($this) {
            self::Standard => 'truck',
            self::Express => 'bolt',
            self::Pickup => 'store',
        };
    }

    /**
     * The fee set by the admin. Standard delivery is free once the order reaches the free-delivery amount.
     */
    public function feeFor(float $subtotal): float
    {
        $threshold = self::freeDeliveryThreshold();

        if ($this === self::Standard && $threshold !== null && $subtotal >= $threshold) {
            return 0.0;
        }

        return round((float) $this->setting('fee'), 2);
    }

    /**
     * Whether customers can choose this option at checkout.
     */
    public function isEnabled(): bool
    {
        return $this->setting('enabled') === '1';
    }

    public function requiresAddress(): bool
    {
        return $this !== self::Pickup;
    }

    public function readyLabel(): string
    {
        return $this === self::Pickup ? 'Ready for Pickup' : 'Out for Delivery';
    }

    /**
     * The options customers can choose at checkout.
     *
     * @return list<self>
     */
    public static function enabledCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method) => $method->isEnabled()));
    }

    /**
     * The order subtotal from which standard delivery is free, or null when it never is.
     */
    public static function freeDeliveryThreshold(): ?float
    {
        $threshold = (float) app(Storefront::class)->get('delivery_free_over');

        return $threshold > 0 && self::Standard->isEnabled() ? $threshold : null;
    }

    private function setting(string $field): string
    {
        return app(Storefront::class)->get("delivery_{$this->value}_{$field}");
    }
}
