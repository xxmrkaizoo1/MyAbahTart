<x-layouts.admin title="Refunds" subtitle="Review customer refund requests and keep track of money paid back.">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
            <p class="text-sm font-semibold text-ink-soft">Waiting for review</p>
            <p class="mt-1 font-display text-3xl font-extrabold {{ $pendingCount > 0 ? 'text-rose-700' : '' }}">{{ $pendingCount }}</p>
        </div>
        <div class="card p-5">
            <p class="text-sm font-semibold text-ink-soft">Refunded this month</p>
            <p class="mt-1 font-display text-3xl font-extrabold">@rm($refundedThisMonth)</p>
        </div>
        <div class="card p-5">
            <p class="text-sm font-semibold text-ink-soft">Refunds this month</p>
            <p class="mt-1 font-display text-3xl font-extrabold">{{ $refundCountThisMonth }}</p>
        </div>
    </div>

    <nav class="scrollbar-none -mx-4 mt-6 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Filter refunds">
        @foreach ([null => 'All', ...collect(\App\Enums\RefundStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()] as $value => $label)
            <a href="{{ route('admin.refunds.index', array_filter(['status' => $value])) }}" @class([
                'shrink-0 rounded-full px-4 py-2 text-sm font-semibold ring-1 transition',
                'bg-plum-900 text-white ring-plum-900' => ($status?->value ?? '') === (string) $value,
                'bg-surface text-ink-soft ring-ink/10 hover:text-ink' => ($status?->value ?? '') !== (string) $value,
            ])>{{ $label }}</a>
        @endforeach
    </nav>

    <section class="card mt-4 overflow-hidden">
        @if ($refunds->isEmpty())
            <div class="flex flex-col items-center px-6 py-16 text-center">
                <span class="grid size-16 place-items-center rounded-full bg-mint-50 text-mint-700"><x-icon name="check" class="size-7" /></span>
                <p class="mt-4 font-display text-xl font-bold">No refunds here</p>
                <p class="mt-1 max-w-sm text-sm text-ink-soft">Customers can request a refund for up to {{ \App\Models\Refund::REQUEST_WINDOW_DAYS }} days after their order is completed.</p>
            </div>
        @else
            <ul class="divide-y divide-ink/5">
                @foreach ($refunds as $refund)
                    <li>
                        <a href="{{ route('admin.orders.show', $refund->order) }}#refunds" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 transition hover:bg-paper sm:px-6">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-butter-200 text-xs font-extrabold text-plum-900">{{ $refund->order->customerInitials() }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-bold">{{ $refund->order->customerName() }} <span class="font-normal text-ink-soft">· {{ $refund->order->code }}</span></p>
                                <p class="truncate text-sm text-ink-soft">
                                    {{ $refund->reason->label() }}
                                    @if ($refund->items->isNotEmpty())
                                        · {{ $refund->items->map(fn ($item) => $item->quantity.'× '.$item->orderItem->displayName())->implode(', ') }}
                                    @endif
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-display text-lg font-bold">
                                    @if ($refund->status === \App\Enums\RefundStatus::Approved)
                                        @rm($refund->amount)
                                    @elseif ($refund->status === \App\Enums\RefundStatus::Requested)
                                        @rm($refund->itemsValue())
                                    @else
                                        —
                                    @endif
                                </p>
                                <p class="text-xs text-ink-soft">{{ ($refund->processed_at ?? $refund->created_at)->diffForHumans() }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $refund->status->badgeClasses() }}">{{ $refund->status->label() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="mt-6"><x-pagination :paginator="$refunds" /></div>
</x-layouts.admin>
