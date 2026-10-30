<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\DemoCustomersSeeder;
use Database\Seeders\DemoReviewsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCustomersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_twenty_customers_with_orders_that_add_up(): void
    {
        Product::factory()->withSizes(['S' => 20, 'M' => 25, 'L' => 30])->create();
        Product::factory()->create(['price' => 35]);

        $this->seed(DemoCustomersSeeder::class);

        $this->assertSame(20, User::where('role', UserRole::Customer)->count());
        $this->assertTrue(User::where('role', UserRole::Customer)->get()->every(fn (User $customer) => $customer->orders()->exists()));
        $this->assertGreaterThan(0, Order::where('status', OrderStatus::Received)->count());

        Order::with('items')->get()->each(function (Order $order) {
            $this->assertEqualsWithDelta((float) $order->subtotal, $order->items->sum(fn (OrderItem $item) => (float) $item->line_total), 0.01);
            $this->assertEqualsWithDelta((float) $order->total, (float) $order->subtotal + (float) $order->delivery_fee, 0.01);
        });

        $this->post(route('login'), ['login' => 'customer1@example.com', 'password' => DemoCustomersSeeder::PASSWORD]);
        $this->assertAuthenticatedAs(User::firstWhere('email', 'customer1@example.com'));
        $this->get(route('orders.index'))->assertOk();
    }

    public function test_every_product_gets_reviews_that_match_what_it_is(): void
    {
        $tart = Product::factory()->create(['name' => 'Tart Lejen']);
        $kerepek = Product::factory()->create(['name' => 'Kerepek Bawang']);
        $this->seed(DemoCustomersSeeder::class);

        $this->seed(DemoReviewsSeeder::class);
        $reviews = Review::count();
        $this->seed(DemoReviewsSeeder::class);

        $this->assertGreaterThanOrEqual(4, $tart->reviews()->count());
        $this->assertGreaterThanOrEqual(4, $kerepek->reviews()->count());
        $this->assertTrue($kerepek->reviews->every(fn (Review $review) => ! str_contains($review->comment, 'tart') && $review->rating >= 3));
        $this->assertSame($reviews, Review::count());

        $this->get(route('products.show', $tart))->assertOk()->assertSee($tart->reviews()->first()->comment);
    }

    public function test_running_it_again_does_not_duplicate_customers_or_their_history(): void
    {
        Product::factory()->create();

        $this->seed(DemoCustomersSeeder::class);
        $orders = Order::count();

        $this->seed(DemoCustomersSeeder::class);

        $this->assertSame(20, User::where('role', UserRole::Customer)->count());
        $this->assertSame($orders, Order::count());
    }
}
