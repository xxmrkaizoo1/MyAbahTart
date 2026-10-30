<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_check_out_their_cart(): void
    {
        $user = User::factory()->create();
        $pineapple = Product::factory()->create(['price' => 45, 'stock' => 10]);
        $egg = Product::factory()->create(['price' => 24, 'stock' => 5]);

        $this->actingAs($user)
            ->post(route('cart.store', $pineapple), ['quantity' => 2])
            ->assertSessionHas('status');
        $this->post(route('cart.store', $egg));

        $response = $this->post(route('checkout.store'), [
            'delivery_method' => DeliveryMethod::Standard->value,
            'phone' => '0123456789',
            'address' => 'No. 1, Jalan Mawar, Shah Alam',
        ]);

        $order = Order::sole();
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::Received, $order->status);
        $this->assertEquals(114.00, (float) $order->subtotal);
        $this->assertEquals(5.00, (float) $order->delivery_fee);
        $this->assertEquals(119.00, (float) $order->total);
        $this->assertCount(2, $order->items);
        $this->assertSame(8, $pineapple->fresh()->stock);
        $this->assertSame(4, $egg->fresh()->stock);
        $this->assertSame('No. 1, Jalan Mawar, Shah Alam', $user->fresh()->address);

        $this->get(route('cart.index'))->assertSee('Your cart is empty');
    }

    public function test_standard_delivery_is_free_above_the_threshold_and_vouchers_apply(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        Voucher::factory()->percent(10)->create(['code' => 'RAYA10', 'min_subtotal' => 100]);

        $this->actingAs($user)->post(route('cart.store', $product), ['quantity' => 3]);
        $this->post(route('cart.voucher.store'), ['code' => 'raya10'])->assertSessionHasNoErrors();

        $this->post(route('checkout.store'), [
            'delivery_method' => DeliveryMethod::Standard->value,
            'phone' => '0123456789',
            'address' => 'Shah Alam',
        ]);

        $order = Order::sole();
        $this->assertEquals(150.00, (float) $order->subtotal);
        $this->assertEquals(0.00, (float) $order->delivery_fee);
        $this->assertEquals(15.00, (float) $order->discount);
        $this->assertEquals(135.00, (float) $order->total);
        $this->assertSame('RAYA10', $order->voucher_code);
    }

    public function test_expired_vouchers_are_rejected(): void
    {
        Voucher::factory()->expired()->create(['code' => 'LAMA']);
        $product = Product::factory()->create();

        $this->post(route('cart.store', $product));

        $this->post(route('cart.voucher.store'), ['code' => 'LAMA'])->assertSessionHasErrors('code');
    }

    public function test_pickup_orders_do_not_need_an_address(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->actingAs(User::factory()->create())->post(route('cart.store', $product));

        $this->post(route('checkout.store'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'phone' => '0123456789',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Order::sole()->address);
    }

    public function test_delivery_orders_require_an_address(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->actingAs(User::factory()->create())->post(route('cart.store', $product));

        $this->post(route('checkout.store'), [
            'delivery_method' => DeliveryMethod::Express->value,
            'phone' => '0123456789',
        ])->assertSessionHasErrors('address');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_cart_never_holds_more_than_the_available_stock(): void
    {
        $product = Product::factory()->create(['stock' => 2]);

        $this->post(route('cart.store', $product), ['quantity' => 5]);

        $this->get(route('cart.index'))->assertOk()->assertSee('value="2"', false);
    }

    public function test_sold_out_products_cannot_be_added(): void
    {
        $product = Product::factory()->soldOut()->create();

        $this->post(route('cart.store', $product))->assertSessionHas('error');

        $this->get(route('cart.index'))->assertSee('Your cart is empty');
    }

    public function test_checkout_fails_when_stock_ran_out_after_adding_to_cart(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->actingAs(User::factory()->create())->post(route('cart.store', $product), ['quantity' => 3]);
        $product->update(['stock' => 1]);

        $this->post(route('checkout.store'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'phone' => '0123456789',
        ])->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_a_customer_can_cancel_a_new_order_and_stock_is_returned(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($user)->post(route('cart.store', $product), ['quantity' => 2]);
        $this->post(route('checkout.store'), ['delivery_method' => 'pickup', 'phone' => '0123456789']);

        $order = Order::sole();
        $this->assertSame(3, $product->fresh()->stock);

        $this->post(route('orders.cancel', $order), ['cancellation_reason' => 'changed_mind'])->assertSessionHas('status');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_customers_cannot_view_other_customers_orders(): void
    {
        $order = Order::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('orders.show', $order))
            ->assertNotFound();
    }
}
