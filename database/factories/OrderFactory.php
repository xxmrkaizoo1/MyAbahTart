<?php

namespace Database\Factories;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 20, 200);

        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Received,
            'delivery_method' => DeliveryMethod::Pickup,
            'phone' => '012-3456789',
            'address' => null,
            'subtotal' => $subtotal,
            'delivery_fee' => 0,
            'discount' => 0,
            'total' => $subtotal,
        ];
    }

    /**
     * Indicate that the order has the given status.
     */
    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Indicate that the order was handed over to the customer.
     */
    public function completed(?Carbon $at = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Completed,
            'completed_at' => $at ?? now(),
        ]);
    }
}
