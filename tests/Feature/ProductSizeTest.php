<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\ProductSize;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_set_how_many_pieces_come_in_each_size(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Pineapple Tart',
            'description' => 'Buttery pastry with pineapple jam.',
            'size_prices' => ['S' => '15', 'M' => '28', 'L' => ''],
            'size_stock' => ['S' => '5', 'M' => '5', 'L' => ''],
            'size_pieces' => ['S' => '6', 'M' => '', 'L' => '50'],
            'size_grams' => ['S' => '250', 'M' => '500', 'L' => '900'],
            'unit_label' => 'Box',
            'low_stock_threshold' => 2,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $product = Product::firstWhere('name', 'Pineapple Tart');

        $this->assertSame(['S' => 6], $product->sizePieces());
        $this->assertSame(['S' => 250, 'M' => 500], $product->sizeGrams());
        $this->assertSame('Small · 6 pcs · 250 g', $product->sizeLabel(ProductSize::Small));
        $this->assertSame('Medium · 500 g', $product->sizeLabel(ProductSize::Medium));

        $this->get(route('products.show', $product))->assertSee('6 pcs · 250 g')->assertSee('500 g');

        $this->post(route('cart.store', $product), ['size' => 'S']);
        $this->get(route('cart.index'))->assertSee('Size S · Small · 6 pcs · 250 g');
    }

    public function test_an_admin_can_set_an_optional_weight_for_the_whole_product(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Kuih Bangkit',
            'description' => 'Melt-in-the-mouth coconut cookies.',
            'price' => '18',
            'unit_label' => 'Jar',
            'weight_grams' => '300',
            'stock' => 10,
            'low_stock_threshold' => 2,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $product = Product::firstWhere('name', 'Kuih Bangkit');

        $this->assertSame('Jar · 300 g', $product->unitLabel());
        $this->get(route('products.show', $product))->assertSee('Jar · 300 g');

        $product->update(['weight_grams' => null]);
        $this->assertSame('Jar', $product->unitLabel());
    }

    public function test_the_product_page_offers_each_size_with_its_price(): void
    {
        $product = Product::factory()->withSizes(['S' => 10, 'L' => 30])->create();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Choose a size')
            ->assertSee('RM 10.00')
            ->assertSee('RM 30.00')
            ->assertDontSee('value="M"', false);
    }

    public function test_sizes_are_added_to_the_cart_as_separate_lines_at_their_own_price(): void
    {
        $product = Product::factory()->withSizes()->create(['stock' => 20]);

        $this->post(route('cart.store', $product), ['size' => 'S', 'quantity' => 2])->assertSessionHas('status');
        $this->post(route('cart.store', $product), ['size' => 'L']);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Size S · Small')
            ->assertSee('Size L · Large')
            ->assertSee('RM 50.00');
    }

    public function test_a_sized_product_needs_one_of_its_offered_sizes(): void
    {
        $product = Product::factory()->withSizes(['S' => 10, 'M' => 18])->create();

        $this->post(route('cart.store', $product))->assertSessionHasErrors('size');
        $this->post(route('cart.store', $product), ['size' => 'L'])->assertSessionHasErrors('size');

        $this->get(route('cart.index'))->assertSee('Your cart is empty');
    }

    public function test_each_size_has_its_own_stock(): void
    {
        $product = Product::factory()->withSizes(stock: ['S' => 2, 'M' => 5, 'L' => 0])->create(['name' => 'Pineapple Tart']);

        $this->assertSame(7, $product->stock);

        $this->post(route('cart.store', $product), ['size' => 'S', 'quantity' => 3]);
        $this->post(route('cart.store', $product), ['size' => 'M', 'quantity' => 4]);
        $this->post(route('cart.store', $product), ['size' => 'L'])->assertSessionHas('error', 'Sorry, Pineapple Tart (Large) is sold out.');

        $this->get(route('cart.index'))->assertSee('6 items');
    }

    public function test_the_product_page_shows_stock_for_each_size(): void
    {
        $product = Product::factory()->withSizes(stock: ['S' => 0, 'M' => 3, 'L' => 40])->create(['low_stock_threshold' => 5]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Sold out')
            ->assertSee('Only 3 left')
            ->assertSee('40 in stock')
            ->assertSee('value="M" data-price="RM 18.00" data-stock="3" class="peer sr-only"  checked', false);
    }

    public function test_updating_and_removing_a_line_only_touches_that_size(): void
    {
        $product = Product::factory()->withSizes()->create(['stock' => 20]);

        $this->post(route('cart.store', $product), ['size' => 'S']);
        $this->post(route('cart.store', $product), ['size' => 'M']);

        $this->patch(route('cart.update', $product), ['size' => 'M', 'quantity' => 3]);
        $this->delete(route('cart.destroy', $product), ['size' => 'S']);

        $this->get(route('cart.index'))
            ->assertSee('3 items')
            ->assertSee('Size M · Medium')
            ->assertDontSee('Size S · Small');
    }

    public function test_checkout_records_each_size_and_takes_stock_from_the_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withSizes(stock: ['S' => 5, 'M' => 0, 'L' => 5])->create(['name' => 'Pineapple Tart']);

        $this->actingAs($user)->post(route('cart.store', $product), ['size' => 'S', 'quantity' => 2]);
        $this->post(route('cart.store', $product), ['size' => 'L']);

        $this->post(route('checkout.store'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'phone' => '0123456789',
        ])->assertSessionHasNoErrors();

        $order = Order::sole();
        $items = $order->items->keyBy(fn ($item) => $item->size->value);

        $this->assertEquals(50.00, (float) $order->subtotal);
        $this->assertSame(ProductSize::Small, $items['S']->size);
        $this->assertEquals(10.00, (float) $items['S']->unit_price);
        $this->assertEquals(30.00, (float) $items['L']->unit_price);
        $this->assertSame('Pineapple Tart (Large)', $items['L']->displayName());
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(['S' => 3, 'M' => 0, 'L' => 4], $product->fresh()->sizeStock());

        $this->get(route('orders.show', $order))->assertSee('Pineapple Tart (Small)');
    }

    public function test_checkout_rejects_a_size_that_ran_out_in_the_meantime(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withSizes(stock: ['S' => 3, 'M' => 3, 'L' => 3])->create();

        $this->actingAs($user)->post(route('cart.store', $product), ['size' => 'S', 'quantity' => 3]);
        $this->post(route('cart.store', $product), ['size' => 'M', 'quantity' => 3]);
        $product->update(['size_stock' => ['S' => 3, 'M' => 1, 'L' => 3]]);

        $this->post(route('checkout.store'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'phone' => '0123456789',
        ])->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::count());
    }

    public function test_an_admin_can_set_size_prices_and_the_base_price_follows_the_cheapest(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Egg Tart',
            'description' => 'Silky custard in a flaky shell.',
            'size_prices' => ['S' => '8.50', 'M' => '', 'L' => '22'],
            'size_stock' => ['S' => '10', 'M' => '99', 'L' => '4'],
            'unit_label' => 'Box',
            'art' => 'egg',
            'stock' => 30,
            'low_stock_threshold' => 5,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $product = Product::firstWhere('name', 'Egg Tart');

        $this->assertSame(['S' => 8.5, 'L' => 22.0], $product->sizePrices());
        $this->assertEquals(8.50, (float) $product->price);
        $this->assertSame(['S' => 10, 'L' => 4], $product->sizeStock());
        $this->assertSame(14, $product->stock);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Egg Tart',
            'description' => 'Silky custard in a flaky shell.',
            'price' => '12',
            'size_prices' => ['S' => '', 'M' => '', 'L' => ''],
            'unit_label' => 'Box',
            'art' => 'egg',
            'stock' => 30,
            'low_stock_threshold' => 5,
        ])->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertFalse($product->hasSizes());
        $this->assertEquals(12.00, (float) $product->price);
        $this->assertSame(30, $product->stock);
        $this->assertNull($product->size_stock);
    }

    public function test_an_admin_can_update_and_restock_each_size(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->withSizes(stock: ['S' => 1, 'M' => 1, 'L' => 1])->create(['name' => 'Pineapple Tart']);

        $this->actingAs($admin)
            ->patch(route('admin.products.stock', $product), ['size_stock' => ['S' => 8, 'M' => 0, 'L' => 2]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['S' => 8, 'M' => 0, 'L' => 2], $product->fresh()->sizeStock());
        $this->assertSame(10, $product->fresh()->stock);

        $this->post(route('admin.products.restock', $product), ['quantity' => 12, 'size' => 'M'])
            ->assertSessionHas('status', 'Received 12 × Pineapple Tart (Medium). Stock is now 12.');
        $this->post(route('admin.products.restock', $product), ['quantity' => 5])->assertSessionHasErrors('size');

        $this->assertSame(['S' => 8, 'M' => 12, 'L' => 2], $product->fresh()->sizeStock());
        $this->assertSame(22, $product->fresh()->stock);
    }

    public function test_a_cancelled_order_returns_stock_to_the_right_size(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withSizes(stock: ['S' => 5, 'M' => 5, 'L' => 5])->create();

        $this->actingAs($user)->post(route('cart.store', $product), ['size' => 'L', 'quantity' => 2]);
        $this->post(route('checkout.store'), ['delivery_method' => DeliveryMethod::Pickup->value, 'phone' => '0123456789']);

        $this->assertSame(['S' => 5, 'M' => 5, 'L' => 3], $product->fresh()->sizeStock());

        $this->post(route('orders.cancel', Order::sole()), ['cancellation_reason' => 'changed_mind']);

        $this->assertSame(['S' => 5, 'M' => 5, 'L' => 5], $product->fresh()->sizeStock());
        $this->assertSame(15, $product->fresh()->stock);
    }

    public function test_a_product_needs_a_price_or_at_least_one_size(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.products.store'), [
            'category_id' => Category::factory()->create()->id,
            'name' => 'Mystery Tart',
            'description' => 'Who knows.',
            'unit_label' => 'Box',
            'art' => 'egg',
            'stock' => 1,
            'low_stock_threshold' => 0,
        ])->assertSessionHasErrors('price');
    }

    public function test_each_size_can_have_its_own_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $form = [
            'category_id' => Category::factory()->create()->id,
            'name' => 'Egg Tart',
            'description' => 'Silky custard in a flaky shell.',
            'size_prices' => ['S' => '8', 'M' => '15', 'L' => '22'],
            'size_stock' => ['S' => '5', 'M' => '5', 'L' => '5'],
            'unit_label' => 'Box',
            'art' => 'egg',
            'low_stock_threshold' => 2,
            'is_active' => '1',
        ];

        $this->actingAs($admin)->post(route('admin.products.store'), [
            ...$form,
            'size_images' => ['S' => UploadedFile::fake()->image('small.jpg'), 'L' => UploadedFile::fake()->image('large.jpg')],
        ])->assertSessionHasNoErrors();

        $product = Product::firstWhere('name', 'Egg Tart');
        $smallPhoto = $product->size_images['S'];
        $largePhoto = $product->size_images['L'];

        Storage::disk('public')->assertExists([$smallPhoto, $largePhoto]);
        $this->assertNull($product->sizeImageUrl(ProductSize::Medium));
        $this->get(route('products.show', $product))->assertSee($smallPhoto)->assertSee($largePhoto)->assertSee('data-size-photo="L"', false);

        $this->post(route('cart.store', $product), ['size' => 'L']);
        $this->get(route('cart.index'))->assertSee($largePhoto);

        $this->put(route('admin.products.update', $product), [
            ...$form,
            'size_prices' => ['S' => '8', 'M' => '15', 'L' => ''],
            'remove_size_images' => ['S' => '1'],
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing([$smallPhoto, $largePhoto]);
        $this->assertNull($product->fresh()->size_images);
    }
}
