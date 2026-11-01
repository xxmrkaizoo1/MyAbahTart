<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProductSize;
use App\Enums\SalesChannel;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'abah@example.com', 'password' => 'secret-password']);
    }

    /**
     * @return array<string, string>
     */
    private function asAdmin(): array
    {
        return ['Authorization' => 'Bearer '.ApiToken::issue($this->admin, 'Test phone')];
    }

    public function test_an_admin_can_sign_in_and_out_of_the_app(): void
    {
        $token = $this->postJson('/api/admin/login', ['email' => 'abah@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'abah@example.com')
            ->json('token');

        $this->getJson('/api/admin/me', ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonPath('user.id', $this->admin->id);

        $this->postJson('/api/admin/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();

        $this->assertSame(0, ApiToken::query()->count());
    }

    public function test_wrong_passwords_and_customers_cannot_sign_in(): void
    {
        $this->postJson('/api/admin/login', ['email' => 'abah@example.com', 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('email');

        User::factory()->create(['email' => 'customer@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/admin/login', ['email' => 'customer@example.com', 'password' => 'secret-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Only shop administrators can use this app.']);
    }

    public function test_the_api_needs_an_admin_token(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/admin/dashboard', ['Authorization' => 'Bearer not-a-token'])->assertUnauthorized();

        $customerToken = ApiToken::issue(User::factory()->create(), 'Phone');

        $this->getJson('/api/admin/dashboard', ['Authorization' => "Bearer {$customerToken}"])->assertForbidden();
    }

    public function test_every_admin_page_is_available_as_json(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->forProduct($product, 2)->create();
        Voucher::factory()->create();
        $headers = $this->asAdmin();

        $this->getJson('/api/admin/dashboard', $headers)->assertOk()->assertJsonStructure(['revenue', 'active_orders', 'channel_split', 'forecast', 'kitchen_queue', 'recent_orders']);
        $this->getJson('/api/admin/orders', $headers)->assertOk()->assertJsonPath('orders.0.code', $order->code)->assertJsonPath('orders.0.next_status.value', 'preparing');
        $this->getJson("/api/admin/orders/{$order->code}", $headers)->assertOk()->assertJsonPath('order.items.0.quantity', 2);
        $this->getJson('/api/admin/products', $headers)->assertOk()->assertJsonPath('products.0.slug', $product->slug);
        $this->getJson("/api/admin/products/{$product->slug}", $headers)->assertOk()->assertJsonPath('product.stock', 3);
        $this->getJson('/api/admin/collections', $headers)->assertOk()->assertJsonCount(1, 'categories');
        $this->getJson('/api/admin/sales?days=7', $headers)->assertOk()->assertJsonPath('days', 7)->assertJsonCount(7, 'daily');
        $this->getJson('/api/admin/storefront', $headers)->assertOk()->assertJsonStructure(['settings' => ['hero_title_highlight']]);
        $this->getJson('/api/admin/delivery', $headers)->assertOk()->assertJsonStructure(['settings', 'billplz_configured']);
        $this->getJson('/api/admin/pos', $headers)->assertOk()->assertJsonPath('products.0.sellable', 3);
        $this->getJson('/api/admin/pos/menu?day=tomorrow', $headers)->assertOk()->assertJsonPath('day', 'tomorrow');
        $this->getJson('/api/admin/refunds', $headers)->assertOk()->assertJsonStructure(['refunds', 'pending_count']);
        $this->getJson('/api/admin/vouchers', $headers)->assertOk()->assertJsonCount(1, 'vouchers');
        $this->getJson('/api/admin/options', $headers)->assertOk()->assertJsonStructure(['cancellation_reasons', 'refund_methods', 'arts']);
    }

    public function test_order_changes_follow_the_admin_panel_rules(): void
    {
        $order = Order::factory()->create();
        $headers = $this->asAdmin();

        $this->patchJson("/api/admin/orders/{$order->code}/status", ['status' => 'completed'], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Orders must move through each status in turn.');

        $this->patchJson("/api/admin/orders/{$order->code}/status", ['status' => 'preparing'], $headers)
            ->assertOk()
            ->assertJsonPath('message', "Order {$order->code} updated: Being Prepared.");

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);

        $this->patchJson("/api/admin/orders/{$order->code}/status", ['status' => 'cancelled'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cancellation_reason');
    }

    public function test_a_counter_sale_can_be_rung_up_from_the_app(): void
    {
        $product = Product::factory()->create(['price' => 12, 'stock' => 10]);
        $headers = $this->asAdmin();

        $response = $this->postJson('/api/admin/pos/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'amount_tendered' => 30,
        ], $headers)->assertOk();

        $order = Order::query()->sole();

        $this->assertSame(SalesChannel::Counter, $order->channel);
        $this->assertSame($this->admin->id, $order->served_by);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertStringEndsWith("/admin/pos/sales/{$order->code}", $response->json('redirect'));

        $this->getJson("/api/admin/pos/sales/{$order->code}", $headers)
            ->assertOk()
            ->assertJsonPath('order.change_given', 6)
            ->assertJsonPath('order.cashier', $this->admin->name);
    }

    public function test_products_can_be_managed_from_the_app(): void
    {
        $category = Category::factory()->create();
        $headers = $this->asAdmin();

        $this->postJson('/api/admin/products', ['name' => 'Kuih Bangkit'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'description', 'price']);

        $this->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'name' => 'Kuih Bangkit',
            'description' => 'Melt-in-the-mouth coconut cookies.',
            'size_prices' => ['S' => '10', 'L' => '25'],
            'size_stock' => ['S' => '4', 'L' => '6'],
            'unit_label' => 'Jar',
            'low_stock_threshold' => 2,
            'is_active' => true,
        ], $headers)->assertOk()->assertJsonPath('message', 'Kuih Bangkit added to the menu.');

        $product = Product::query()->where('name', 'Kuih Bangkit')->sole();
        $this->assertSame(10, $product->stock);

        $this->postJson("/api/admin/products/{$product->slug}/restock", ['quantity' => 5, 'size' => 'S'], $headers)->assertOk();
        $this->assertSame(9, $product->fresh()->stockFor(ProductSize::Small));

        $review = Review::factory()->for($product)->create();
        $this->getJson("/api/admin/products/{$product->slug}", $headers)->assertJsonPath('product.reviews.0.id', $review->id);
        $this->deleteJson("/api/admin/reviews/{$review->id}", [], $headers)->assertOk();

        $this->deleteJson("/api/admin/products/{$product->slug}", [], $headers)->assertOk();
        $this->assertModelMissing($product);
    }

    public function test_collections_with_products_cannot_be_deleted_from_the_app(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/admin/collections/{$product->category->slug}", [], $this->asAdmin())
            ->assertUnprocessable()
            ->assertJsonPath('message', "Move or delete the 1 product in {$product->category->name} first.");
    }

    public function test_vouchers_can_be_managed_from_the_app(): void
    {
        $headers = $this->asAdmin();

        $this->postJson('/api/admin/vouchers', ['code' => 'raya10', 'description' => 'Raya promo', 'type' => 'percent', 'value' => 10], $headers)
            ->assertOk()
            ->assertJsonPath('message', 'Voucher RAYA10 created.');

        $voucher = Voucher::query()->sole();

        $this->patchJson("/api/admin/vouchers/{$voucher->id}", [], $headers)->assertOk();
        $this->assertFalse($voucher->fresh()->is_active);

        $this->deleteJson("/api/admin/vouchers/{$voucher->id}", [], $headers)->assertOk();
        $this->assertModelMissing($voucher);
    }
}
