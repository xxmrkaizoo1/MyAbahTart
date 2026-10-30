<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_pages_render(): void
    {
        $product = Product::firstWhere('slug', 'golden-pineapple-tart');

        $this->get(route('home'))->assertOk()->assertSee('Pistachio Rose Tart')->assertSee('Trending now');
        $this->get(route('products.index'))->assertOk()->assertSee('Golden Pineapple Tart');
        $this->get(route('products.index', ['search' => 'lemon', 'sort' => 'price-low']))->assertOk()->assertSee('Lemon Meringue Tart');
        $this->get(route('products.index', ['category' => 'savoury']))->assertOk()->assertSee('Mushroom &amp; Thyme Quiche', false);
        $this->get(route('products.show', $product))->assertOk()->assertSee('What people say');
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
        $this->get(route('register'))->assertOk()->assertSee('Create an account');

        $this->post(route('cart.store', $product));
        $this->get(route('cart.index'))->assertOk()->assertSee('Sign in to check out');
    }

    public function test_the_main_navigation_highlights_the_current_page(): void
    {
        $this->get(route('products.index'))
            ->assertOk()
            ->assertSeeInOrder(['data-nav-track', 'aria-current="page" data-lit', 'data-nav-pill', 'Shop all'], false);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertDontSee('data-nav-pill', false);
    }

    public function test_customer_pages_render(): void
    {
        $customer = User::firstWhere('email', 'customer@myabahtart.test');
        $order = $customer->orders()->latest()->first();

        $this->actingAs($customer);

        $this->get(route('home'))->assertOk()->assertSee('Picked for you');
        $this->get(route('orders.index'))->assertOk()->assertSee($order->code);
        $this->get(route('orders.show', $order))->assertOk();
        $this->get(route('profile.edit'))->assertOk()->assertSee('Personal details');
        $this->get(route('cart.index'))->assertOk();
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs(User::firstWhere('email', 'firasumairisgood@gmail.com'));

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Sales forecast')->assertSee('Kitchen queue');
        $this->get(route('admin.dashboard', ['days' => 30]))->assertOk();
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('Being prepared');
        $this->get(route('admin.orders.index', ['status' => 'preparing', 'search' => 'Sophia']))->assertOk();
        $this->get(route('admin.orders.show', Order::first()))->assertOk();
        $this->get(route('admin.products.index'))->assertOk();
        $this->get(route('admin.products.index', ['stock' => 'low']))->assertOk()->assertSee('Hazelnut Lava Box');
        $this->get(route('admin.products.create'))->assertOk();
        $this->get(route('admin.products.edit', Product::first()))->assertOk();
        $this->get(route('admin.vouchers.index'))->assertOk()->assertSee('WELCOME10');
    }

    public function test_no_malay_copy_remains_in_rendered_pages(): void
    {
        $this->actingAs(User::firstWhere('email', 'customer@myabahtart.test'));

        foreach ([route('home'), route('products.index'), route('orders.index'), route('cart.index')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            foreach (['Pesanan', 'Troli', 'Selamat', 'anda', 'Tambah', 'Bayar'] as $malayWord) {
                $this->assertDoesNotMatchRegularExpression("/\b{$malayWord}\b/i", strip_tags($html), "Found “{$malayWord}” on {$url}");
            }
        }
    }
}
