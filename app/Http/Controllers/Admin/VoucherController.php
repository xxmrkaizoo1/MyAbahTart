<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function index(): View
    {
        return view('admin.vouchers.index', [
            'vouchers' => Voucher::query()->latest()->get(),
            'usage' => Order::query()
                ->notCancelled()
                ->whereNotNull('voucher_code')
                ->groupBy('voucher_code')
                ->selectRaw('voucher_code, COUNT(*) as uses, SUM(discount) as discount_total')
                ->get()
                ->keyBy('voucher_code'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'code' => ['required', 'alpha_num', 'max:30', 'unique:vouchers,code'],
            'description' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([Voucher::TYPE_FIXED, Voucher::TYPE_PERCENT])],
            'value' => ['required', 'numeric', 'min:0.01', Rule::when($request->input('type') === Voucher::TYPE_PERCENT, ['max:100'])],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $validated['min_subtotal'] ??= 0;

        Voucher::create($validated);

        return back()->with('status', "Voucher {$validated['code']} created.");
    }

    public function update(Voucher $voucher): RedirectResponse
    {
        $voucher->update(['is_active' => ! $voucher->is_active]);

        return back()->with('status', "Voucher {$voucher->code} ".($voucher->is_active ? 'activated.' : 'paused.'));
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        $voucher->delete();

        return back()->with('status', "Voucher {$voucher->code} deleted.");
    }
}
