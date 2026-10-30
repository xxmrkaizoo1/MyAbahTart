<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_runs_on_malaysia_time(): void
    {
        $this->assertSame('Asia/Kuala_Lumpur', config('app.timezone'));
        $this->assertSame('+08:00', now()->format('P'));
    }

    public function test_order_times_are_shown_in_malaysia_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 05:14:00', 'UTC'));
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();

        $this->actingAs($customer)->get(route('orders.show', $order))->assertSee('1:14 pm');
    }
}
