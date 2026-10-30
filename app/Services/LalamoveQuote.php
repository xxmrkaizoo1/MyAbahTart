<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * A Lalamove price for one trip from the shop to a customer.
 */
readonly class LalamoveQuote
{
    public function __construct(
        public string $quotationId,
        public float $price,
        public ?float $distanceKm,
        public CarbonInterface $expiresAt,
    ) {}

    /**
     * @return array{price: float, distance_km: float|null, expires_at: string}
     */
    public function toArray(): array
    {
        return [
            'price' => $this->price,
            'distance_km' => $this->distanceKm,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }
}
