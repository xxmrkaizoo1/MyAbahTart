<?php

namespace Database\Seeders;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class ShopDemoSeeder extends Seeder
{
    /**
     * Seed demo accounts, six weeks of orders and product reviews.
     */
    public function run(): void
    {
        mt_srand(2026);

        User::factory()->admin()->create([
            'name' => 'Abah Admin',
            'email' => 'firasumairisgood@gmail.com',
            'phone' => '0123456789',
            'password' => Hash::make('123456789'),
        ]);

        $sophia = User::factory()->create([
            'name' => 'Sophia Tan',
            'email' => 'customer@myabahtart.test',
            'phone' => '0198765432',
            'address' => '18, Jalan Telawi 3, Bangsar Baru, 59100 Kuala Lumpur',
        ]);

        $customers = collect([
            'Aiden Lim', 'Priya Nair', 'Hannah Wong', 'Marcus Lee', 'Nadia Rahman',
            'Jacob Ong', 'Chloe Yap', 'Ethan Cheah', 'Zara Iskandar',
        ])->map(fn (string $name) => User::factory()->create([
            'name' => $name,
            'address' => fake()->buildingNumber().', Jalan '.fake()->lastName().', Kuala Lumpur',
        ]))->push($sophia);

        $products = Product::all();
        $weights = $products->mapWithKeys(fn (Product $product) => [
            $product->id => match ($product->art) {
                'pineapple' => 10,
                'egg' => 8,
                'lemon', 'pistachio' => 6,
                'chocolate', 'caramel' => 4,
                default => 3,
            },
        ]);

        for ($daysAgo = 41; $daysAgo >= 0; $daysAgo--) {
            $day = today()->subDays($daysAgo);
            $weekendBoost = in_array($day->dayOfWeekIso, [5, 6, 7], true) ? 4 : 0;
            $growth = (int) round((41 - $daysAgo) / 10);
            $orderCount = 5 + $weekendBoost + $growth + mt_rand(0, 3);

            for ($index = 0; $index < $orderCount; $index++) {
                $placedAt = $day->copy()->setTime(mt_rand(9, 20), mt_rand(0, 59));

                if ($daysAgo === 0 && $placedAt->isFuture()) {
                    $placedAt = now()->subMinutes(mt_rand(5, 300));
                }

                $this->createOrder($customers->random(), $products, $weights, $placedAt, $this->statusFor($daysAgo, $placedAt));
            }
        }

        $this->createOrder($sophia, $products, $weights, now()->subMinutes(25), OrderStatus::Preparing);

        $this->seedReviews($customers, $products);
    }

    private function statusFor(int $daysAgo, Carbon $placedAt): OrderStatus
    {
        if ($daysAgo > 0) {
            return mt_rand(1, 100) <= 4 ? OrderStatus::Cancelled : OrderStatus::Completed;
        }

        $minutesAgo = $placedAt->diffInMinutes(now());

        return match (true) {
            $minutesAgo < 30 => OrderStatus::Received,
            $minutesAgo < 75 => OrderStatus::Preparing,
            $minutesAgo < 120 => OrderStatus::Ready,
            default => OrderStatus::Completed,
        };
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, int>  $weights
     */
    private function createOrder(User $customer, Collection $products, Collection $weights, Carbon $placedAt, OrderStatus $status): void
    {
        $lines = collect(range(1, mt_rand(1, 3)))
            ->map(fn () => $this->weightedPick($products, $weights))
            ->unique('id')
            ->map(fn (Product $product) => [
                'product' => $product,
                'quantity' => $product->unit_label === 'Single' ? mt_rand(2, 6) : mt_rand(1, 2),
            ]);

        $subtotal = round($lines->sum(fn (array $line) => (float) $line['product']->price * $line['quantity']), 2);
        $deliveryMethod = collect(DeliveryMethod::cases())->random();
        $deliveryFee = $deliveryMethod->feeFor($subtotal);
        $discount = $subtotal >= 60 && mt_rand(1, 5) === 1 ? 10.0 : 0.0;

        $order = Order::create([
            'user_id' => $customer->id,
            'status' => $status,
            'delivery_method' => $deliveryMethod,
            'phone' => $customer->phone,
            'address' => $deliveryMethod->requiresAddress() ? ($customer->address ?? fake()->address()) : null,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'discount' => $discount,
            'total' => round($subtotal - $discount + $deliveryFee, 2),
            'voucher_code' => $discount > 0 ? 'WELCOME10' : null,
        ]);

        $order->forceFill([
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
            'preparing_at' => in_array($status, [OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed], true) ? $placedAt->copy()->addMinutes(8) : null,
            'ready_at' => in_array($status, [OrderStatus::Ready, OrderStatus::Completed], true) ? $placedAt->copy()->addMinutes(45) : null,
            'completed_at' => $status === OrderStatus::Completed ? $placedAt->copy()->addMinutes(mt_rand(60, 180)) : null,
            'cancelled_at' => $status === OrderStatus::Cancelled ? $placedAt->copy()->addMinutes(10) : null,
        ])->save();

        foreach ($lines as $line) {
            $order->items()->create([
                'product_id' => $line['product']->id,
                'product_name' => $line['product']->name,
                'unit_price' => $line['product']->price,
                'quantity' => $line['quantity'],
                'line_total' => round((float) $line['product']->price * $line['quantity'], 2),
            ]);
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, int>  $weights
     */
    private function weightedPick(Collection $products, Collection $weights): Product
    {
        $roll = mt_rand(1, $weights->sum());

        foreach ($products as $product) {
            $roll -= $weights[$product->id];

            if ($roll <= 0) {
                return $product;
            }
        }

        return $products->first();
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, Product>  $products
     */
    private function seedReviews(Collection $customers, Collection $products): void
    {
        $comments = [
            5 => [
                'Honestly the best tart I’ve had in KL. The pastry just melts — I ordered a second box the same week.',
                'Beautifully packed and arrived perfect. Made a lovely gift for my in-laws.',
                'Premium taste and worth every ringgit. Even my picky kids finished theirs!',
                'So fresh and fragrant. Delivery was quick and the tracking updates were spot on.',
                'Perfect balance of sweet and buttery. This is my new weekend ritual.',
            ],
            4 => [
                'Delicious and not too sweet. Would love a bigger box option.',
                'Consistent quality every time — just sells out fast on weekends.',
                'Crisp shell, generous filling. Goes so well with an afternoon coffee.',
            ],
            3 => [
                'Tasty, but I wished the filling was a little more generous.',
            ],
        ];

        foreach ($products as $product) {
            foreach ($customers->shuffle()->take(mt_rand(2, 4)) as $customer) {
                $rating = [5, 5, 5, 4, 4, 3][mt_rand(0, 5)];

                Review::create([
                    'product_id' => $product->id,
                    'user_id' => $customer->id,
                    'rating' => $rating,
                    'comment' => $comments[$rating][array_rand($comments[$rating])],
                ])->forceFill(['created_at' => now()->subDays(mt_rand(1, 30))])->save();
            }
        }
    }
}
