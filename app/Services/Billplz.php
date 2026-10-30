<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Online payments (FPX online banking, cards and e-wallets) through the Billplz API (v3).
 */
class Billplz
{
    /**
     * Unpaid orders are cancelled and their stock released after this many minutes.
     */
    public const PAYMENT_WINDOW_MINUTES = 30;

    /**
     * Whether the API key, collection and X Signature key are set in the .env file.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.billplz.key'))
            && filled(config('services.billplz.collection_id'))
            && filled(config('services.billplz.x_signature'));
    }

    public function isSandbox(): bool
    {
        return (bool) config('services.billplz.sandbox');
    }

    /**
     * Create a bill for an order and return its id and the page where the customer pays.
     *
     * @return array{id: string, url: string}
     */
    public function createBill(string $orderCode, float $total, User $customer, string $phone): array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->asForm()->post('/v3/bills', [
            'collection_id' => config('services.billplz.collection_id'),
            'email' => $customer->email,
            'mobile' => preg_replace('/\D/', '', $phone),
            'name' => Str::limit($customer->name, 250, ''),
            'amount' => (int) round($total * 100),
            'callback_url' => route('payments.billplz.callback'),
            'redirect_url' => route('payments.billplz.return'),
            'description' => Str::limit("Order {$orderCode} from ".config('app.name'), 200, ''),
            'reference_1_label' => 'Order',
            'reference_1' => $orderCode,
        ]));

        return ['id' => (string) $response->json('id'), 'url' => (string) $response->json('url')];
    }

    /**
     * Ask Billplz whether the order's bill is paid, and record the payment if it is.
     */
    public function confirm(Order $order): bool
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return true;
        }

        $bill = $this->send(fn (PendingRequest $request) => $request->get("/v3/bills/{$order->billplz_bill_id}"));

        if ($bill->json('paid') !== true || (int) $bill->json('amount') !== (int) round((float) $order->total * 100)) {
            return false;
        }

        $order->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Online,
            'paid_at' => now(),
        ]);

        if ($order->status === OrderStatus::Cancelled) {
            Log::warning("Order {$order->code} was paid after it was cancelled. Refund the customer from the Billplz dashboard.");
        }

        return true;
    }

    /**
     * Delete an unpaid bill so the customer can no longer pay for a cancelled order.
     */
    public function deleteBill(string $billId): void
    {
        try {
            $this->send(fn (PendingRequest $request) => $request->delete("/v3/bills/{$billId}"));
        } catch (PaymentGatewayException) {
            // The bill may already be paid or gone; the cancellation still stands.
        }
    }

    /**
     * Check the X Signature Billplz adds to its callback and redirect, proving the data came from Billplz.
     *
     * @param  array<string, mixed>  $fields
     */
    public function hasValidSignature(array $fields, string $prefix = ''): bool
    {
        $signature = (string) ($fields['x_signature'] ?? '');
        unset($fields['x_signature']);

        $source = collect($fields)
            ->map(fn ($value, string $key) => $prefix.$key.(is_scalar($value) ? (string) $value : ''))
            ->values()
            ->sort(fn (string $first, string $second) => strcasecmp($first, $second))
            ->implode('|');

        return $signature !== '' && hash_equals(hash_hmac('sha256', $source, (string) config('services.billplz.x_signature')), $signature);
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        $request = Http::baseUrl($this->isSandbox() ? 'https://www.billplz-sandbox.com/api' : 'https://www.billplz.com/api')
            ->withBasicAuth((string) config('services.billplz.key'), '')
            ->acceptJson()
            ->timeout(15);

        try {
            $response = $call($request);
        } catch (ConnectionException) {
            throw new PaymentGatewayException('We couldn’t reach the payment page. Please try again in a moment.');
        }

        if ($response->failed()) {
            Log::warning('Billplz request failed', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

            throw new PaymentGatewayException('We couldn’t start the payment. Please try again in a moment.');
        }

        return $response;
    }
}
