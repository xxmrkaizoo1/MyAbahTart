<?php

namespace App\Enums;

use App\Services\Billplz;
use App\Services\Storefront;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case EWallet = 'e_wallet';
    case Card = 'card';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::EWallet => 'E-wallet / QR',
            self::Card => 'Card',
            self::Online => 'Online (Billplz)',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'ticket',
            self::EWallet => 'phone',
            self::Card => 'tag',
            self::Online => 'lock',
        };
    }

    /**
     * The name shown to customers choosing how to pay for an online order.
     */
    public function checkoutLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash on delivery',
            self::Online => 'Online payment',
            default => $this->label(),
        };
    }

    public function checkoutDescription(): string
    {
        return match ($this) {
            self::Cash => 'Pay in cash when your order arrives or when you collect it',
            self::Online => 'FPX online banking, cards and e-wallets, secured by Billplz',
            default => '',
        };
    }

    /**
     * The ways customers can pay online orders right now, as switched on by the admin.
     *
     * @return list<self>
     */
    public static function forCheckout(): array
    {
        $storefront = app(Storefront::class);
        $methods = [];

        if ($storefront->get('payment_online_enabled') === '1' && app(Billplz::class)->isConfigured()) {
            $methods[] = self::Online;
        }

        if ($storefront->get('payment_cod_enabled') === '1' || $methods === []) {
            $methods[] = self::Cash;
        }

        return $methods;
    }

    /**
     * Whether online payment is switched on by the admin but Billplz is not connected yet.
     */
    public static function onlineComingSoon(): bool
    {
        return app(Storefront::class)->get('payment_online_enabled') === '1' && ! app(Billplz::class)->isConfigured();
    }

    /**
     * The ways a customer can pay at the shop counter.
     *
     * @return list<self>
     */
    public static function forCounter(): array
    {
        return [self::Cash, self::EWallet, self::Card];
    }
}
