<?php

namespace Tests\Feature;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ShopInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_orders_move_through_each_status_in_turn(): void
    {
        $order = Order::factory()->create();

        foreach ([OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $status) {
            $this->actingAs($this->admin)
                ->patch(route('admin.orders.update', $order), ['status' => $status->value])
                ->assertSessionHas('status');

            $this->assertSame($status, $order->fresh()->status);
        }

        $order->refresh();
        $this->assertNotNull($order->preparing_at);
        $this->assertNotNull($order->ready_at);
        $this->assertNotNull($order->completed_at);
    }

    public function test_statuses_cannot_be_skipped(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.update', $order), ['status' => OrderStatus::Completed->value])
            ->assertSessionHas('error');

        $this->assertSame(OrderStatus::Received, $order->fresh()->status);
    }

    public function test_cancelling_an_order_returns_its_stock(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        $order = Order::factory()->status(OrderStatus::Preparing)->create();
        OrderItem::factory()->for($order)->forProduct($product, 3)->create();

        $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), ['status' => 'cancelled', 'cancellation_reason' => 'out_of_stock']);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_cancelling_an_order_needs_a_reason_the_customer_can_see(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
            ->assertSee('data-dialog-open="cancel-order"', false)
            ->assertSee('Address problem or outside our delivery area');

        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors(['cancellation_reason' => 'Choose why the order is being cancelled.']);
        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled', 'cancellation_reason' => 'other'])
            ->assertSessionHasErrors(['cancellation_note' => 'Tell the customer what went wrong.']);

        $this->assertSame(OrderStatus::Received, $order->fresh()->status);

        $this->patch(route('admin.orders.update', $order), [
            'status' => 'cancelled',
            'cancellation_reason' => 'address',
            'cancellation_note' => 'We can’t deliver to Shah Alam yet.',
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(CancellationReason::Address, $order->cancellation_reason);

        $this->actingAs($order->user)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Address problem or outside our delivery area')
            ->assertSee('We can’t deliver to Shah Alam yet.');
    }

    public function test_a_customer_cancelling_their_own_order_gives_a_reason_the_shop_can_see(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($order->user)
            ->get(route('orders.show', $order))
            ->assertSee('Why are you cancelling?')
            ->assertSee('Changed my mind');

        $this->post(route('orders.cancel', $order), ['cancellation_reason' => 'wrong_details', 'cancellation_note' => 'Wrong house number.'])
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(CancellationReason::WrongDetails, $order->cancellation_reason);
        $this->assertSame('Wrong house number.', $order->cancellation_note);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $order))
            ->assertSee('Wrong address or phone number')
            ->assertSee('Wrong house number.');
    }

    public function test_a_customer_must_choose_one_of_their_cancellation_reasons(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($order->user)->post(route('orders.cancel', $order))->assertSessionHasErrors('cancellation_reason');
        $this->post(route('orders.cancel', $order), ['cancellation_reason' => 'out_of_stock'])->assertSessionHasErrors('cancellation_reason');
        $this->post(route('orders.cancel', $order), ['cancellation_reason' => 'other'])->assertSessionHasErrors('cancellation_note');

        $this->assertSame(OrderStatus::Received, $order->fresh()->status);
    }

    public function test_an_admin_can_create_a_product(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Tart Kelapa Pandan',
            'description' => 'Kelapa muda dengan kastard pandan.',
            'price' => 12.5,
            'unit_label' => 'Sebiji',
            'art' => 'matcha',
            'stock' => 30,
            'low_stock_threshold' => 5,
            'is_active' => '1',
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::firstWhere('slug', 'tart-kelapa-pandan');
        $this->assertNotNull($product);
        $this->assertTrue($product->is_active);
        $this->assertFalse($product->is_seasonal);
    }

    public function test_an_admin_can_update_stock_quickly(): void
    {
        $product = Product::factory()->create(['stock' => 2]);

        $this->actingAs($this->admin)->patch(route('admin.products.stock', $product), ['stock' => 40]);

        $this->assertSame(40, $product->fresh()->stock);
    }

    public function test_receiving_stock_from_the_agent_adds_to_the_product(): void
    {
        $product = Product::factory()->create(['stock' => 4]);

        $this->actingAs($this->admin)
            ->post(route('admin.products.restock', $product), ['quantity' => 25])
            ->assertSessionHas('status');

        $this->assertSame(29, $product->fresh()->stock);

        $this->actingAs($this->admin)
            ->post(route('admin.products.restock', $product), ['quantity' => 0])
            ->assertSessionHasErrors('quantity');
    }

    public function test_reorder_suggestions_flag_products_that_will_sell_out_soon(): void
    {
        $healthy = Product::factory()->create(['stock' => 20, 'low_stock_threshold' => 10]);
        $runningOut = Product::factory()->create(['stock' => 6, 'low_stock_threshold' => 2]);
        $soldOut = Product::factory()->soldOut()->create();
        Product::factory()->create(['stock' => 30]);

        $order = Order::factory()->status(OrderStatus::Completed)->create();
        OrderItem::factory()->for($order)->forProduct($healthy, 28)->create();
        OrderItem::factory()->for($order)->forProduct($runningOut, 28)->create();

        $suggestions = app(ShopInsights::class)->reorderSuggestions()->keyBy(fn (array $row) => $row['product']->id);

        $this->assertSame([$soldOut->id, $runningOut->id], $suggestions->keys()->all());
        $this->assertTrue($suggestions[$soldOut->id]['urgent']);
        $this->assertFalse($suggestions[$runningOut->id]['urgent']);
        $this->assertSame(3.0, $suggestions[$runningOut->id]['days_left']);
        $this->assertSame(25, $suggestions[$runningOut->id]['quantity']);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Reorder from agent')->assertSee($runningOut->name);
    }

    public function test_an_admin_can_create_and_toggle_a_voucher(): void
    {
        $this->actingAs($this->admin)->post(route('admin.vouchers.store'), [
            'code' => 'merdeka20',
            'description' => 'Diskaun Merdeka',
            'type' => 'percent',
            'value' => 20,
        ])->assertSessionHasNoErrors();

        $voucher = Voucher::firstWhere('code', 'MERDEKA20');
        $this->assertTrue($voucher->is_active);

        $this->patch(route('admin.vouchers.update', $voucher));

        $this->assertFalse($voucher->fresh()->is_active);
    }
}
