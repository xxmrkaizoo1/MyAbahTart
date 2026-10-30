<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillplzPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE_KEY = 'S-test-signature-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.billplz.key' => 'billplz-api-key',
            'services.billplz.collection_id' => 'col_123',
            'services.billplz.x_signature' => self::SIGNATURE_KEY,
            'services.billplz.sandbox' => true,
        ]);

        app(Storefront::class)->update(['payment_online_enabled' => '1']);
    }

    public function test_checkout_sends_the_customer_to_the_billplz_payment_page(): void
    {
        Http::fake(['www.billplz-sandbox.com/api/v3/bills' => Http::response(['id' => 'bill_abc', 'url' => 'https://www.billplz-sandbox.com/bills/bill_abc'])]);
        $product = Product::factory()->create(['price' => 40, 'stock' => 10]);
        $customer = User::factory()->create(['email' => 'aisyah@example.com']);

        $this->actingAs($customer)->post(route('cart.store', $product));
        $this->get(route('cart.index'))->assertSee('Continue to payment')->assertSee('Secure payment by Billplz');

        $this->post(route('checkout.store'), $this->checkoutForm())
            ->assertRedirect('https://www.billplz-sandbox.com/bills/bill_abc');

        $order = Order::sole();
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame('bill_abc', $order->billplz_bill_id);
        $this->assertSame(9, $product->fresh()->stock);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('billplz-api-key:'))
            && $request['collection_id'] === 'col_123'
            && $request['amount'] === 4500
            && $request['email'] === 'aisyah@example.com'
            && $request['reference_1'] === $order->code
            && $request['callback_url'] === route('payments.billplz.callback'));
    }

    public function test_the_order_is_paid_when_the_customer_returns_from_billplz(): void
    {
        $order = $this->unpaidOrder();
        Http::fake(["www.billplz-sandbox.com/api/v3/bills/{$order->billplz_bill_id}" => Http::response(['id' => $order->billplz_bill_id, 'paid' => true, 'amount' => 4500])]);

        $this->actingAs($order->user)
            ->get(route('payments.billplz.return', ['billplz' => $this->signed(['id' => $order->billplz_bill_id, 'paid' => 'true', 'paid_at' => '2026-10-30 16:00:00 +0800'], 'billplz')]))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(PaymentMethod::Online, $order->payment_method);
        $this->assertNotNull($order->paid_at);
    }

    public function test_a_tampered_return_link_is_rejected(): void
    {
        $order = $this->unpaidOrder();
        Http::fake();

        $fields = $this->signed(['id' => $order->billplz_bill_id, 'paid' => 'false', 'paid_at' => ''], 'billplz');
        $fields['paid'] = 'true';

        $this->get(route('payments.billplz.return', ['billplz' => $fields]))->assertForbidden();

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_a_bill_that_is_not_paid_in_full_does_not_count(): void
    {
        $order = $this->unpaidOrder();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response(['id' => $order->billplz_bill_id, 'paid' => true, 'amount' => 100])]);

        $this->actingAs($order->user)
            ->get(route('payments.billplz.return', ['billplz' => $this->signed(['id' => $order->billplz_bill_id, 'paid' => 'true', 'paid_at' => ''], 'billplz')]))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->actingAs($order->user)->get(route('orders.show', $order))->assertSee('Complete your payment')->assertSee($order->payment_url);
    }

    public function test_billplz_callback_marks_the_order_paid(): void
    {
        $order = $this->unpaidOrder();
        Http::fake(['www.billplz-sandbox.com/*' => Http::response(['id' => $order->billplz_bill_id, 'paid' => true, 'amount' => 4500])]);

        $this->post(route('payments.billplz.callback'), $this->signed([
            'id' => $order->billplz_bill_id,
            'collection_id' => 'col_123',
            'paid' => 'true',
            'state' => 'paid',
            'amount' => '4500',
            'paid_amount' => '4500',
            'email' => 'aisyah@example.com',
            'mobile' => '',
            'name' => 'Aisyah',
        ]))->assertOk();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        $this->post(route('payments.billplz.callback'), ['id' => $order->billplz_bill_id, 'paid' => 'true', 'x_signature' => 'forged'])->assertForbidden();
    }

    public function test_the_kitchen_cannot_start_an_unpaid_order(): void
    {
        $order = $this->unpaidOrder();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.update', $order), ['status' => OrderStatus::Preparing->value])
            ->assertSessionHas('error', 'Wait for the customer to pay before starting this order.');

        $this->assertSame(OrderStatus::Received, $order->fresh()->status);
        $this->get(route('admin.orders.show', $order))->assertSee('Awaiting payment');
    }

    public function test_unpaid_orders_are_cancelled_after_the_payment_window_and_stock_is_returned(): void
    {
        $product = Product::factory()->create(['price' => 40, 'stock' => 5]);
        $expired = $this->unpaidOrder($product, 'bill_old');
        $expired->forceFill(['created_at' => now()->subMinutes(45)])->save();
        $recent = $this->unpaidOrder($product, 'bill_new');

        Http::fake([
            'www.billplz-sandbox.com/api/v3/bills/bill_old' => Http::sequence()
                ->push(['id' => 'bill_old', 'paid' => false, 'amount' => 4500])
                ->push([], 200),
        ]);

        $this->artisan('orders:cancel-unpaid')->expectsOutput('Cancelled 1 unpaid order.')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $expired->fresh()->status);
        $this->assertSame(OrderStatus::Received, $recent->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock);
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/v3/bills/bill_old'));
    }

    public function test_customers_can_choose_cash_on_delivery_instead(): void
    {
        Http::fake();
        $product = Product::factory()->create(['price' => 40, 'stock' => 10]);

        $this->actingAs(User::factory()->create())->post(route('cart.store', $product));
        $this->get(route('cart.index'))->assertSee('How would you like to pay?')->assertSee('Cash on delivery')->assertSee('Online payment');

        $this->post(route('checkout.store'), [...$this->checkoutForm(), 'payment_method' => PaymentMethod::Cash->value])
            ->assertRedirect(route('orders.show', Order::sole()));

        $order = Order::sole();
        $this->assertSame(PaymentMethod::Cash, $order->payment_method);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertFalse($order->isAwaitingPayment());
        Http::assertNothingSent();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertSee('Cash on delivery');

        foreach ([OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $status) {
            $this->patch(route('admin.orders.update', $order), ['status' => $status->value])->assertSessionHasNoErrors();
        }

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->get(route('admin.orders.show', $order))->assertSee('Paid in cash');
    }

    public function test_switched_off_payment_options_cannot_be_used(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.delivery.update'), [...$this->deliverySettings(), 'payment_cod_enabled' => '0', 'payment_online_enabled' => '1'])
            ->assertSessionHasNoErrors();

        $product = Product::factory()->create(['price' => 40, 'stock' => 10]);
        $this->actingAs(User::factory()->create())->post(route('cart.store', $product));
        $this->get(route('cart.index'))->assertDontSee('Cash on delivery');

        $this->post(route('checkout.store'), [...$this->checkoutForm(), 'payment_method' => PaymentMethod::Cash->value])
            ->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());
    }

    public function test_one_working_payment_option_must_stay_on(): void
    {
        config(['services.billplz.key' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.delivery.update'), [...$this->deliverySettings(), 'payment_cod_enabled' => '0', 'payment_online_enabled' => '1'])
            ->assertSessionHasErrors('payment_options');

        $this->get(route('admin.delivery.edit'))->assertSee('Payment at checkout')->assertSee('Not connected yet');
    }

    public function test_without_billplz_keys_only_cash_on_delivery_is_offered(): void
    {
        config(['services.billplz.key' => null]);
        Http::fake();
        $product = Product::factory()->create(['price' => 40, 'stock' => 10]);

        $this->actingAs(User::factory()->create())->post(route('cart.store', $product));
        $this->get(route('cart.index'))->assertSee('Place order')->assertSee('Cash on delivery')->assertSee('Coming soon')->assertDontSee('value="online"', false);

        $this->post(route('checkout.store'), $this->checkoutForm())->assertRedirect(route('orders.show', Order::sole()));

        $this->assertSame(PaymentMethod::Cash, Order::sole()->payment_method);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, string>
     */
    private function deliverySettings(): array
    {
        $settings = collect(Storefront::DEFAULTS)->only(collect(DeliveryMethod::cases())->flatMap(fn (DeliveryMethod $method) => [
            "delivery_{$method->value}_label", "delivery_{$method->value}_description", "delivery_{$method->value}_fee", "delivery_{$method->value}_enabled",
        ]))->all();

        return $settings;
    }

    /**
     * @return array<string, string>
     */
    private function checkoutForm(): array
    {
        return [
            'delivery_method' => DeliveryMethod::Standard->value,
            'phone' => '012-345 6789',
            'address' => '27, Jalan SS 2/24, 47300 Petaling Jaya',
        ];
    }

    private function unpaidOrder(?Product $product = null, string $billId = 'bill_abc'): Order
    {
        $order = Order::factory()->status(OrderStatus::Received)->create([
            'total' => 45,
            'payment_method' => PaymentMethod::Online,
            'payment_status' => PaymentStatus::Unpaid,
            'billplz_bill_id' => $billId,
            'payment_url' => "https://www.billplz-sandbox.com/bills/{$billId}",
        ]);

        if ($product) {
            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => 40,
                'quantity' => 2,
                'line_total' => 80,
            ]);
        }

        return $order;
    }

    /**
     * Sign fields the way Billplz does, so the shop can check they are genuine.
     *
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function signed(array $fields, string $prefix = ''): array
    {
        $source = collect($fields)->map(fn (string $value, string $key) => $prefix.$key.$value)->values()->all();
        sort($source, SORT_STRING | SORT_FLAG_CASE);

        return [...$fields, 'x_signature' => hash_hmac('sha256', implode('|', $source), self::SIGNATURE_KEY)];
    }
}
