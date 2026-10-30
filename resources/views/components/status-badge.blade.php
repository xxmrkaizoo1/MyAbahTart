@props(['order'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.$order->status->badgeClasses()]) }}>
    @if ($order->status->isActive())
        <span class="relative flex size-1.5">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-60"></span>
            <span class="relative inline-flex size-1.5 rounded-full bg-current"></span>
        </span>
    @endif
    {{ $order->statusLabel() }}
</span>
