<x-layouts.shop>
    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -top-40 right-[-10%] size-[36rem] rounded-full bg-butter-200/70 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-40 left-[-15%] size-[30rem] rounded-full bg-berry-100 blur-3xl" aria-hidden="true"></div>

        <div class="container-page relative grid items-center gap-10 py-10 sm:py-14 lg:grid-cols-[1.05fr_1fr] lg:py-20">
            <div class="text-center lg:text-left">
                @if ($storefront->get('hero_badge') !== '')
                    <p class="inline-flex items-center gap-2 rounded-full bg-surface px-3 py-1.5 text-xs font-bold text-ink shadow-soft ring-1 ring-ink/5">
                        <span class="size-2 rounded-full bg-mint-500"></span> {{ $storefront->get('hero_badge') }}
                    </p>
                @endif
                <h1 class="mt-5 text-[2.6rem] leading-[1.02] font-extrabold sm:text-6xl lg:text-7xl">
                    {{ $storefront->get('hero_title_start') }}
                    <span class="relative inline-block text-berry-600">
                        {{ $storefront->get('hero_title_highlight') }}
                        <svg class="absolute -bottom-2 left-0 w-full text-butter-400" viewBox="0 0 200 12" preserveAspectRatio="none" aria-hidden="true"><path d="M2 9C50 3 150 3 198 8" stroke="currentColor" stroke-width="5" fill="none" stroke-linecap="round" /></svg>
                    </span>
                    {{ $storefront->get('hero_title_end') }}
                </h1>
                @if ($storefront->get('hero_intro') !== '')
                    <p class="mx-auto mt-6 max-w-lg text-base leading-relaxed text-ink-soft sm:text-lg lg:mx-0">{{ $storefront->get('hero_intro') }}</p>
                @endif
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row lg:justify-start">
                    <a href="{{ route('products.index') }}" class="btn-primary px-7 py-3.5 text-base">{{ $storefront->get('hero_button') }} <x-icon name="arrow-right" class="size-4.5" /></a>
                    <a href="{{ auth()->check() ? route('orders.index') : route('register') }}" class="btn-outline px-7 py-3.5 text-base">
                        {{ auth()->check() ? 'Track my order' : 'Create an account' }}
                    </a>
                </div>

                <dl class="mx-auto mt-10 grid max-w-md grid-cols-3 gap-3 lg:mx-0">
                    @foreach ([[$stats['flavours'], 'Flavours'], [$stats['rating'].'★', 'Avg. rating'], [number_format($stats['orders']), 'Orders this month']] as [$value, $label])
                        <div class="rounded-2xl bg-surface/70 p-3 ring-1 ring-ink/5 backdrop-blur">
                            <dt class="text-[11px] font-semibold text-ink-soft sm:text-xs">{{ $label }}</dt>
                            <dd class="font-display text-xl font-extrabold sm:text-2xl">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Collage --}}
            <div class="relative mx-auto aspect-square w-full max-w-[22rem] sm:max-w-md lg:max-w-none">
                <div class="absolute inset-[6%] overflow-hidden rounded-full bg-butter-300 shadow-pop ring-8 ring-surface">
                    @if ($storefront->heroImageUrl())
                        <img src="{{ $storefront->heroImageUrl() }}" alt="" class="size-full object-cover">
                    @elseif ($featured)
                        <x-product-image :product="$featured" class="size-full" />
                    @else
                        <img src="{{ \App\Models\Product::defaultImageUrl() }}" alt="" class="size-full object-cover">
                    @endif
                </div>
                @foreach ($heroProducts->take(2) as $index => $product)
                    <a href="{{ route('products.show', $product) }}" @class([
                        'absolute size-[30%] overflow-hidden rounded-full shadow-pop ring-4 ring-surface animate-float',
                        '-top-[2%] -left-[2%]' => $index === 0,
                        'right-[-2%] bottom-[12%] [animation-delay:1.5s]' => $index === 1,
                    ])>
                        <x-product-image :product="$product" class="size-full" />
                    </a>
                @endforeach
                @if ($featured)
                    <a href="{{ route('products.show', $featured) }}" class="absolute bottom-[2%] left-[2%] flex items-center gap-3 rounded-2xl bg-surface/95 p-2.5 pr-4 shadow-pop ring-1 ring-ink/5 backdrop-blur sm:bottom-[6%]">
                        <span class="grid size-10 place-items-center rounded-xl bg-plum-900 text-butter-300"><x-icon name="sparkles" class="size-5" /></span>
                        <span class="leading-tight">
                            <span class="block text-[10px] font-bold tracking-wider text-berry-600 uppercase">Limited edition</span>
                            <span class="block text-sm font-bold">{{ $featured->name }}</span>
                            <span class="block text-xs text-ink-soft">{{ $featured->hasSizes() ? 'From ' : '' }}@rm($featured->price) · {{ $featured->stock }} left today</span>
                        </span>
                    </a>
                @endif
            </div>
        </div>
    </section>

    {{-- Ticker --}}
    <div class="overflow-hidden border-y border-ink/5 bg-berry-600 py-3.5 text-white" aria-hidden="true">
        @php
            $tickerNames = $storefront->tickerItems()->whenEmpty(fn () => $marquee)->whenEmpty(fn () => collect(['Prepared fresh daily']));
            $tickerNames = collect(array_fill(0, (int) ceil(12 / $tickerNames->count()), $tickerNames))->flatten();
        @endphp
        <div class="flex w-max animate-marquee font-display text-lg font-bold whitespace-nowrap sm:text-xl">
            @foreach ([1, 2] as $loopCopy)
                <div class="flex shrink-0 gap-8 pr-8">
                    @foreach ($tickerNames as $name)
                        <span class="flex items-center gap-8">{{ $name }} <span class="text-butter-300">✦</span></span>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <div class="container-page">
        {{-- Active order --}}
        @if ($activeOrder)
            <a href="{{ route('orders.show', $activeOrder) }}" class="mt-8 flex flex-wrap items-center gap-4 rounded-3xl bg-plum-900 p-4 text-white shadow-pop sm:p-5">
                <span class="grid size-12 place-items-center rounded-2xl bg-butter-300 text-plum-900"><x-icon name="chef" class="size-6" /></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-white/60">Order {{ $activeOrder->code }} is on its way through the kitchen</p>
                    <p class="font-display text-lg font-bold">{{ $activeOrder->statusLabel() }}</p>
                </div>
                <span class="btn-butter">Track order <x-icon name="arrow-right" class="size-4" /></span>
            </a>
        @endif

        {{-- Collections --}}
        <section class="mt-14 sm:mt-20">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">Collections</p>
                    <h2 class="section-title mt-1">Find your favourite</h2>
                </div>
                <a href="{{ route('products.index') }}" class="btn-ghost hidden sm:inline-flex">View all <x-icon name="arrow-right" class="size-4" /></a>
            </div>

            @php($tileColours = ['bg-butter-200', 'bg-berry-100', 'bg-[#f3e2d6]', 'bg-mint-100', 'bg-[#efe3f6]'])
            <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 lg:grid-rows-2">
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}" @class([
                        'group relative flex min-h-40 flex-col justify-between overflow-hidden rounded-3xl p-4 transition hover:-translate-y-1 hover:shadow-pop sm:p-5',
                        $tileColours[$loop->index % count($tileColours)],
                        'col-span-2 min-h-56 lg:row-span-2 lg:min-h-0' => $loop->first,
                    ])>
                        <div class="relative z-10">
                            <h3 @class(['font-display font-extrabold', 'text-3xl sm:text-4xl' => $loop->first, 'text-lg sm:text-xl' => ! $loop->first])>{{ $category->name }}</h3>
                            <p class="mt-1 text-sm font-semibold text-ink/60">{{ $category->products_count }} {{ Str::plural('product', $category->products_count) }}</p>
                        </div>
                        <span class="relative z-10 grid size-10 place-items-center rounded-full bg-surface text-ink shadow-soft transition group-hover:bg-plum-900 group-hover:text-white">
                            <x-icon name="arrow-right" class="size-4.5" />
                        </span>
                        <div @class([
                            'absolute overflow-hidden rounded-full transition duration-500 group-hover:rotate-12',
                            '-right-10 -bottom-10 size-56 sm:size-72 lg:-right-12 lg:-bottom-12 lg:size-80' => $loop->first,
                            '-right-6 -bottom-6 size-28 sm:size-32' => ! $loop->first,
                        ])>
                            <img src="{{ $category->imageUrl() }}" alt="" class="size-full object-cover" loading="lazy">
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Picked for you --}}
        <section class="mt-14 sm:mt-20">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="eyebrow flex items-center gap-1.5 text-mint-600"><x-icon name="sparkles" class="size-4" /> {{ auth()->check() ? 'Picked for you' : 'Trending now' }}</p>
                    <h2 class="section-title mt-1">{{ auth()->check() ? 'Based on what you love' : 'What everyone’s ordering' }}</h2>
                </div>
            </div>
            <div class="scrollbar-none -mx-4 mt-6 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-6 sm:overflow-visible sm:px-0 lg:grid-cols-4">
                @foreach ($recommendations as $recommendation)
                    <x-product-card :product="$recommendation['product']" :reason="$recommendation['reason']" class="w-[70%] shrink-0 snap-start sm:w-auto" />
                @endforeach
            </div>
        </section>
    </div>

    {{-- Limited edition spotlight --}}
    @if ($featured)
        <section class="relative mt-16 overflow-hidden bg-plum-900 text-white sm:mt-24">
            <div class="pointer-events-none absolute -top-24 -left-24 size-96 rounded-full bg-berry-600/30 blur-3xl" aria-hidden="true"></div>
            <div class="container-page relative grid items-center gap-10 py-14 sm:py-20 lg:grid-cols-2">
                <div class="relative mx-auto aspect-square w-full max-w-xs sm:max-w-sm lg:order-2 lg:max-w-md">
                    <div class="absolute inset-0 animate-[spin_40s_linear_infinite] rounded-full border-2 border-dashed border-butter-300/40"></div>
                    <div class="absolute inset-[8%] overflow-hidden rounded-full ring-8 ring-white/10">
                        <x-product-image :product="$featured" class="size-full" />
                    </div>
                    <span class="absolute top-[6%] right-[4%] grid size-20 rotate-12 place-items-center rounded-full bg-butter-300 text-center font-display text-sm leading-tight font-extrabold text-plum-900 shadow-pop sm:size-24 sm:text-base">Only<br>{{ $featured->stock }} left</span>
                </div>
                <div class="text-center lg:text-left">
                    <p class="eyebrow text-butter-300">Limited edition</p>
                    <h2 class="mt-3 text-4xl font-extrabold sm:text-5xl">{{ $featured->name }}</h2>
                    <p class="mx-auto mt-4 max-w-lg leading-relaxed text-white/70 lg:mx-0">{{ $featured->description }}</p>

                    <div class="mx-auto mt-6 max-w-sm lg:mx-0">
                        <div class="flex justify-between text-xs font-semibold text-white/60"><span>Today’s batch</span><span>{{ $featured->stock }} remaining</span></div>
                        <div class="mt-2 h-2 rounded-full bg-white/10"><div class="h-full rounded-full bg-butter-300" style="width: {{ max(6, $featured->stockPercentage()) }}%"></div></div>
                    </div>

                    <form method="POST" action="{{ route('cart.store', $featured) }}" class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                        @csrf
                        <input type="hidden" name="buy_now" value="1">
                        @if ($featured->hasSizes())
                            <select name="size" class="input w-auto rounded-full bg-white/10 py-3 pr-10 font-bold text-white ring-white/20" aria-label="Size">
                                @foreach ($featured->sizePrices() as $code => $price)
                                    @php($left = $featured->stockFor(\App\Enums\ProductSize::from($code)))
                                    <option value="{{ $code }}" class="text-ink" @disabled($left === 0) @selected($code === $featured->defaultSize()?->value)>{{ $featured->sizeLabel(\App\Enums\ProductSize::from($code)) }} · RM {{ number_format($price, 2) }}{{ $left === 0 ? ' · sold out' : '' }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="font-display text-3xl font-extrabold">@rm($featured->price)</span>
                        @endif
                        <button class="btn-butter px-7 py-3.5 text-base sm:ml-3" @disabled($featured->isSoldOut())>Order now <x-icon name="arrow-right" class="size-4.5" /></button>
                        <a href="{{ route('products.show', $featured) }}" class="btn px-5 py-3.5 text-white ring-1 ring-white/20 hover:bg-white/10">Details</a>
                    </form>
                </div>
            </div>
        </section>
    @endif

    <div class="container-page">
        {{-- Favourites --}}
        <section class="mt-16 sm:mt-24">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">Bestsellers</p>
                    <h2 class="section-title mt-1">This month’s favourites</h2>
                </div>
                <a href="{{ route('products.index', ['sort' => 'popular']) }}" class="btn-ghost hidden sm:inline-flex">See all <x-icon name="arrow-right" class="size-4" /></a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-6 lg:grid-cols-4">
                @foreach ($bestsellers as $product)
                    <x-product-card :product="$product" :rank="$loop->iteration" />
                @endforeach
            </div>
        </section>

        {{-- How it works --}}
        <section class="mt-16 rounded-4xl bg-surface p-6 shadow-soft ring-1 ring-ink/5 sm:mt-24 sm:p-10 lg:p-14">
            <div class="text-center">
                <p class="eyebrow">How it works</p>
                <h2 class="section-title mt-1">{{ $storefront->get('steps_title') }}</h2>
            </div>
            <ol class="mt-10 grid gap-8 md:grid-cols-3 md:gap-6">
                @foreach ($storefront->steps() as ['icon' => $icon, 'title' => $title, 'text' => $text])
                    <li class="relative text-center">
                        <span class="mx-auto grid size-16 place-items-center rounded-3xl bg-berry-50 text-berry-600 ring-1 ring-berry-100"><x-icon :name="$icon" class="size-7" /></span>
                        <span class="absolute top-0 left-1/2 ml-6 grid size-7 place-items-center rounded-full bg-plum-900 text-xs font-extrabold text-white">{{ $loop->iteration }}</span>
                        <h3 class="mt-5 font-display text-xl font-bold">{{ $title }}</h3>
                        <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-ink-soft">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Reviews --}}
        @if ($reviews->isNotEmpty())
            <section class="mt-16 sm:mt-24">
                <div class="text-center">
                    <p class="eyebrow">Reviews</p>
                    <h2 class="section-title mt-1">Loved by our regulars</h2>
                </div>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    @foreach ($reviews as $review)
                        <figure @class(['flex flex-col rounded-3xl p-6', 'bg-butter-200' => $loop->index === 0, 'bg-berry-100' => $loop->index === 1, 'bg-mint-100' => $loop->index === 2])>
                            <x-icon name="quote" class="size-8 text-ink/30" />
                            <blockquote class="mt-3 flex-1 text-base leading-relaxed font-medium">“{{ $review->comment }}”</blockquote>
                            <figcaption class="mt-6 flex items-center gap-3">
                                <span class="grid size-10 place-items-center rounded-full bg-surface text-sm font-extrabold">{{ $review->user->initials() }}</span>
                                <span class="min-w-0 text-sm">
                                    <span class="block font-bold">{{ $review->user->name }}</span>
                                    <a href="{{ route('products.show', $review->product) }}" class="block truncate text-ink/60 hover:underline">on {{ $review->product->name }}</a>
                                </span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- New --}}
        <section class="mt-16 sm:mt-24">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">Just landed</p>
                    <h2 class="section-title mt-1">New on the menu</h2>
                </div>
                <a href="{{ route('products.index', ['sort' => 'newest']) }}" class="btn-ghost hidden sm:inline-flex">See all <x-icon name="arrow-right" class="size-4" /></a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-6 lg:grid-cols-4">
                @foreach ($newArrivals as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.shop>
