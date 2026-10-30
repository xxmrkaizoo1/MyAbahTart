<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Seed collections, tarts and vouchers.
     */
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Classics', 'art' => 'pineapple'],
            ['name' => 'Fruit & Citrus', 'art' => 'lemon'],
            ['name' => 'Chocolate & Nuts', 'art' => 'chocolate'],
            ['name' => 'Savoury', 'art' => 'savory'],
            ['name' => 'Limited Edition', 'art' => 'pistachio'],
        ])->mapWithKeys(fn (array $category, int $index) => [
            $category['name'] => Category::create([
                ...$category,
                'slug' => Str::slug($category['name']),
                'sort_order' => $index,
            ]),
        ]);

        $products = [
            ['Classics', 'Golden Pineapple Tart', 'Slow-cooked Morris pineapple jam', 48.00, 'Box of 40', 'pineapple', 110, true, false,
                'Our signature since day one. Morris pineapples are simmered for eight hours into a tangy-sweet jam, then tucked into a melt-in-your-mouth butter pastry. No preservatives, baked in small batches every morning.'],
            ['Classics', 'Portuguese Egg Tart', 'Flaky, caramelised custard', 26.00, 'Box of 6', 'egg', 60, true, false,
                'Hundreds of paper-thin pastry layers wrapped around a silky egg custard, blasted in a hot oven until the top blisters and caramelises. Best enjoyed warm.'],
            ['Classics', 'Salted Caramel Tart', 'Gooey caramel, sea salt flakes', 14.00, 'Single', 'caramel', 40, false, false,
                'A crisp shortcrust shell filled with slow-cooked butter caramel and finished with flaky sea salt. Rich, glossy and dangerously moreish.'],
            ['Fruit & Citrus', 'Lemon Meringue Tart', 'Sharp lemon curd, torched meringue', 13.50, 'Single', 'lemon', 35, true, false,
                'Zingy curd made from fresh lemons and plenty of butter, crowned with a cloud of Italian meringue that we torch to order.'],
            ['Fruit & Citrus', 'Very Berry Custard Tart', 'Fresh berries on vanilla cream', 15.00, 'Single', 'berry', 30, false, false,
                'Vanilla bean pastry cream piled high with strawberries, blueberries and raspberries, finished with a light apricot glaze.'],
            ['Fruit & Citrus', 'Mango Passionfruit Tart', 'Tropical and tangy', 14.00, 'Single', 'mango', 7, false, false,
                'Ripe mango slices fanned over a passionfruit cream. Bright, juicy and the perfect afternoon pick-me-up.'],
            ['Chocolate & Nuts', 'Dark Chocolate Ganache Tart', '70% cacao, cocoa nib crunch', 16.00, 'Single', 'chocolate', 45, false, false,
                'A deep, velvety ganache made with 70% single-origin chocolate in a cocoa shortcrust, topped with crunchy cocoa nibs.'],
            ['Chocolate & Nuts', 'Maple Pecan Tart', 'Toasted pecans, maple caramel', 15.50, 'Single', 'nutty', 28, false, false,
                'Whole toasted pecans set in a sticky maple and brown-butter caramel. Nutty, warm and comforting.'],
            ['Chocolate & Nuts', 'Hazelnut Lava Box', 'Molten hazelnut centres', 38.00, 'Box of 12', 'chocolate', 5, false, false,
                'Bite-sized chocolate tarts with a molten hazelnut centre that oozes when you bite in. Made for sharing — or not.'],
            ['Savoury', 'Mushroom & Thyme Quiche', 'Buttery crust, creamy filling', 11.00, 'Single', 'savory', 30, false, false,
                'Roasted mushrooms, fresh thyme and gruyère in a silky egg custard. A hearty breakfast or light lunch.'],
            ['Savoury', 'Smoky Cheddar Tart', 'Aged cheddar with chilli flakes', 32.00, 'Box of 12', 'cheese', 24, false, false,
                'Mature cheddar and a pinch of smoked chilli in buttery pastry cups. The savoury partner to every sweet box.'],
            ['Limited Edition', 'Pistachio Rose Tart', 'Sicilian pistachio, rose cream', 18.00, 'Single', 'pistachio', 22, false, true,
                'Our limited-run showstopper: Sicilian pistachio frangipane under a delicate rose cream, scattered with crushed pistachios and petals. Only a few dozen made each day.'],
            ['Limited Edition', 'Matcha White Chocolate Tart', 'Uji matcha, white ganache', 16.50, 'Single', 'matcha', 20, false, false,
                'Earthy Uji matcha meets creamy white chocolate ganache for a balanced, not-too-sweet tart.'],
            ['Limited Edition', 'Earl Grey Lavender Tart', 'Floral tea-infused cream', 15.00, 'Single', 'lavender', 18, false, false,
                'Earl Grey infused cream with a hint of lavender, finished with a whipped vanilla dollop. Gentle, fragrant and elegant.'],
        ];

        foreach ($products as $index => [$category, $name, $tagline, $price, $unit, $art, $stock, $bestseller, $seasonal, $description]) {
            Product::create([
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'tagline' => $tagline,
                'description' => $description,
                'price' => $price,
                'unit_label' => $unit,
                'art' => $art,
                'stock' => $stock,
                'low_stock_threshold' => 10,
                'is_active' => true,
                'is_bestseller' => $bestseller,
                'is_seasonal' => $seasonal,
            ])->forceFill([
                'created_at' => now()->subDays(70 - $index * 5),
                'updated_at' => now()->subDays(70 - $index * 5),
            ])->save();
        }

        Voucher::create([
            'code' => 'WELCOME10',
            'description' => 'RM10 off orders over RM60',
            'type' => Voucher::TYPE_FIXED,
            'value' => 10,
            'min_subtotal' => 60,
        ]);

        Voucher::create([
            'code' => 'SWEET15',
            'description' => '15% off orders over RM100',
            'type' => Voucher::TYPE_PERCENT,
            'value' => 15,
            'min_subtotal' => 100,
            'expires_at' => now()->addMonths(2),
        ]);

        Voucher::create([
            'code' => 'TREAT5',
            'description' => 'RM5 off any order over RM30',
            'type' => Voucher::TYPE_FIXED,
            'value' => 5,
            'min_subtotal' => 30,
        ]);
    }
}
