@php
    use App\Enums\OrderStatus;

    $steps = OrderStatus::timeline();
    $currentIndex = array_search($order->status, $steps, true);
    $finished = $order->status === OrderStatus::Completed;
    $icons = ['check', 'whisk', $order->delivery_method->requiresAddress() ? 'truck' : 'store', 'gift'];
    $hints = [
        'Confirmed by the kitchen',
        'Our team is preparing it',
        $order->delivery_method->requiresAddress() ? 'Heading to your door' : 'Waiting at the counter',
        'Enjoy every bite!',
    ];
@endphp

@if ($order->status === OrderStatus::Cancelled)
    <div class="flex items-center gap-3 rounded-2xl bg-rose-50 p-4 text-sm font-semibold text-rose-800 ring-1 ring-rose-200">
        <x-icon name="x" class="size-5" />
        This order was cancelled {{ $order->cancelled_at?->format('j M Y, g:i a') }}.
    </div>
@else
    <ol class="grid gap-0 sm:grid-cols-4 sm:gap-2">
        @foreach ($steps as $index => $step)
            @php
                $done = $finished || $index < $currentIndex;
                $current = ! $finished && $index === $currentIndex;
                $time = $order->timestampFor($step);
            @endphp
            <li class="relative flex gap-4 pb-6 last:pb-0 sm:flex-col sm:gap-3 sm:pb-0">
                {{-- connector --}}
                @unless ($loop->last)
                    <span @class([
                        'absolute top-11 bottom-0 left-[21px] w-0.5 sm:top-[21px] sm:right-0 sm:bottom-auto sm:left-12 sm:h-0.5 sm:w-auto',
                        'bg-berry-600' => $done,
                        'bg-ink/10' => ! $done,
                    ])></span>
                @endunless
                <span @class([
                    'relative z-10 grid size-11 shrink-0 place-items-center rounded-full',
                    'bg-berry-600 text-white' => $done,
                    'bg-butter-300 text-plum-900 ring-4 ring-butter-100' => $current,
                    'bg-surface text-ink/30 ring-1 ring-ink/10' => ! $done && ! $current,
                ])>
                    <x-icon :name="$icons[$index]" class="size-5" />
                </span>
                <div class="pt-1.5 sm:pt-0">
                    <p @class(['font-bold', 'text-ink/40' => ! $done && ! $current])>{{ $step === OrderStatus::Ready ? $order->delivery_method->readyLabel() : $step->label() }}</p>
                    <p class="text-sm text-ink-soft">{{ $hints[$index] }}</p>
                    @if ($time)
                        <p class="mt-1 text-xs font-bold text-berry-700">{{ $time->format('g:i a · j M') }}</p>
                    @elseif ($step === OrderStatus::Ready && $estimate)
                        <p class="mt-1 text-xs font-bold text-butter-600">Est. {{ $estimate->format('g:i a') }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
