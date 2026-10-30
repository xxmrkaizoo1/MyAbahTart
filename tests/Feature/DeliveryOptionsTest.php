<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOptionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function deliveryForm(array $overrides = []): array
    {
        return array_merge([
            'delivery_standard_label' => 'Pos Laju',
            'delivery_standard_description' => 'Arrives in 3–5 working days',
            'delivery_standard_fee' => '8',
            'delivery_standard_enabled' => '1',
            'delivery_express_label' => 'Lalamove',
            'delivery_express_description' => 'Within 2 hours in Klang Valley',
            'delivery_express_fee' => '20',
            'delivery_express_enabled' => '1',
            'delivery_pickup_label' => 'Self pickup',
            'delivery_pickup_description' => 'Collect at Taman Melawati',
            'delivery_pickup_fee' => '0',
            'delivery_pickup_enabled' => '1',
            'delivery_free_over' => '200',
            'payment_cod_enabled' => '1',
        ], $overrides);
    }

    public function test_an_admin_can_rename_reprice_and_switch_off_delivery_options(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.delivery.edit'))->assertOk()->assertSee('Standard Delivery')->assertSee('150.00');

        $this->put(route('admin.delivery.update'), $this->deliveryForm(['delivery_express_enabled' => null]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame('Pos Laju', DeliveryMethod::Standard->label());
        $this->assertSame(8.0, DeliveryMethod::Standard->feeFor(50));
        $this->assertSame(0.0, DeliveryMethod::Standard->feeFor(200));
        $this->assertSame(200.0, DeliveryMethod::freeDeliveryThreshold());
        $this->assertSame([DeliveryMethod::Standard, DeliveryMethod::Pickup], DeliveryMethod::enabledCases());

        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 30, 'stock' => 10]);
        $this->actingAs($customer)->post(route('cart.store', $product));

        $this->get(route('cart.index'))
            ->assertSee('Pos Laju')
            ->assertSee('Arrives in 3–5 working days')
            ->assertSee('RM 8.00')
            ->assertSee('Self pickup')
            ->assertDontSee('Lalamove')
            ->assertSee('Free delivery over RM 200.00');

        $this->post(route('checkout.store'), ['delivery_method' => 'express', 'phone' => '0123456789', 'address' => 'No. 1, Jalan Mawar'])
            ->assertSessionHasErrors('delivery_method');

        $this->post(route('checkout.store'), ['delivery_method' => 'standard', 'phone' => '0123456789', 'address' => 'No. 1, Jalan Mawar'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(8.00, (float) Order::sole()->delivery_fee);
    }

    public function test_at_least_one_option_must_stay_on_and_free_delivery_can_be_turned_off(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.delivery.update'), $this->deliveryForm(['delivery_standard_enabled' => null, 'delivery_express_enabled' => null, 'delivery_pickup_enabled' => null]))
            ->assertSessionHasErrors('delivery_options');

        $this->assertCount(3, DeliveryMethod::enabledCases());

        $this->put(route('admin.delivery.update'), $this->deliveryForm(['delivery_free_over' => '0']))->assertSessionHasNoErrors();

        $this->assertNull(DeliveryMethod::freeDeliveryThreshold());
        $this->assertSame(8.0, DeliveryMethod::Standard->feeFor(5000));
        $this->get(route('home'))->assertDontSee('Free delivery over');
    }

    public function test_customers_cannot_change_delivery_options(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.delivery.update'), $this->deliveryForm())
            ->assertForbidden();
    }
}
