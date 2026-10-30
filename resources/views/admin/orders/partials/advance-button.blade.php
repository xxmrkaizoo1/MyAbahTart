@if ($next = $order->status->next())
    <form method="POST" action="{{ route('admin.orders.update', $order) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" value="{{ $next->value }}">
        <button @class([
            'inline-flex items-center gap-1.5 rounded-full px-3.5 py-2 text-xs font-bold whitespace-nowrap transition active:scale-95',
            'bg-butter-300 text-plum-900 hover:bg-butter-400' => $next->value === 'preparing',
            'bg-mint-600 text-white hover:bg-mint-700' => $next->value === 'ready',
            'bg-plum-900 text-white hover:bg-plum-700' => $next->value === 'completed',
        ])>
            {{ $next->actionLabel() }} <x-icon name="arrow-right" class="size-3.5" />
        </button>
    </form>
@endif
