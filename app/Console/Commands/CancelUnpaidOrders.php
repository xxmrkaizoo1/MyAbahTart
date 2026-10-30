<?php

namespace App\Console\Commands;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use App\Services\Billplz;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('orders:cancel-unpaid')]
#[Description('Cancel online orders that were not paid in time and put their stock back')]
class CancelUnpaidOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Billplz $billplz): int
    {
        $orders = Order::query()
            ->where('payment_method', PaymentMethod::Online)
            ->where('payment_status', PaymentStatus::Unpaid)
            ->where('status', OrderStatus::Received)
            ->where('created_at', '<=', now()->subMinutes(Billplz::PAYMENT_WINDOW_MINUTES))
            ->with('items.product')
            ->get();

        $cancelled = 0;

        foreach ($orders as $order) {
            try {
                if ($billplz->confirm($order)) {
                    continue;
                }
            } catch (PaymentGatewayException) {
                continue;
            }

            $billplz->deleteBill($order->billplz_bill_id);

            DB::transaction(function () use ($order) {
                foreach ($order->items as $item) {
                    $item->product?->adjustStock($item->size, $item->quantity);
                }

                $order->fill([
                    'cancellation_reason' => CancellationReason::Payment,
                    'cancellation_note' => 'Payment wasn’t completed within '.Billplz::PAYMENT_WINDOW_MINUTES.' minutes.',
                ]);
                $order->transitionTo(OrderStatus::Cancelled);
            });

            $cancelled++;
        }

        $this->info("Cancelled {$cancelled} unpaid ".str('order')->plural($cancelled).'.');

        return self::SUCCESS;
    }
}
