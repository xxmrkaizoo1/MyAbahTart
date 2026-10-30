<x-layouts.shop :title="$product->name">
    <div class="container-page py-6 sm:py-10">
        <nav class="flex flex-wrap items-center gap-1.5 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-ink">Home</a> <span>/</span>
            <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" class="hover:text-ink">{{ $product->category->name }}</a> <span>/</span>
            <span class="font-semibold text-ink">{{ $product->name }}</span>
        </nav>

        <div class="mt-6 grid gap-8 lg:grid-cols-2 lg:gap-14">
            {{-- Visual --}}
            <div class="lg:sticky lg:top-28 lg:self-start">
                <div class="relative overflow-hidden rounded-4xl shadow-soft">
                    <x-product-image :product="$product" class="aspect-square" />
                    @foreach (\App\Enums\ProductSize::cases() as $size)
                        @if ($product->offersSize($size) && $product->sizeImageUrl($size))
                            <img src="{{ $product->sizeImageUrl($size) }}" alt="{{ $product->name }}, {{ $size->label() }}" data-size-photo="{{ $size->value }}"
                                class="pointer-events-none absolute inset-0 size-full bg-paper-deep object-cover opacity-0 transition-opacity duration-300">
                        @endif
                    @endforeach
                    <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                        @if ($product->is_seasonal)
                            <span class="chip bg-plum-900 px-3 py-1.5 text-white">Limited edition</span>
                        @endif
                        @if ($product->is_bestseller)
                            <span class="chip bg-butter-300 px-3 py-1.5 text-plum-900">Bestseller</span>
                        @endif
                    </div>
                </div>
                <ul class="mt-4 grid grid-cols-3 gap-3 text-center text-xs font-semibold">
                    @foreach ([['chef', 'Prepared fresh'], ['sparkles', 'No preservatives'], ['truck', 'Same-day option']] as [$icon, $label])
                        <li class="rounded-2xl bg-surface p-3 ring-1 ring-ink/5"><x-icon :name="$icon" class="mx-auto size-5 text-berry-600" /><span class="mt-1.5 block">{{ $label }}</span></li>
                    @endforeach
                </ul>
            </div>

            {{-- Details --}}
            <div>
                <p class="eyebrow">{{ $product->category->name }}</p>
                <h1 class="mt-2 text-4xl leading-tight font-extrabold sm:text-5xl">{{ $product->name }}</h1>
                <p class="mt-2 text-lg text-ink-soft">{{ $product->tagline }}</p>

                <a href="#reviews" class="mt-4 inline-flex items-center gap-2 text-sm">
                    <x-stars :rating="$product->reviews_avg_rating ?? 0" />
                    <span class="font-bold">{{ number_format((float) $product->reviews_avg_rating, 1) }}</span>
                    <span class="text-ink-soft underline-offset-4 hover:underline">{{ $product->reviews_count }} {{ Str::plural('review', $product->reviews_count) }}</span>
                </a>

                <div class="mt-6 flex items-end gap-3 border-y border-ink/10 py-5">
                    <span data-size-price class="font-display text-4xl font-extrabold text-berry-700">@rm($product->priceFor($product->defaultSize()))</span>
                    <span class="pb-1 text-ink-soft">/ {{ $product->unitLabel() }}</span>
                    <span class="ml-auto pb-1 text-sm font-semibold">
                        @if ($product->isSoldOut())
                            <span class="text-rose-700">Sold out</span>
                        @elseif ($product->isLowStock())
                            <span class="text-rose-700">Only {{ $product->stock }} left</span>
                        @else
                            <span class="flex items-center gap-1.5 text-mint-600"><span class="size-2 rounded-full bg-mint-500"></span> In stock</span>
                        @endif
                    </span>
                </div>

                <p class="mt-5 leading-relaxed text-ink-soft">{{ $product->description }}</p>

                <form method="POST" action="{{ route('cart.store', $product) }}" class="mt-7 grid grid-cols-[auto_1fr] gap-3">
                    @csrf
                    @if ($product->hasSizes())
                        <fieldset class="col-span-2 mb-1">
                            <legend class="label">Choose a size</legend>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($product->sizePrices() as $code => $price)
                                    @php($size = \App\Enums\ProductSize::from($code))
                                    @php($left = $product->stockFor($size))
                                    <label @class(['cursor-pointer' => $left > 0, 'cursor-not-allowed' => $left === 0])>
                                        <input type="radio" name="size" value="{{ $code }}" data-price="RM {{ number_format($price, 2) }}" data-stock="{{ $left }}" class="peer sr-only" @disabled($left === 0) @checked(old('size', $product->defaultSize()->value) === $code && $left > 0)>
                                        <span class="flex flex-col items-center rounded-2xl bg-surface px-3 py-3 ring-1 ring-ink/15 transition peer-checked:bg-berry-50 peer-checked:ring-2 peer-checked:ring-berry-600 peer-focus-visible:ring-2 peer-focus-visible:ring-berry-500 peer-disabled:opacity-50 not-peer-disabled:hover:ring-ink/30">
                                            <span class="font-display text-2xl font-extrabold">{{ $code }}</span>
                                            <span class="text-xs font-semibold text-ink-soft">{{ $size->label() }}</span>
                                            @if (($details = $product->sizeDetails($size)) !== '')
                                                <span class="mt-1 rounded-full bg-butter-100 px-2 py-0.5 text-center text-[11px] font-bold text-ink">{{ $details }}</span>
                                            @endif
                                            <span class="mt-1 text-sm font-bold text-berry-700">RM {{ number_format($price, 2) }}</span>
                                            <span @class(['mt-0.5 text-[11px] font-semibold', 'text-rose-700' => $left <= $product->low_stock_threshold, 'text-ink-soft' => $left > $product->low_stock_threshold])>
                                                {{ $left === 0 ? 'Sold out' : ($left <= $product->low_stock_threshold ? "Only {$left} left" : "{$left} in stock") }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('size') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                        </fieldset>
                    @endif
                    <div data-stepper class="flex items-center rounded-full bg-surface ring-1 ring-ink/15">
                        <button type="button" data-step="-1" class="grid size-12 place-items-center rounded-full text-ink-soft hover:text-ink" aria-label="Decrease quantity"><x-icon name="minus" class="size-4" /></button>
                        <input type="number" name="quantity" value="1" min="1" max="{{ max(1, min(99, $product->stockFor($product->defaultSize()))) }}" class="w-8 border-0 bg-transparent p-0 text-center font-bold focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none" aria-label="Quantity">
                        <button type="button" data-step="1" class="grid size-12 place-items-center rounded-full text-ink-soft hover:text-ink" aria-label="Increase quantity"><x-icon name="plus" class="size-4" /></button>
                    </div>
                    <button type="submit" class="btn-primary py-3.5 text-base" @disabled($product->isSoldOut())><x-icon name="bag" class="size-5" /> Add to cart</button>
                    <button type="submit" name="buy_now" value="1" class="btn-dark col-span-2 py-3.5 text-base" @disabled($product->isSoldOut())>Buy it now</button>
                </form>

                @if ($boughtTogether->isNotEmpty())
                    <div class="mt-8">
                        <p class="flex items-center gap-1.5 text-sm font-bold text-mint-700"><x-icon name="sparkles" class="size-4" /> Often bought together</p>
                        <ul class="mt-3 space-y-2">
                            @foreach ($boughtTogether as $companion)
                                <li class="flex items-center gap-3 rounded-2xl bg-surface p-2 pr-3 ring-1 ring-ink/5">
                                    <x-product-image :product="$companion" class="size-14 shrink-0 rounded-xl" />
                                    <a href="{{ route('products.show', $companion) }}" class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold">{{ $companion->name }}</span>
                                        <span class="block text-xs text-ink-soft">{{ $companion->hasSizes() ? 'From ' : '' }}@rm($companion->price) · {{ $companion->unitLabel() }}</span>
                                    </a>
                                    @if ($companion->hasSizes())
                                        <a href="{{ route('products.show', $companion) }}" class="grid size-9 place-items-center rounded-full bg-paper-deep hover:bg-berry-600 hover:text-white" aria-label="Choose a size for {{ $companion->name }}"><x-icon name="arrow-right" class="size-4" /></a>
                                    @else
                                        <form method="POST" action="{{ route('cart.store', $companion) }}">
                                            @csrf
                                            <button class="grid size-9 place-items-center rounded-full bg-paper-deep hover:bg-berry-600 hover:text-white" aria-label="Add {{ $companion->name }}"><x-icon name="plus" class="size-4" /></button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-8 divide-y divide-ink/10 border-y border-ink/10">
                    @foreach ([
                        ['Delivery & pickup', collect(\App\Enums\DeliveryMethod::enabledCases())->map(fn ($method) => $method->label().': '.$method->description().' ('.($method->feeFor(0) == 0 ? 'free' : 'RM '.number_format($method->feeFor(0), 2)).($method === \App\Enums\DeliveryMethod::Standard && \App\Enums\DeliveryMethod::freeDeliveryThreshold() ? ', free over RM '.number_format(\App\Enums\DeliveryMethod::freeDeliveryThreshold(), 2) : '').').')->implode(' ')],
                        ['Storage', 'Best enjoyed within 3 days. Keep in an airtight container; refrigerate cream and custard tarts and bring to room temperature before eating.'],
                        ['Allergens', 'Made in a kitchen that handles wheat, dairy, eggs and nuts.'],
                    ] as [$heading, $copy])
                        <details class="group py-4" @if ($loop->first) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between font-bold">
                                {{ $heading }} <x-icon name="plus" class="size-4 transition group-open:rotate-45" />
                            </summary>
                            <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $copy }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Reviews --}}
        <section id="reviews" class="mt-16 scroll-mt-28 sm:mt-24">
            <div class="grid gap-8 lg:grid-cols-[300px_1fr] lg:gap-12">
                <div>
                    <p class="eyebrow">Reviews</p>
                    <h2 class="section-title mt-1">What people say</h2>
                    <div class="mt-5 flex items-end gap-3">
                        <span class="font-display text-6xl font-extrabold">{{ number_format((float) $product->reviews_avg_rating, 1) }}</span>
                        <div class="pb-2">
                            <x-stars :rating="$product->reviews_avg_rating ?? 0" />
                            <p class="mt-1 text-sm text-ink-soft">{{ $product->reviews_count }} {{ Str::plural('review', $product->reviews_count) }}</p>
                        </div>
                    </div>
                    <ul class="mt-5 space-y-2">
                        @for ($stars = 5; $stars >= 1; $stars--)
                            @php($share = $product->reviews_count ? ($ratingBreakdown[$stars] ?? 0) / $product->reviews_count * 100 : 0)
                            <li class="flex items-center gap-3 text-sm">
                                <span class="w-3 font-semibold">{{ $stars }}</span>
                                <div class="h-2 flex-1 rounded-full bg-ink/5"><div class="h-full rounded-full bg-butter-400" style="width: {{ $share }}%"></div></div>
                                <span class="w-6 text-right text-ink-soft">{{ $ratingBreakdown[$stars] ?? 0 }}</span>
                            </li>
                        @endfor
                    </ul>

                    @if ($canReview)
                        <form method="POST" action="{{ route('reviews.store', $product) }}" class="card mt-8 p-5">
                            @csrf
                            <p class="font-bold">Rate this product</p>
                            <fieldset class="mt-2 flex flex-row-reverse justify-end gap-1">
                                <legend class="sr-only">Rating</legend>
                                @for ($star = 5; $star >= 1; $star--)
                                    <input type="radio" id="rating-{{ $star }}" name="rating" value="{{ $star }}" class="peer sr-only" @checked(old('rating', 5) == $star)>
                                    <label for="rating-{{ $star }}" class="cursor-pointer text-ink/15 peer-checked:text-butter-500 hover:text-butter-500 [&:hover~label]:text-butter-500" title="{{ $star }} {{ Str::plural('star', $star) }}">
                                        <x-icon name="star" filled class="size-7" />
                                    </label>
                                @endfor
                            </fieldset>
                            <textarea name="comment" rows="3" required minlength="5" placeholder="Tell others what you thought…" class="input mt-3">{{ old('comment') }}</textarea>
                            @error('comment') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                            <button class="btn-dark mt-3 w-full">Post review</button>
                        </form>
                    @endif
                </div>

                @if ($reviews->isEmpty())
                    <p class="card grid place-items-center p-10 text-center text-ink-soft">No reviews yet — be the first to try it!</p>
                @else
                    <div class="columns-1 gap-4 sm:columns-2 xl:columns-3">
                        @foreach ($reviews as $review)
                            <article class="card mb-4 break-inside-avoid p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <x-stars :rating="$review->rating" size="size-4" />
                                    @if (auth()->user()?->isAdmin())
                                        <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" data-confirm="Delete this review by {{ $review->user->name }}? This can’t be undone.">
                                            @csrf
                                            @method('DELETE')
                                            <button class="-mt-1.5 -mr-1.5 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold text-rose-700 hover:bg-rose-50" aria-label="Delete review by {{ $review->user->name }}">
                                                <x-icon name="trash" class="size-3.5" /> Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                <p class="mt-3 leading-relaxed">“{{ $review->comment }}”</p>
                                <div class="mt-4 flex items-center gap-3 text-sm">
                                    <span class="grid size-9 place-items-center rounded-full bg-berry-100 text-xs font-extrabold text-berry-800">{{ $review->user->initials() }}</span>
                                    <span class="font-bold">{{ $review->user->name }}</span>
                                    <span class="ml-auto text-xs text-ink-soft">{{ $review->created_at->diffForHumans() }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        @if ($related->isNotEmpty())
            <section class="mt-16 sm:mt-24">
                <p class="eyebrow">You may also like</p>
                <h2 class="section-title mt-1">More from {{ $product->category->name }}</h2>
                <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-6 lg:grid-cols-4">
                    @foreach ($related as $relatedProduct)
                        <x-product-card :product="$relatedProduct" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.shop>
