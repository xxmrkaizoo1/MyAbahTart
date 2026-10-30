@props(['product', 'reason' => null, 'rank' => null])

<article {{ $attributes->merge(['class' => 'group relative flex flex-col']) }}>
    <div class="relative">
        <a href="{{ route('products.show', $product) }}" class="block overflow-hidden rounded-3xl" tabindex="-1">
            <x-product-image :product="$product" class="aspect-[4/5] transition duration-500 group-hover:scale-[1.04]" />
        </a>

        <div class="pointer-events-none absolute top-3 left-3 flex flex-wrap gap-1.5">
            @if ($product->is_seasonal)
                <span class="chip bg-plum-900 text-white">Limited</span>
            @endif
            @if ($product->is_bestseller)
                <span class="chip bg-butter-300 text-plum-900">Bestseller</span>
            @endif
            @if ($product->isSoldOut())
                <span class="chip bg-surface text-ink">Sold out</span>
            @elseif ($product->isLowStock())
                <span class="chip bg-rose-600 text-white">Only {{ $product->stock }} left</span>
            @endif
        </div>

        @if ($rank)
            <span class="pointer-events-none absolute -bottom-3 left-3 font-display text-6xl leading-none font-extrabold text-white [text-shadow:0_2px_12px_rgb(33_20_37/0.35)]">{{ $rank }}</span>
        @endif

        @if ($product->hasSizes())
            <a href="{{ route('products.show', $product) }}"
                class="absolute right-3 bottom-3 flex h-11 items-center gap-1 rounded-full bg-surface pr-4 pl-3 text-xs font-bold text-ink shadow-pop transition hover:bg-berry-600 hover:text-white active:scale-90"
                aria-label="Choose a size for {{ $product->name }}">
                <x-icon name="plus" class="size-4" /> {{ implode(' · ', array_keys($product->sizePrices())) }}
            </a>
        @else
            <form method="POST" action="{{ route('cart.store', $product) }}" class="absolute right-3 bottom-3">
                @csrf
                <button type="submit" @disabled($product->isSoldOut())
                    class="grid size-11 place-items-center rounded-full bg-surface text-ink shadow-pop transition hover:bg-berry-600 hover:text-white active:scale-90 disabled:opacity-60"
                    aria-label="Add {{ $product->name }} to cart">
                    <x-icon name="plus" class="size-5" />
                </button>
            </form>
        @endif
    </div>

    <div class="mt-3.5 flex flex-1 flex-col px-1">
        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="truncate font-semibold text-ink-soft">{{ $product->category->name }}</span>
            @if ($product->reviews_avg_rating)
                <span class="flex shrink-0 items-center gap-1 font-bold text-ink">
                    <x-icon name="star" filled class="size-3.5 text-butter-500" /> {{ number_format((float) $product->reviews_avg_rating, 1) }}
                </span>
            @endif
        </div>
        <a href="{{ route('products.show', $product) }}" class="mt-1 font-display text-lg leading-snug font-bold text-ink decoration-berry-400 decoration-2 underline-offset-4 hover:underline">
            {{ $product->name }}
        </a>
        @if ($reason)
            <p class="mt-1 flex items-center gap-1 text-xs font-semibold text-mint-700"><x-icon name="sparkles" class="size-3.5" /> {{ $reason }}</p>
        @else
            <p class="mt-0.5 line-clamp-1 text-sm text-ink-soft">{{ $product->tagline }}</p>
        @endif
        <p class="mt-2 text-sm">
            <span class="font-bold text-berry-700">{{ $product->hasSizes() ? 'From ' : '' }}@rm($product->price)</span>
            <span class="text-ink-soft">· {{ $product->unitLabel() }}</span>
        </p>
    </div>
</article>
