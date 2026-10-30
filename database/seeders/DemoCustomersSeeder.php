<?php

namespace Database\Seeders;

use App\Enums\CancellationReason;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\ProductSize;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DemoCustomersSeeder extends Seeder
{
    /**
     * The password every demo customer signs in with.
     */
    public const PASSWORD = 'password';

    /**
     * @var list<array{0: string, 1: string}>
     */
    private const CUSTOMERS = [
        ['Nur Aisyah Binti Hassan', 'No. 12, Jalan Melati 3, Taman Melawati, 53100 Kuala Lumpur'],
        ['Muhammad Hafiz Bin Rahim', '27, Jalan SS 2/24, 47300 Petaling Jaya, Selangor'],
        ['Siti Nurhaliza Binti Ahmad', 'Lot 5, Jalan Kenari 7, Bandar Puchong Jaya, 47100 Puchong'],
        ['Ahmad Faizal Bin Osman', '33, Jalan Setia Prima U13/S, Setia Alam, 40170 Shah Alam'],
        ['Tan Mei Ling', 'B-8-3, Residensi Bangsar, Jalan Maarof, 59000 Kuala Lumpur'],
        ['Lim Wei Jie', '41, Jalan Kuchai Maju 6, Kuchai Lama, 58200 Kuala Lumpur'],
        ['Kavitha a/p Raman', '9, Jalan 17/29, Seksyen 17, 46400 Petaling Jaya'],
        ['Arjun s/o Selvam', '22, Jalan USJ 9/5Q, Subang Jaya, 47620 Selangor'],
        ['Nurul Izzah Binti Kamal', '15, Jalan Wangsa Delima 6, Wangsa Maju, 53300 Kuala Lumpur'],
        ['Mohd Amirul Bin Zainal', '78, Jalan Bukit Indah 2, Ampang, 68000 Selangor'],
        ['Chong Kar Wai', '6, Jalan Cheras Hartamas 3, 43200 Cheras, Selangor'],
        ['Farah Hanim Binti Yusof', '10, Jalan Kajang Perdana 2/1, 43000 Kajang'],
        ['Hakim Danial Bin Roslan', '54, Presint 9, Jalan P9C/2, 62250 Putrajaya'],
        ['Wong Siew Lan', '18, Jalan Desa 3/6, Taman Desa, 58100 Kuala Lumpur'],
        ['Aina Sofea Binti Ismail', '3, Jalan Rawang Perdana 4, 48000 Rawang, Selangor'],
        ['Syafiq Irfan Bin Azman', '29, Jalan Bangi Avenue 1/5, 43650 Bandar Baru Bangi'],
        ['Priya a/p Subramaniam', '45, Jalan Klang Lama Batu 4, 58000 Kuala Lumpur'],
        ['Ng Jia Hui', '12-2, Jalan Kepong Baru 7, 52100 Kepong, Kuala Lumpur'],
        ['Khairul Anuar Bin Mansor', '67, Jalan Seri Kembangan 8/2, 43300 Seri Kembangan'],
        ['Dayang Nur Batrisya Binti Halim', '20, Jalan Semenyih Indah 3, 43500 Semenyih'],
    ];

    /**
     * @var list<string>
     */
    private const NOTES = [
        'Please call when you arrive.',
        'Leave at the guardhouse, thank you!',
        'For a birthday, please pack nicely.',
        'Gate is blue, house number on the left pillar.',
        'Kalau boleh hantar sebelum 5 petang ya.',
    ];

    /**
     * Seed 20 demo customer accounts, each with a few weeks of orders on the current menu.
     */
    public function run(): void
    {
        $products = Product::available()->get();

        if ($products->isEmpty()) {
            $this->command?->warn('Add at least one product first, demo orders need something to sell.');

            return;
        }

        $password = Hash::make(self::PASSWORD);
        $seededHistory = false;

        foreach (self::CUSTOMERS as $index => [$name, $address]) {
            $customer = User::firstOrCreate(
                ['email' => 'customer'.($index + 1).'@example.com'],
                [
                    'name' => $name,
                    'phone' => '01'.fake()->randomElement(['1', '2', '3', '6', '7', '9']).fake()->numerify('#######'),
                    'address' => $address,
                    'password' => $password,
                    'email_verified_at' => now(),
                ],
            );

            if ($customer->orders()->exists()) {
                continue;
            }

            $customer->forceFill(['created_at' => now()->subDays(fake()->numberBetween(35, 90))])->save();
            $seededHistory = true;

            foreach (range(1, fake()->numberBetween(1, 6)) as $ignored) {
                $placedAt = now()->subDays(fake()->numberBetween(1, 30))->setTime(fake()->numberBetween(9, 21), fake()->numberBetween(0, 59));
                $status = fake()->numberBetween(1, 100) <= 8 ? OrderStatus::Cancelled : OrderStatus::Completed;

                $this->createOrder($customer, $products, $placedAt, $status);
            }
        }

        if ($seededHistory) {
            $this->seedTodaysOrders($products);
        }
    }

    /**
     * A handful of orders still moving through the kitchen today.
     *
     * @param  Collection<int, Product>  $products
     */
    private function seedTodaysOrders(Collection $products): void
    {
        $customers = User::whereIn('email', collect(range(1, count(self::CUSTOMERS)))->map(fn (int $number) => "customer{$number}@example.com"))->get();

        foreach ([[15, OrderStatus::Received], [40, OrderStatus::Received], [55, OrderStatus::Preparing], [80, OrderStatus::Preparing], [110, OrderStatus::Ready]] as [$minutesAgo, $status]) {
            $this->createOrder($customers->random(), $products, now()->subMinutes($minutesAgo), $status);
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function createOrder(User $customer, Collection $products, CarbonInterface $placedAt, OrderStatus $status): void
    {
        $lines = $products->shuffle()->take(fake()->numberBetween(1, min(3, $products->count())))->map(function (Product $product) {
            $size = $product->hasSizes() ? ProductSize::from(fake()->randomElement(array_keys($product->sizePrices()))) : null;
            $quantity = fake()->numberBetween(1, $product->price < 5 ? 6 : 3);
            $unitPrice = $product->priceFor($size);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'size' => $size,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => round($unitPrice * $quantity, 2),
            ];
        });

        $subtotal = round($lines->sum('line_total'), 2);
        $deliveryMethod = fake()->randomElement(DeliveryMethod::cases());
        $deliveryFee = $deliveryMethod->feeFor($subtotal);
        $cancellationReason = $status === OrderStatus::Cancelled
            ? fake()->randomElement([...CancellationReason::forCustomers(), CancellationReason::Address, CancellationReason::Unreachable])
            : null;

        $order = Order::create([
            'user_id' => $customer->id,
            'status' => $status,
            'delivery_method' => $deliveryMethod,
            'phone' => $customer->phone,
            'address' => $deliveryMethod->requiresAddress() ? $customer->address : null,
            'notes' => fake()->boolean(25) ? fake()->randomElement(self::NOTES) : null,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'discount' => 0,
            'total' => round($subtotal + $deliveryFee, 2),
            'cancellation_reason' => $cancellationReason,
            'cancellation_note' => $cancellationReason === CancellationReason::Other ? 'Plans changed, will order again next week.' : null,
        ]);

        $reached = fn (OrderStatus ...$statuses) => in_array($status, $statuses, true);

        $order->forceFill([
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
            'preparing_at' => $reached(OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed) ? $placedAt->copy()->addMinutes(8) : null,
            'ready_at' => $reached(OrderStatus::Ready, OrderStatus::Completed) ? $placedAt->copy()->addMinutes(45) : null,
            'completed_at' => $reached(OrderStatus::Completed) ? $placedAt->copy()->addMinutes(fake()->numberBetween(60, 180)) : null,
            'cancelled_at' => $reached(OrderStatus::Cancelled) ? $placedAt->copy()->addMinutes(12) : null,
        ])->save();

        $order->items()->createMany($lines->all());
    }
}
