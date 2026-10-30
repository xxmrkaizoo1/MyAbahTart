@props(['title', 'tone' => 'mint'])

@php
    [$wrapperClasses, $iconClasses] = match ($tone) {
        'berry' => ['bg-berry-50 ring-berry-200/70', 'bg-berry-600 text-white'],
        'butter' => ['bg-butter-50 ring-butter-200', 'bg-butter-300 text-plum-900'],
        default => ['bg-mint-50 ring-mint-100', 'bg-mint-600 text-white'],
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex gap-3 rounded-3xl p-4 ring-1 sm:p-5 '.$wrapperClasses]) }}>
    <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $iconClasses }}">
        <x-icon name="sparkles" class="size-4.5" />
    </span>
    <div class="min-w-0 text-sm">
        <p class="font-bold text-ink">{{ $title }}</p>
        <div class="mt-0.5 leading-relaxed text-ink-soft">{{ $slot }}</div>
    </div>
</div>
