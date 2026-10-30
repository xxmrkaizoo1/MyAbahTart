<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Tart '.Str::title(fake()->unique()->words(2, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'tagline' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 5, 60),
            'unit_label' => 'Box of 12',
            'art' => fake()->randomElement(array_keys(Product::ARTS)),
            'stock' => fake()->numberBetween(20, 100),
            'low_stock_threshold' => 10,
            'is_active' => true,
            'is_bestseller' => false,
            'is_seasonal' => false,
        ];
    }

    /**
     * Indicate that the product cannot be ordered.
     */
    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    /**
     * Indicate that the product is hidden from customers.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the product is sold in sizes, each with its own price and stock.
     *
     * @param  array<string, float>  $prices  Prices keyed by size code (S, M, L).
     * @param  array<string, int>|null  $stock  Stock keyed by size code; defaults to 20 of each size.
     */
    public function withSizes(array $prices = ['S' => 10, 'M' => 18, 'L' => 30], ?array $stock = null): static
    {
        return $this->state(fn (array $attributes) => [
            'size_prices' => $prices,
            'size_stock' => $stock ?? array_map(fn () => 20, $prices),
            'price' => min($prices),
        ]);
    }
}
