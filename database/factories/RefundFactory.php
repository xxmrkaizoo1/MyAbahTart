<?php

namespace Database\Factories;

use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => RefundStatus::Requested,
            'reason' => fake()->randomElement(RefundReason::cases()),
            'details' => fake()->sentence(),
            'amount' => 0,
        ];
    }

    /**
     * Indicate that the refund has been paid out.
     */
    public function approved(float $amount = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::Approved,
            'amount' => $amount,
            'method' => RefundMethod::Cash,
            'processed_at' => now(),
        ]);
    }
}
