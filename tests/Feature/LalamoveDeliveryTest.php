<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LalamoveDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.lalamove.key' => 'pk_test_123',
            'services.lalamove.secret' => 'sk_test_456',
            'services.lalamove.sandbox' => true,
        ]);

        app(Storefront::class)->update(['delivery_express_live' => '1', 'delivery_express_label' => 'Lalamove send']);
    }

    private function fakeLalamove(string $price = '23.40', int $status = 200): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => fn (Request $request) => Http::response(
                str_contains($request['q'], 'Nowhere') || str_contains($request['q'], 'Atlantis') ? [] : [['lat' => '3.1300', 'lon' => '101.6700']],
            ),
            'rest.sandbox.lalamove.com/*' => $status === 200
                ? Http::response(['data' => [
                    'quotationId' => '1514140994227007571',
                    'priceBreakdown' => ['total' => $price, 'currency' => 'MYR'],
                    'distance' => ['value' => '12450', 'unit' => 'm'],
                    'expiresAt' => now()->addMinutes(5)->toIso8601String(),
                ]])
                : Http::response(['errors' => [['id' => 'ERR_OUT_OF_SERVICE_AREA', 'message' => 'Out of area']]], $status),
        ]);
    }

    public function test_checkout_shows_a_live_price_for_the_customers_address(): void
    {
        $this->fakeLalamove();

        $this->postJson(route('delivery.quote'), ['address' => '27, Jalan SS 2/24, 47300 Petaling Jaya'])
            ->assertOk()
            ->assertJson(['price' => 23.4, 'distance_km' => 12.5]);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'lalamove.com/v3/quotations')) {
                return false;
            }

            [$scheme, $credentials] = explode(' ', $request->header('Authorization')[0]);
            [$key, $time, $signature] = explode(':', $credentials);
            $expected = hash_hmac('sha256', "{$time}\r\nPOST\r\n/v3/quotations\r\n\r\n{$request->body()}", 'sk_test_456');

            return $scheme === 'hmac' && $key === 'pk_test_123' && hash_equals($expected, $signature)
                && $request->header('Market')[0] === 'MY'
                && $request['data']['serviceType'] === 'MOTORCYCLE'
                && count($request['data']['stops']) === 2
                && $request['data']['stops'][1]['address'] === '27, Jalan SS 2/24, 47300 Petaling Jaya';
        });
    }

    public function test_the_order_is_charged_the_lalamove_price(): void
    {
        $this->fakeLalamove('31.90');
        $product = Product::factory()->create(['price' => 40, 'stock' => 10]);

        $this->actingAs(User::factory()->create())->post(route('cart.store', $product));

        $this->get(route('cart.index'))->assertSee('Lalamove send')->assertSee('By distance')->assertSee(route('delivery.quote'), false);

        $this->post(route('checkout.store'), [
            'delivery_method' => DeliveryMethod::Express->value,
            'phone' => '0123456789',
            'address' => '27, Jalan SS 2/24, 47300 Petaling Jaya',
        ])->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertEquals(31.90, (float) $order->delivery_fee);
        $this->assertEquals(71.90, (float) $order->total);
    }

    public function test_addresses_that_are_not_on_the_map_get_a_helpful_message(): void
    {
        $this->fakeLalamove();

        $this->postJson(route('delivery.quote'), ['address' => 'Nowhere Lane, Atlantis'])
            ->assertUnprocessable()
            ->assertJson(['message' => 'We couldn’t find that address on the map. Please add the postcode and city.']);
    }

    public function test_addresses_lalamove_cannot_price_block_express_checkout(): void
    {
        $product = Product::factory()->create(['price' => 40, 'stock' => 10]);
        $this->actingAs(User::factory()->create())->post(route('cart.store', $product));

        $this->fakeLalamove(status: 422);
        $this->post(route('checkout.store'), [
            'delivery_method' => DeliveryMethod::Express->value,
            'phone' => '0123456789',
            'address' => 'Jalan Pantai, 12345 Far Away',
        ])->assertSessionHasErrors(['address' => 'Lalamove doesn’t deliver to this address yet. Please choose another option.']);

        $this->assertSame(0, Order::count());
    }

    public function test_without_api_keys_express_uses_the_flat_fee(): void
    {
        config(['services.lalamove.key' => null]);
        Http::fake();
        $product = Product::factory()->create(['price' => 40, 'stock' => 10]);

        $this->actingAs(User::factory()->create())->post(route('cart.store', $product));
        $this->get(route('cart.index'))->assertDontSee('By distance');

        $this->post(route('checkout.store'), [
            'delivery_method' => DeliveryMethod::Express->value,
            'phone' => '0123456789',
            'address' => '27, Jalan SS 2/24, 47300 Petaling Jaya',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(15.00, (float) Order::sole()->delivery_fee);
        Http::assertNothingSent();
    }

    public function test_the_admin_page_shows_the_lalamove_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.delivery.edit'))
            ->assertOk()
            ->assertSee('Live Lalamove price')
            ->assertSee('Connected to Lalamove (sandbox: test prices)');
    }
}
