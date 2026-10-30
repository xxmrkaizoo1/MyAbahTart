<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryMethod;
use App\Http\Controllers\Controller;
use App\Services\Lalamove;
use App\Services\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function edit(Storefront $storefront, Lalamove $lalamove): View
    {
        return view('admin.delivery.edit', ['storefront' => $storefront, 'lalamove' => $lalamove]);
    }

    public function update(Request $request, Storefront $storefront, Lalamove $lalamove): RedirectResponse
    {
        $rules = [
            'delivery_free_over' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'lalamove_service_type' => ['nullable', Rule::in(array_keys(Lalamove::SERVICE_TYPES))],
            'lalamove_pickup_address' => ['nullable', 'string', 'max:300'],
        ];

        foreach (DeliveryMethod::cases() as $method) {
            $rules["delivery_{$method->value}_label"] = ['required', 'string', 'max:40'];
            $rules["delivery_{$method->value}_description"] = ['nullable', 'string', 'max:120'];
            $rules["delivery_{$method->value}_fee"] = ['required', 'numeric', 'min:0', 'max:1000'];
        }

        $validated = $request->validate($rules, [
            'delivery_*_label.required' => 'Give every option a name.',
            'delivery_*_fee.required' => 'Enter a fee, or 0 for free.',
        ]);

        foreach (DeliveryMethod::cases() as $method) {
            $validated["delivery_{$method->value}_enabled"] = $request->boolean("delivery_{$method->value}_enabled") ? '1' : '0';
            $validated["delivery_{$method->value}_fee"] = number_format((float) $validated["delivery_{$method->value}_fee"], 2, '.', '');
        }

        if (! in_array('1', array_map(fn (DeliveryMethod $method) => $validated["delivery_{$method->value}_enabled"], DeliveryMethod::cases()), true)) {
            throw ValidationException::withMessages(['delivery_options' => 'Keep at least one option switched on so customers can check out.']);
        }

        $validated['delivery_free_over'] = number_format((float) ($validated['delivery_free_over'] ?? 0), 2, '.', '');
        $validated['delivery_express_live'] = $lalamove->isConfigured() && $request->boolean('delivery_express_live') ? '1' : '0';
        $validated['lalamove_service_type'] ??= 'MOTORCYCLE';
        $validated['lalamove_pickup_address'] = trim((string) ($validated['lalamove_pickup_address'] ?? ''));

        $storefront->update($validated);

        return back()->with('status', 'Delivery options saved. Customers see them at checkout straight away.');
    }
}
