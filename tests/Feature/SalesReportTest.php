<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use App\Services\ChannelSales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private Product $pineapple;

    private Product $egg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pineapple = Product::factory()->create(['name' => 'Pineapple Tart', 'price' => 10]);
        $this->egg = Product::factory()->create(['name' => 'Egg Tart', 'price' => 5]);

        $online = $this->sale(SalesChannel::Online, 100, ['delivery_method' => DeliveryMethod::Express]);
        OrderItem::factory()->for($online)->forProduct($this->pineapple, 10)->create();
        Refund::factory()->for($online)->approved(20)->create();

        $counter = $this->sale(SalesChannel::Counter, 30, ['payment_method' => PaymentMethod::Cash]);
        OrderItem::factory()->for($counter)->forProduct($this->egg, 6)->create();

        $card = $this->sale(SalesChannel::Counter, 15, ['payment_method' => PaymentMethod::Card]);
        OrderItem::factory()->for($card)->forProduct($this->egg, 3)->create();

        $this->sale(SalesChannel::Counter, 999, ['status' => OrderStatus::Cancelled]);
        $this->sale(SalesChannel::Online, 500, ['created_at' => now()->subDays(40)]);
    }

    public function test_each_channel_has_its_own_figures(): void
    {
        $sales = app(ChannelSales::class);

        $online = $sales->summary(SalesChannel::Online, 30);
        $counter = $sales->summary(SalesChannel::Counter, 30);

        $this->assertSame(80.0, $online['revenue']);
        $this->assertSame(20.0, $online['refunded']);
        $this->assertSame(1, $online['orders']);
        $this->assertSame(10, $online['items']);

        $this->assertSame(45.0, $counter['revenue']);
        $this->assertSame(2, $counter['orders']);
        $this->assertSame(9, $counter['items']);
        $this->assertSame(22.5, $counter['average']);
    }

    public function test_daily_revenue_is_split_by_channel(): void
    {
        $today = app(ChannelSales::class)->dailyRevenue(7)[today()->toDateString()];

        $this->assertSame(['online' => 80.0, 'counter' => 45.0], $today);
    }

    public function test_best_sellers_and_payment_breakdowns_are_per_channel(): void
    {
        $sales = app(ChannelSales::class);

        $this->assertSame('Pineapple Tart', $sales->topProducts(SalesChannel::Online, 30)->sole()['product']->name);
        $this->assertSame('Egg Tart', $sales->topProducts(SalesChannel::Counter, 30)->sole()['product']->name);
        $this->assertSame(9, $sales->topProducts(SalesChannel::Counter, 30)->sole()['units']);

        $payments = $sales->counterPayments(30)->keyBy(fn (array $row) => $row['method']->value);
        $this->assertSame(1, $payments['cash']['orders']);
        $this->assertSame(15.0, $payments['card']['revenue']);
        $this->assertSame(0, $payments['e_wallet']['orders']);

        $this->assertSame(1, $sales->onlineDeliveries(30)->first()['orders']);
        $this->assertSame(DeliveryMethod::Express, $sales->onlineDeliveries(30)->first()['method']);
    }

    public function test_the_report_page_compares_both_channels(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.sales.index', ['days' => 7]))
            ->assertOk()
            ->assertSee('Online vs counter')
            ->assertSee('RM 80.00')
            ->assertSee('RM 45.00')
            ->assertSee('Online <span class="font-bold text-ink">64%</span>', false)
            ->assertSee('How counter customers paid')
            ->assertSee('View as table');
    }

    public function test_the_dashboard_links_to_the_channel_comparison(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Compare channels')
            ->assertSee(route('admin.sales.index', ['days' => 7]), false);
    }

    public function test_customers_cannot_see_sales_figures(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.sales.index'))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sale(SalesChannel $channel, float $total, array $attributes = []): Order
    {
        return Order::factory()->completed()->create([
            'channel' => $channel,
            'subtotal' => $total,
            'total' => $total,
            ...$attributes,
        ]);
    }
}
