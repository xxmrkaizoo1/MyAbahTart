{{-- Why a cancelled order was cancelled. Expects $order. --}}
@if ($order->status === \App\Enums\OrderStatus::Cancelled && $order->cancellation_reason)
    <div class="mt-5 flex gap-3 rounded-3xl bg-rose-50 p-4 text-sm text-rose-900 ring-1 ring-rose-200 sm:p-5">
        <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-rose-100 text-rose-700"><x-icon name="alert" class="size-5" /></span>
        <div class="min-w-0">
            <p class="font-bold">Cancelled{{ $order->cancelled_at ? ' on '.$order->cancelled_at->format('j M, g:i a') : '' }} · {{ $order->cancellation_reason->label() }}</p>
            @if ($order->cancellation_note)
                <p class="mt-1 whitespace-pre-line text-rose-800">“{{ $order->cancellation_note }}”</p>
            @endif
        </div>
    </div>
@endif
