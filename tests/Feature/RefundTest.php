<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $admin;

    private Product $product;

    private Order $order;

    private OrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->product = Product::factory()->create(['price' => 20, 'stock' => 10]);
        $this->order = Order::factory()->for($this->customer)->completed()->create(['subtotal' => 60, 'total' => 60]);
        $this->item = OrderItem::factory()->for($this->order)->forProduct($this->product, 3)->create();
    }

    public function test_a_customer_can_request_a_refund_for_items_on_a_completed_order(): void
    {
        $this->actingAs($this->customer)
            ->post(route('refunds.store', $this->order), [
                'reason' => RefundReason::Damaged->value,
                'details' => 'Box was crushed.',
                'items' => [$this->item->id => 2],
            ])
            ->assertSessionHas('status');

        $refund = Refund::sole();

        $this->assertSame(RefundStatus::Requested, $refund->status);
        $this->assertSame($this->customer->id, $refund->requested_by);
        $this->assertSame(2, $refund->items->sole()->quantity);
        $this->assertEquals(40.00, $refund->itemsValue());

        $this->get(route('orders.show', $this->order))
            ->assertOk()
            ->assertSee('Refund requested')
            ->assertSee('Awaiting review')
            ->assertDontSee('Request a refund');
    }

    public function test_the_refund_form_shows_on_eligible_orders_only(): void
    {
        $this->actingAs($this->customer)->get(route('orders.show', $this->order))->assertSee('Request a refund');

        $active = Order::factory()->for($this->customer)->status(OrderStatus::Preparing)->create();
        $this->get(route('orders.show', $active))->assertDontSee('Request a refund');

        $old = Order::factory()->for($this->customer)->completed(now()->subDays(Refund::REQUEST_WINDOW_DAYS + 1))->create();
        OrderItem::factory()->for($old)->create();
        $this->get(route('orders.show', $old))->assertDontSee('Request a refund');
    }

    public function test_refund_requests_are_refused_outside_the_window_or_for_unfinished_orders(): void
    {
        $old = Order::factory()->for($this->customer)->completed(now()->subDays(Refund::REQUEST_WINDOW_DAYS + 1))->create();
        $oldItem = OrderItem::factory()->for($old)->create();
        $active = Order::factory()->for($this->customer)->status(OrderStatus::Ready)->create();
        $activeItem = OrderItem::factory()->for($active)->create();

        $this->actingAs($this->customer);
        $this->post(route('refunds.store', $old), ['reason' => 'damaged', 'items' => [$oldItem->id => 1]])->assertSessionHas('error');
        $this->post(route('refunds.store', $active), ['reason' => 'damaged', 'items' => [$activeItem->id => 1]])->assertSessionHas('error');

        $this->assertSame(0, Refund::count());
    }

    public function test_customers_cannot_request_refunds_on_someone_elses_order(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('refunds.store', $this->order), ['reason' => 'damaged', 'items' => [$this->item->id => 1]])
            ->assertNotFound();
    }

    public function test_a_refund_request_needs_items_within_what_was_bought(): void
    {
        $this->actingAs($this->customer);

        $this->post(route('refunds.store', $this->order), ['reason' => 'damaged', 'items' => [$this->item->id => 0]])
            ->assertSessionHasErrors('items');
        $this->post(route('refunds.store', $this->order), ['reason' => 'damaged', 'items' => [$this->item->id => 4]])
            ->assertSessionHasErrors('items.'.$this->item->id);
        $this->post(route('refunds.store', $this->order), ['reason' => 'other', 'items' => [$this->item->id => 1]])
            ->assertSessionHasErrors('details');

        $this->assertSame(0, Refund::count());
    }

    public function test_only_one_request_can_be_open_at_a_time(): void
    {
        $this->actingAs($this->customer);
        $this->post(route('refunds.store', $this->order), ['reason' => 'damaged', 'items' => [$this->item->id => 1]]);
        $this->post(route('refunds.store', $this->order), ['reason' => 'damaged', 'items' => [$this->item->id => 1]])
            ->assertSessionHas('error');

        $this->assertSame(1, Refund::count());
    }

    public function test_an_admin_can_approve_a_request_and_restock_the_items(): void
    {
        $refund = $this->requestRefund(2);

        $this->actingAs($this->admin)
            ->patch(route('admin.refunds.update', $refund), [
                'decision' => 'approve',
                'amount' => '40',
                'method' => RefundMethod::BankTransfer->value,
                'restock' => '1',
                'admin_note' => 'Sorry about that!',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $refund->refresh();

        $this->assertSame(RefundStatus::Approved, $refund->status);
        $this->assertEquals(40.00, (float) $refund->amount);
        $this->assertSame(RefundMethod::BankTransfer, $refund->method);
        $this->assertTrue($refund->restocked);
        $this->assertSame($this->admin->id, $refund->processed_by);
        $this->assertSame(12, $this->product->fresh()->stock);
        $this->assertEquals(20.00, $this->order->fresh()->refundableAmount());

        $this->actingAs($this->customer)
            ->get(route('orders.show', $this->order))
            ->assertSee('Partly refunded')
            ->assertSee('Sorry about that!')
            ->assertSee('Request a refund');
    }

    public function test_approving_without_restocking_leaves_stock_alone(): void
    {
        $refund = $this->requestRefund(1);

        $this->actingAs($this->admin)->patch(route('admin.refunds.update', $refund), [
            'decision' => 'approve',
            'amount' => '20',
            'method' => RefundMethod::Cash->value,
        ]);

        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_refunds_can_never_exceed_what_the_customer_paid(): void
    {
        $refund = $this->requestRefund(3);

        $this->actingAs($this->admin)
            ->patch(route('admin.refunds.update', $refund), ['decision' => 'approve', 'amount' => '60.01', 'method' => 'cash'])
            ->assertSessionHasErrorsIn('refund', ['amount']);

        $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
    }

    public function test_declining_a_request_needs_a_note_for_the_customer(): void
    {
        $refund = $this->requestRefund(1);

        $this->actingAs($this->admin)
            ->patch(route('admin.refunds.update', $refund), ['decision' => 'reject'])
            ->assertSessionHasErrorsIn('refund', ['admin_note']);

        $this->patch(route('admin.refunds.update', $refund), ['decision' => 'reject', 'admin_note' => 'Tarts were eaten.'])
            ->assertSessionHas('status');

        $this->assertSame(RefundStatus::Rejected, $refund->fresh()->status);

        $this->actingAs($this->customer)
            ->get(route('orders.show', $this->order))
            ->assertSee('Declined')
            ->assertSee('Tarts were eaten.');
    }

    public function test_a_handled_refund_cannot_be_decided_again(): void
    {
        $refund = Refund::factory()->for($this->order)->approved(20)->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.refunds.update', $refund), ['decision' => 'reject', 'admin_note' => 'Oops'])
            ->assertSessionHas('error');

        $this->assertSame(RefundStatus::Approved, $refund->fresh()->status);
    }

    public function test_an_admin_can_issue_a_refund_without_a_request(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.refunds.store', $this->order), [
                'reason' => RefundReason::MissingItem->value,
                'items' => [$this->item->id => 1],
                'amount' => '20',
                'method' => RefundMethod::EWallet->value,
            ])
            ->assertSessionHasNoErrors();

        $refund = Refund::sole();

        $this->assertSame(RefundStatus::Approved, $refund->status);
        $this->assertSame(1, $refund->items->sole()->quantity);
        $this->assertEquals(40.00, $this->order->fresh()->refundableAmount());

        $this->get(route('admin.orders.show', $this->order))
            ->assertOk()
            ->assertSee('Refund history')
            ->assertSee('RM 20.00');
    }

    public function test_staff_cannot_refund_an_order_that_is_not_completed(): void
    {
        $active = Order::factory()->status(OrderStatus::Preparing)->create(['total' => 30]);

        $this->actingAs($this->admin)
            ->post(route('admin.refunds.store', $active), ['reason' => 'other', 'amount' => '10', 'method' => 'cash'])
            ->assertSessionHas('error');

        $this->assertSame(0, Refund::count());
    }

    public function test_the_refunds_page_lists_and_filters_refunds(): void
    {
        $pending = $this->requestRefund(1);
        $otherOrder = Order::factory()->completed()->create(['total' => 50]);
        Refund::factory()->for($otherOrder)->approved(15)->create(['processed_at' => now()]);

        $this->actingAs($this->admin);

        $this->get(route('admin.refunds.index'))
            ->assertOk()
            ->assertSee($this->order->code)
            ->assertSee($otherOrder->code)
            ->assertSee('RM 15.00');

        $this->get(route('admin.refunds.index', ['status' => 'requested']))
            ->assertOk()
            ->assertSee($pending->order->code)
            ->assertDontSee($otherOrder->code);

        $this->get(route('admin.orders.show', $this->order))
            ->assertOk()
            ->assertSee('Refund requested')
            ->assertSee('Approve');
    }

    public function test_customers_cannot_open_the_refunds_page(): void
    {
        $this->actingAs($this->customer)->get(route('admin.refunds.index'))->assertForbidden();
    }

    public function test_dashboard_revenue_excludes_refunded_money(): void
    {
        Refund::factory()->for($this->order)->approved(25)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('RM 35.00');
    }

    private function requestRefund(int $quantity): Refund
    {
        $refund = Refund::factory()->for($this->order)->create(['requested_by' => $this->customer->id]);
        $refund->items()->create(['order_item_id' => $this->item->id, 'quantity' => $quantity]);

        return $refund;
    }
}
