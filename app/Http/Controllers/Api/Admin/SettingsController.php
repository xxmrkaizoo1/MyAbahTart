<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CancellationReason;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductSize;
use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Billplz;
use App\Services\Lalamove;
use App\Services\Storefront;
use Illuminate\Http\JsonResponse;

/**
 * Shop settings and the choices the app offers in its forms.
 */
class SettingsController extends Controller
{
    /**
     * Labels for every choice the admin panel's forms offer.
     */
    public function options(): JsonResponse
    {
        $choices = fn (array $cases) => array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);

        return response()->json([
            'order_statuses' => array_map(fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->shortLabel()], OrderStatus::cases()),
            'cancellation_reasons' => $choices(CancellationReason::forShop()),
            'refund_reasons' => $choices(RefundReason::cases()),
            'refund_methods' => $choices(RefundMethod::cases()),
            'refund_statuses' => $choices(RefundStatus::cases()),
            'counter_payment_methods' => $choices(PaymentMethod::forCounter()),
            'delivery_methods' => $choices(DeliveryMethod::cases()),
            'sizes' => $choices(ProductSize::cases()),
            'arts' => collect(Product::ARTS)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'lalamove_service_types' => collect(Lalamove::SERVICE_TYPES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function storefront(Storefront $storefront): JsonResponse
    {
        return response()->json([
            'settings' => $storefront->all(),
            'hero_image_url' => $storefront->heroImageUrl(),
        ]);
    }

    public function delivery(Storefront $storefront, Lalamove $lalamove, Billplz $billplz): JsonResponse
    {
        return response()->json([
            'settings' => $storefront->all(),
            'lalamove_configured' => $lalamove->isConfigured(),
            'lalamove_sandbox' => $lalamove->isSandbox(),
            'billplz_configured' => $billplz->isConfigured(),
            'billplz_sandbox' => $billplz->isSandbox(),
        ]);
    }
}
