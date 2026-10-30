<?php

namespace App\Enums;

enum CancellationReason: string
{
    case Address = 'address';
    case OutOfStock = 'out_of_stock';
    case Unreachable = 'unreachable';
    case Payment = 'payment';
    case ShopClosed = 'shop_closed';
    case CustomerRequest = 'customer_request';
    case ChangedMind = 'changed_mind';
    case OrderedByMistake = 'ordered_by_mistake';
    case WrongDetails = 'wrong_details';
    case TakingTooLong = 'taking_too_long';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Address => 'Address problem or outside our delivery area',
            self::OutOfStock => 'Item sold out',
            self::Unreachable => 'Couldn’t reach the customer',
            self::Payment => 'Payment problem',
            self::ShopClosed => 'Kitchen closed or too busy',
            self::CustomerRequest => 'Customer asked to cancel',
            self::ChangedMind => 'Changed my mind',
            self::OrderedByMistake => 'Ordered the wrong items or quantity',
            self::WrongDetails => 'Wrong address or phone number',
            self::TakingTooLong => 'It’s taking too long',
            self::Other => 'Something else',
        };
    }

    /**
     * The reasons the shop picks from when it cancels an order.
     *
     * @return list<self>
     */
    public static function forShop(): array
    {
        return [self::Address, self::OutOfStock, self::Unreachable, self::Payment, self::ShopClosed, self::CustomerRequest, self::Other];
    }

    /**
     * The reasons a customer picks from when they cancel their own order.
     *
     * @return list<self>
     */
    public static function forCustomers(): array
    {
        return [self::ChangedMind, self::OrderedByMistake, self::WrongDetails, self::TakingTooLong, self::Other];
    }
}
