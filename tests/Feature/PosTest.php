<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductSize;
use App\Enums\SalesChannel;
use App\Models\CounterItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Abah Admin']);
    }

    public function test_an_admin_can_choose_todays_counter_items(): void
    {
        [$pineapple, $egg, $lemon] = Product::factory()->count(3)->create();
        CounterItem::create(['sale_date' => today()->toDateString(), 'product_id' => $lemon->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.pos.menu.update'), ['day' => 'today', 'product_ids' => [$pineapple->id, $egg->id]])
            ->assertRedirect(route('admin.pos.index'));

        $this->assertEqualsCanonicalizing([$pineapple->id, $egg->id], CounterItem::productIdsOn(today()));
    }

    public function test_an_admin_can_set_how_many_of_each_item_to_bring_to_the_counter(): void
    {
        [$pineapple, $egg] = Product::factory()->count(2)->create(['stock' => 50]);
        CounterItem::create(['sale_date' => today()->toDateString(), 'product_id' => $pineapple->id, 'quantity' => 5]);

        $this->actingAs($this->admin)
            ->put(route('admin.pos.menu.update'), [
                'day' => 'today',
                'product_ids' => [$pineapple->id, $egg->id],
                'quantities' => [$pineapple->id => '20', $egg->id => ''],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$pineapple->id => 20, $egg->id => null], CounterItem::quantitiesOn(today())->sortKeys()->all() + []);
        $this->assertSame(1, CounterItem::query()->where('product_id', $pineapple->id)->count());

        $this->put(route('admin.pos.menu.update'), [
            'day' => 'today',
            'product_ids' => [$pineapple->id],
            'quantities' => [$pineapple->id => '-3'],
        ])->assertSessionHasErrors('quantities.'.$pineapple->id);
    }

    public function test_an_admin_can_choose_how_many_of_each_size_to_bring(): void
    {
        $product = Product::factory()->withSizes(['S' => 10, 'M' => 18, 'L' => 30], ['S' => 20, 'M' => 20, 'L' => 2])->create(['name' => 'Pineapple Tart']);

        $this->actingAs($this->admin)->put(route('admin.pos.menu.update'), [
            'day' => 'today',
            'product_ids' => [$product->id],
            'size_quantities' => [$product->id => ['S' => '4', 'M' => '', 'L' => '6']],
        ])->assertSessionHasNoErrors();

        $item = CounterItem::sole();
        $this->assertSame(['S' => 4, 'M' => null, 'L' => 6], $item->size_quantities);
        $this->assertNull($item->quantity);

        $this->get(route('admin.pos.menu'))->assertSee('name="size_quantities['.$product->id.'][S]"', false);

        $this->post(route('admin.pos.store'), [
            'items' => [['product_id' => $product->id, 'size' => 'S', 'quantity' => 3]],
            'payment_method' => 'card',
        ])->assertSessionHasNoErrors();

        $this->get(route('admin.pos.index'))
            ->assertSee('1 of 4')
            ->assertSee('data-size="M" data-size-stock="20"', false)
            ->assertSee('data-size="L" data-size-stock="2"', false);

        $this->post(route('admin.pos.store'), [
            'items' => [['product_id' => $product->id, 'size' => 'S', 'quantity' => 2]],
            'payment_method' => 'card',
        ])->assertSessionHasErrors(['items' => 'Only 1 of Pineapple Tart (Small) left at the counter.']);

        $this->put(route('admin.pos.menu.update'), [
            'day' => 'today',
            'product_ids' => [$product->id],
            'size_quantities' => [$product->id => ['S' => '4', 'M' => '5', 'L' => '1']],
        ]);

        $this->assertSame(10, CounterItem::sole()->quantity);
    }

    public function test_the_counter_cannot_sell_more_than_was_brought_out(): void
    {
        $product = Product::factory()->withSizes(['S' => 10, 'L' => 30], ['S' => 25, 'L' => 25])->create(['name' => 'Pineapple Tart']);
        CounterItem::create(['sale_date' => today()->toDateString(), 'product_id' => $product->id, 'quantity' => 5]);

        $this->actingAs($this->admin);

        $this->post(route('admin.pos.store'), [
            'items' => [['product_id' => $product->id, 'size' => 'S', 'quantity' => 2], ['product_id' => $product->id, 'size' => 'L', 'quantity' => 1]],
            'payment_method' => 'card',
        ])->assertSessionHasNoErrors();

        $this->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('2 of 5 left')
            ->assertSee('3 sold')
            ->assertSee('data-stock="2"', false);

        $this->post(route('admin.pos.store'), [
            'items' => [['product_id' => $product->id, 'size' => 'S', 'quantity' => 3]],
            'payment_method' => 'card',
        ])->assertSessionHasErrors(['items' => 'Only 2 of Pineapple Tart left at the counter.']);

        $this->assertSame(47, $product->fresh()->stock);
        $this->assertSame(1, Order::count());

        $this->get(route('admin.pos.menu'))->assertSee('3 sold at the counter today');
    }

    public function test_the_counter_limit_never_exceeds_the_real_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        CounterItem::create(['sale_date' => today()->toDateString(), 'product_id' => $product->id, 'quantity' => 10]);

        $this->assertSame(3, CounterItem::sellableToday($product));

        $this->actingAs($this->admin)
            ->get(route('admin.pos.menu'))
            ->assertSee('More than the 3 in stock');
    }

    public function test_online_orders_do_not_use_up_the_counter_allowance(): void
    {
        $product = Product::factory()->create(['price' => 10, 'stock' => 50]);
        CounterItem::create(['sale_date' => today()->toDateString(), 'product_id' => $product->id, 'quantity' => 4]);
        OrderItem::factory()->for(Order::factory())->forProduct($product, 3)->create();

        $this->assertSame(4, CounterItem::sellableToday($product));
    }

    public function test_tomorrows_items_can_be_planned_by_copying_today(): void
    {
        $products = Product::factory()->count(2)->create();
        foreach ($products as $product) {
            CounterItem::create(['sale_date' => today()->toDateString(), 'product_id' => $product->id]);
        }

        $this->actingAs($this->admin)
            ->put(route('admin.pos.menu.update'), ['day' => 'tomorrow', 'copy_previous' => '1'])
            ->assertRedirect(route('admin.pos.menu', ['day' => 'tomorrow']));

        $this->assertEqualsCanonicalizing($products->pluck('id')->all(), CounterItem::productIdsOn(today()->addDay()));
        $this->assertCount(2, CounterItem::productIdsOn(today()));
    }

    public function test_the_counter_menu_page_lists_active_products(): void
    {
        $product = Product::factory()->create(['name' => 'Pandan Tart']);
        Product::factory()->inactive()->create(['name' => 'Hidden Tart']);

        $this->actingAs($this->admin)
            ->get(route('admin.pos.menu', ['day' => 'tomorrow']))
            ->assertOk()
            ->assertSee('Pandan Tart')
            ->assertDontSee('Hidden Tart')
            ->assertSee(today()->addDay()->format('l, j F'));

        $this->assertNotNull($product);
    }

    public function test_the_pos_shows_only_todays_items_once_they_are_set(): void
    {
        $onCounter = Product::factory()->create(['name' => 'Counter Tart']);
        Product::factory()->create(['name' => 'Online Only Tart']);

        $this->actingAs($this->admin)->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('You haven’t picked today’s counter items yet', false)
            ->assertSee('Online Only Tart');

        CounterItem::create(['sale_date' => today()->toDateString(), 'product_id' => $onCounter->id]);

        $this->get(route('admin.pos.index'))
            ->assertSee('Counter Tart')
            ->assertDontSee('Online Only Tart')
            ->assertSee('Show all products');

        $this->get(route('admin.pos.index', ['all' => 1]))->assertSee('Online Only Tart');
    }

    public function test_a_cash_sale_is_recorded_as_a_completed_counter_order(): void
    {
        $plain = Product::factory()->create(['name' => 'Egg Tart', 'price' => 4, 'stock' => 20]);
        $sized = Product::factory()->withSizes(['S' => 10, 'L' => 30], ['S' => 5, 'L' => 5])->create(['name' => 'Pineapple Tart']);

        $response = $this->actingAs($this->admin)->post(route('admin.pos.store'), [
            'items' => [
                ['product_id' => $plain->id, 'quantity' => 3],
                ['product_id' => $sized->id, 'size' => 'L', 'quantity' => 1],
                ['product_id' => $sized->id, 'size' => 'S', 'quantity' => 2],
            ],
            'discount' => '2',
            'payment_method' => PaymentMethod::Cash->value,
            'amount_tendered' => '100',
            'customer_name' => 'Aunty Mei',
        ]);

        $order = Order::sole();
        $response->assertRedirect(route('admin.pos.receipt', $order));

        $this->assertSame(SalesChannel::Counter, $order->channel);
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNull($order->user_id);
        $this->assertSame('Aunty Mei', $order->customerName());
        $this->assertSame($this->admin->id, $order->served_by);
        $this->assertEquals(62.00, (float) $order->subtotal);
        $this->assertEquals(2.00, (float) $order->discount);
        $this->assertEquals(60.00, (float) $order->total);
        $this->assertEquals(40.00, $order->changeGiven());
        $this->assertSame(ProductSize::Large, $order->items->firstWhere('unit_price', 30)->size);
        $this->assertSame(17, $plain->fresh()->stock);
        $this->assertSame(7, $sized->fresh()->stock);

        $this->get(route('admin.pos.receipt', $order))
            ->assertOk()
            ->assertSee('Pineapple Tart (Large)')
            ->assertSee('60.00')
            ->assertSee('40.00')
            ->assertSee('Abah Admin');
    }

    public function test_e_wallet_sales_need_no_cash_amount(): void
    {
        $product = Product::factory()->create(['price' => 12, 'stock' => 5]);

        $this->actingAs($this->admin)->post(route('admin.pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => PaymentMethod::EWallet->value,
        ])->assertSessionHasNoErrors();

        $order = Order::sole();

        $this->assertSame(PaymentMethod::EWallet, $order->payment_method);
        $this->assertNull($order->changeGiven());
        $this->assertSame('Walk-in customer', $order->customerName());
    }

    public function test_not_enough_cash_is_refused(): void
    {
        $product = Product::factory()->create(['price' => 12, 'stock' => 5]);

        $this->actingAs($this->admin)->post(route('admin.pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'amount_tendered' => '20',
        ])->assertSessionHasErrors('amount_tendered');

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_a_sale_cannot_take_more_than_a_size_has_in_stock(): void
    {
        $product = Product::factory()->withSizes(stock: ['S' => 1, 'M' => 5, 'L' => 5])->create(['name' => 'Pineapple Tart']);

        $this->actingAs($this->admin)->post(route('admin.pos.store'), [
            'items' => [
                ['product_id' => $product->id, 'size' => 'S', 'quantity' => 2],
                ['product_id' => $product->id, 'size' => 'M', 'quantity' => 2],
            ],
            'payment_method' => 'card',
        ])->assertSessionHasErrors(['items' => 'Only 1 of Pineapple Tart (Small) in stock.']);

        $this->assertSame(0, Order::count());
        $this->assertSame(['S' => 1, 'M' => 5, 'L' => 5], $product->fresh()->sizeStock());
    }

    public function test_sizes_must_match_what_the_product_offers(): void
    {
        $sized = Product::factory()->withSizes(['S' => 10])->create();
        $plain = Product::factory()->create();

        $this->actingAs($this->admin);
        $this->post(route('admin.pos.store'), ['items' => [['product_id' => $sized->id, 'quantity' => 1]], 'payment_method' => 'card'])
            ->assertSessionHasErrors('items');
        $this->post(route('admin.pos.store'), ['items' => [['product_id' => $plain->id, 'size' => 'S', 'quantity' => 1]], 'payment_method' => 'card'])
            ->assertSessionHasErrors('items');
        $this->post(route('admin.pos.store'), ['items' => [], 'payment_method' => 'card'])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    public function test_counter_sales_appear_in_admin_orders_and_can_be_refunded(): void
    {
        $product = Product::factory()->create(['price' => 10, 'stock' => 5]);

        $this->actingAs($this->admin)->post(route('admin.pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'card',
        ]);

        $order = Order::sole();

        $this->get(route('admin.orders.index'))->assertOk()->assertSee('Walk-in customer');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Counter sale')->assertSee('Issue a refund');
        $this->get(route('admin.pos.index'))->assertOk()->assertSee($order->code)->assertSee('1 sale');

        $this->post(route('admin.refunds.store', $order), ['reason' => 'quality', 'amount' => '10', 'method' => 'cash'])
            ->assertSessionHasNoErrors();

        $this->get(route('admin.refunds.index'))->assertOk()->assertSee('Walk-in customer');
        $this->assertEquals(10.00, $order->fresh()->refundableAmount());
    }

    public function test_online_orders_have_no_counter_receipt(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.pos.receipt', $order))->assertNotFound();
    }

    public function test_customers_cannot_use_the_counter(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.pos.index'))->assertForbidden();
        $this->post(route('admin.pos.store'), [])->assertForbidden();
        $this->get(route('admin.pos.menu'))->assertForbidden();
    }
}
