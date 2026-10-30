<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoReviewsSeeder extends Seeder
{
    /**
     * Products that already have this many reviews are left alone.
     */
    public const MINIMUM_REVIEWS = 4;

    /**
     * Comments that suit a kind of product, matched against its name.
     *
     * @var array<string, array<int, list<string>>>
     */
    private const COMMENTS = [
        'tart' => [
            5 => [
                'Pastry is so buttery and melts in the mouth. Jem nenas pun tak terlalu manis, memang sedap!',
                'Ordered for Raya open house and everything was finished in one afternoon. Will order again.',
                'Neatly packed, no broken pieces at all. My mum says this is the best tart she has had.',
                'Rasa macam tart buatan mak. Fresh and fragrant, worth every ringgit.',
            ],
            4 => [
                'Very nice tart, the filling is generous. Wish the large size came in a bigger jar.',
                'Sedap dan cantik. Delivery took a bit longer but it was worth the wait.',
            ],
            3 => [
                'Taste is good but a few pieces were a bit soft by the time it arrived.',
            ],
        ],
        'rempeyek' => [
            5 => [
                'Rangup gila! Lots of ikan bilis and kacang in every piece. Habis satu balang dalam dua hari.',
                'Crispy, not oily at all and the seasoning is just right. Perfect with nasi lemak.',
                'Rempeyek paling sedap pernah beli online. Packing balang pun kemas.',
            ],
            4 => [
                'Very crunchy and tasty. A few pieces were broken but still sedap.',
                'Good portion for the price. My kids love it as a snack.',
            ],
            3 => [
                'Sedap tapi agak masin sikit for my taste.',
            ],
        ],
        'kerepek' => [
            5 => [
                'Kerepek ni memang rangup and fresh, not stale like the ones in the shop.',
                'Addictive! Thin, crispy and the flavour is just nice. Bought three more for the office.',
                'Fresh and crunchy, nicely sealed so it stays crispy for days.',
            ],
            4 => [
                'Crunchy and tasty, good for movie nights. Will try the other flavours next.',
                'Sedap, cuma nak lagi banyak dalam satu pek hehe.',
            ],
            3 => [
                'Okay taste, a little too sweet for me.',
            ],
        ],
        'default' => [
            5 => [
                'Excellent quality and fast delivery. Highly recommended!',
                'Sedap sangat, packing kemas and the seller is very responsive.',
                'Fresh, tasty and nicely packed. Will definitely order again.',
            ],
            4 => [
                'Good quality for the price. Delivery was on time.',
                'Very nice, my family enjoyed it. Would love more variety.',
            ],
            3 => [
                'Not bad, but I expected a bit more for the price.',
            ],
        ],
    ];

    /**
     * Give every product a handful of customer reviews, preferring customers who actually ordered it.
     */
    public function run(): void
    {
        $customers = User::where('role', UserRole::Customer)->get();

        if ($customers->isEmpty()) {
            $this->command?->warn('Add some customers first, reviews need someone to write them.');

            return;
        }

        foreach (Product::withCount('reviews')->get() as $product) {
            if ($product->reviews_count >= self::MINIMUM_REVIEWS) {
                continue;
            }

            $buyers = $customers->filter(fn (User $customer) => $customer->orders()
                ->where('status', OrderStatus::Completed)
                ->whereHas('items', fn ($items) => $items->where('product_id', $product->id))
                ->exists());

            $reviewers = $buyers->shuffle()
                ->concat($customers->diff($buyers)->shuffle())
                ->reject(fn (User $customer) => Review::where('product_id', $product->id)->where('user_id', $customer->id)->exists())
                ->take(fake()->numberBetween(self::MINIMUM_REVIEWS, 7) - $product->reviews_count);

            $comments = $this->commentsFor($product);

            foreach ($reviewers as $reviewer) {
                $rating = fake()->randomElement([5, 5, 5, 5, 4, 4, 4, 3]);

                Review::create([
                    'product_id' => $product->id,
                    'user_id' => $reviewer->id,
                    'rating' => $rating,
                    'comment' => $this->unusedComment($product, $comments[$rating]),
                ])->forceFill(['created_at' => now()->subDays(fake()->numberBetween(1, 28))->subMinutes(fake()->numberBetween(0, 600))])->save();
            }
        }
    }

    /**
     * A comment this product doesn't already show, falling back to any when they've all been used.
     *
     * @param  list<string>  $choices
     */
    private function unusedComment(Product $product, array $choices): string
    {
        $unused = array_diff($choices, $product->reviews()->pluck('comment')->all());

        return fake()->randomElement($unused ?: $choices);
    }

    /**
     * @return array<int, list<string>>
     */
    private function commentsFor(Product $product): array
    {
        foreach (array_keys(self::COMMENTS) as $kind) {
            if (Str::contains($product->name, $kind, ignoreCase: true)) {
                return self::COMMENTS[$kind];
            }
        }

        return self::COMMENTS['default'];
    }
}
