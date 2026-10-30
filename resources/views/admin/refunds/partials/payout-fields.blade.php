{{-- Amount, method, restock and note fields shared by "approve request" and "issue refund". Expects $order, $suggestedAmount and $prefix. --}}
@php($refundErrors = $errors->getBag('refund'))
<div class="grid grid-cols-2 gap-3">
    <div>
        <label for="{{ $prefix }}-amount" class="label">Amount (RM)</label>
        <input id="{{ $prefix }}-amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $order->refundableAmount() }}"
            value="{{ $refundErrors->any() ? old('amount') : number_format($suggestedAmount, 2, '.', '') }}" required class="input bg-paper">
    </div>
    <div>
        <label for="{{ $prefix }}-method" class="label">Paid back by</label>
        <select id="{{ $prefix }}-method" name="method" class="input bg-paper">
            @foreach (\App\Enums\RefundMethod::cases() as $method)
                <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </select>
    </div>
</div>
<p class="text-xs text-ink-soft">Up to @rm($order->refundableAmount()) can still be refunded on this order.</p>
<label class="flex items-start gap-2.5 rounded-2xl bg-paper p-3 text-sm">
    <input type="checkbox" name="restock" value="1" class="mt-0.5 size-4.5 rounded border-ink/20 text-berry-600 focus:ring-berry-500" @checked(old('restock'))>
    <span><span class="font-semibold">Put the items back into stock</span><span class="block text-xs text-ink-soft">Only tick this if the items came back unopened and can be sold again.</span></span>
</label>
<div>
    <label for="{{ $prefix }}-note" class="label">Note <span class="font-normal text-ink-soft">(the customer sees this)</span></label>
    <textarea id="{{ $prefix }}-note" name="admin_note" rows="2" maxlength="1000" class="input bg-paper">{{ $refundErrors->any() ? old('admin_note') : '' }}</textarea>
</div>
@foreach ($refundErrors->all() as $message)
    <p class="text-sm text-rose-700">{{ $message }}</p>
@endforeach
