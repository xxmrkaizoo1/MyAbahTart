<?php

namespace Database\Factories;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('????##')),
            'description' => fake()->sentence(4),
            'type' => Voucher::TYPE_FIXED,
            'value' => 10,
            'min_subtotal' => 0,
            'is_active' => true,
            'expires_at' => null,
        ];
    }

    /**
     * Indicate that the voucher takes a percentage off the subtotal.
     */
    public function percent(float $value): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Voucher::TYPE_PERCENT,
            'value' => $value,
        ]);
    }

    /**
     * Indicate that the voucher has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
