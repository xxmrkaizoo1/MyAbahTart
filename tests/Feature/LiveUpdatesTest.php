<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_sees_a_new_version_when_their_order_moves_on(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $someoneElsesOrder = Order::factory()->create();
        $this->actingAs($customer);

        $before = $this->getJson(route('live'))->assertOk()->assertJson(['incoming' => null])->json('version');

        $this->travel(5)->seconds();
        $someoneElsesOrder->transitionTo(OrderStatus::Preparing);
        $this->assertSame($before, $this->getJson(route('live'))->json('version'));

        $order->transitionTo(OrderStatus::Preparing);
        $this->assertNotSame($before, $this->getJson(route('live'))->json('version'));
    }

    public function test_admins_are_told_how_many_new_orders_are_waiting(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->count(2)->create();
        Order::factory()->status(OrderStatus::Preparing)->create();

        $this->actingAs($admin)->getJson(route('live'))->assertOk()->assertJson(['incoming' => 2]);

        Order::factory()->create();
        $this->getJson(route('live'))->assertJson(['incoming' => 3]);
    }

    public function test_guests_cannot_poll(): void
    {
        $this->getJson(route('live'))->assertUnauthorized();
    }

    public function test_polling_releases_unpaid_orders_even_without_a_cron_job(): void
    {
        config([
            'services.billplz.key' => 'billplz-api-key',
            'services.billplz.collection_id' => 'col_123',
            'services.billplz.x_signature' => 'signature-key',
        ]);
        Http::fake(['www.billplz-sandbox.com/*' => Http::response(['id' => 'bill_old', 'paid' => false, 'amount' => 4500])]);

        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'total' => 45,
            'payment_method' => PaymentMethod::Online,
            'payment_status' => PaymentStatus::Unpaid,
            'billplz_bill_id' => 'bill_old',
        ]);
        $order->forceFill(['created_at' => now()->subHour()])->save();

        $this->actingAs($customer)->getJson(route('live'))->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_pages_mark_the_sections_that_update_live(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();

        $this->actingAs($customer)->get(route('orders.show', $order))
            ->assertSee('data-live-url="'.route('live').'"', false)
            ->assertSee('data-live="order-head"', false)
            ->assertSee('data-live="order-progress"', false);
        $this->get(route('orders.index'))->assertSee('data-live="orders"', false);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.orders.index'))->assertSee('data-live="board"', false)->assertSee('data-live="nav-orders"', false);
        $this->get(route('admin.orders.show', $order))->assertSee('data-live="order-status"', false);
        $this->get(route('admin.dashboard'))->assertSee('data-live="kpis"', false);
    }
}
