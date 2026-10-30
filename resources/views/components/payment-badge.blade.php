@props(['order'])

@if ($order->payment_status)
    @php($isPaid = $order->payment_status === \App\Enums\PaymentStatus::Paid)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.($order->isCashOnDelivery() && ! $isPaid ? 'bg-paper-deep text-ink ring-ink/10' : $order->payment_status->badgeClasses())]) }}>
        <x-icon :name="$isPaid ? 'check' : ($order->isCashOnDelivery() ? 'ticket' : 'clock')" class="size-3.5" />
        {{ $order->paymentLabel() }}
    </span>
@endif
