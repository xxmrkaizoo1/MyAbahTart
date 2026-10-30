<x-layouts.shop title="Menu">
    <section class="container-page pt-4 sm:pt-6">
        @php
            $showcase = $products->take(3);
            $averageRating = $products->avg('reviews_avg_rating');
            $headerFreeOver = \App\Enums\DeliveryMethod::freeDeliveryThreshold();
        @endphp
        <div class="relative isolate overflow-hidden rounded-4xl bg-plum-900 text-white">
            <div class="pointer-events-none absolute -top-32 -left-20 -z-10 size-96 rounded-full bg-berry-500/35 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -right-24 -bottom-40 -z-10 size-[28rem] rounded-full bg-butter-400/25 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(rgb(255_255_255/0.07)_1px,transparent_1px)] [background-size:20px_20px] [mask-image:linear-gradient(to_right,black,transparent_75%)]" aria-hidden="true"></div>

            <div class="grid items-center gap-8 px-6 py-9 sm:px-10 sm:py-12 lg:grid-cols-[1fr_auto] lg:gap-12 lg:py-10">
                <div class="min-w-0">
                    <nav class="flex items-center gap-1.5 text-sm text-white/55" aria-label="Breadcrumb">
                        <a href="{{ route('home') }}" class="hover:text-white">Home</a>
                        <x-icon name="chevron-right" class="size-3.5" />
                        @if ($activeCategory)
                            <a href="{{ route('products.index') }}" class="hover:text-white">Menu</a>
                            <x-icon name="chevron-right" class="size-3.5" />
                            <span class="font-semibold text-white">{{ $activeCategory->name }}</span>
                        @else
                            <span class="font-semibold text-white">Menu</span>
                        @endif
                    </nav>

                    <p class="mt-6 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold tracking-[0.14em] text-butter-300 uppercase ring-1 ring-white/15 backdrop-blur">
                        <x-icon name="sparkles" class="size-3.5" /> {{ $search !== '' ? 'Search results' : 'Prepared fresh daily' }}
                    </p>
                    <h1 class="mt-4 font-display text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-6xl">
                        @if ($search !== '')
                            “<span class="text-butter-300">{{ Str::limit($search, 30) }}</span>”
                        @elseif ($activeCategory)
                            {{ $activeCategory->name }}
                        @else
                            The full <span class="relative whitespace-nowrap text-butter-300">menu<svg class="absolute -bottom-2 left-0 h-3 w-full text-berry-400" viewBox="0 0 100 12" preserveAspectRatio="none" aria-hidden="true"><path d="M2 9c20-6 50-8 96-3" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg></span>
                        @endif
                    </h1>
                    <p class="mt-4 max-w-md text-base text-white/70">
                        @if ($search !== '')
                            {{ $products->count() }} {{ Str::plural('product', $products->count()) }} found{{ $activeCategory ? ' in '.$activeCategory->name : '' }}.
                        @else
                            Pick your favourites. Every order is prepared and packed fresh, then delivered or ready for pickup.
                        @endif
                    </p>

                    <ul class="mt-6 flex flex-wrap gap-2 text-sm font-semibold">
                        <li class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-2 ring-1 ring-white/10">
                            <x-icon name="grid" class="size-4 text-butter-300" /> {{ $products->count() }} {{ Str::plural('product', $products->count()) }}
                        </li>
                        @if ($averageRating)
                            <li class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-2 ring-1 ring-white/10">
                                <x-icon name="star" class="size-4 text-butter-300" /> {{ number_format($averageRating, 1) }} average rating
                            </li>
                        @endif
                        @if ($headerFreeOver)
                            <li class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-2 ring-1 ring-white/10">
                                <x-icon name="truck" class="size-4 text-butter-300" /> Free delivery over @rm($headerFreeOver)
                            </li>
                        @endif
                    </ul>
                </div>

                @if ($showcase->isNotEmpty())
                    <div class="relative hidden h-64 w-80 lg:block" aria-hidden="true">
                        @foreach ($showcase as $product)
                            <div @class([
                                'absolute overflow-hidden rounded-3xl shadow-2xl shadow-black/40 ring-4',
                                'top-8 left-0 size-40 -rotate-8 ring-white/10' => $loop->index === 0 && $showcase->count() > 1,
                                'top-0 right-0 size-40 rotate-6 ring-white/10' => $loop->index === 1,
                                'top-12 left-20 z-10 size-44 ring-white/25' => $loop->index === 2 || $showcase->count() === 1,
                            ])>
                                <x-product-image :product="$product" class="size-full" />
                            </div>
                        @endforeach
                        <span class="absolute right-2 bottom-0 z-20 inline-flex items-center gap-1.5 rounded-full bg-butter-300 px-3 py-1.5 text-xs font-extrabold text-plum-900 shadow-lg">
                            <x-icon name="heart" class="size-3.5" /> Customer favourites
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="container-page grid grid-cols-1 gap-8 py-6 sm:py-10 lg:grid-cols-[240px_1fr] lg:gap-12">
        {{-- Filters --}}
        <aside class="lg:sticky lg:top-28 lg:self-start">
            <form action="{{ route('products.index') }}" class="relative">
                @if ($activeCategory)
                    <input type="hidden" name="category" value="{{ $activeCategory->slug }}">
                @endif
                <input type="hidden" name="sort" value="{{ $sort }}">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-ink-soft" />
                <input type="search" name="search" value="{{ $search }}" placeholder="Search products" class="input rounded-full pl-10" aria-label="Search products">
            </form>

            <p class="eyebrow mt-8 hidden lg:flex">Collections</p>
            <nav class="scrollbar-none -mx-4 mt-4 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:mt-3 lg:flex-col lg:gap-1 lg:px-0" aria-label="Collections">
                <a href="{{ route('products.index', array_filter(['search' => $search, 'sort' => $sort])) }}" @class([
                    'flex shrink-0 items-center justify-between gap-3 rounded-full px-4 py-2 text-sm font-semibold ring-1 transition lg:rounded-2xl lg:ring-0',
                    'bg-plum-900 text-white ring-plum-900' => ! $activeCategory,
                    'bg-surface text-ink-soft ring-ink/10 hover:text-ink lg:bg-transparent lg:hover:bg-surface' => $activeCategory,
                ])>All <span class="hidden opacity-60 lg:inline">{{ $categories->sum('products_count') }}</span></a>
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', array_filter(['category' => $category->slug, 'search' => $search, 'sort' => $sort])) }}" @class([
                        'flex shrink-0 items-center justify-between gap-3 rounded-full px-4 py-2 text-sm font-semibold ring-1 transition lg:rounded-2xl lg:ring-0',
                        'bg-plum-900 text-white ring-plum-900' => $activeCategory?->is($category),
                        'bg-surface text-ink-soft ring-ink/10 hover:text-ink lg:bg-transparent lg:hover:bg-surface' => ! $activeCategory?->is($category),
                    ])>{{ $category->name }} <span class="hidden opacity-60 lg:inline">{{ $category->products_count }}</span></a>
                @endforeach
            </nav>

            @if ($freeOver = \App\Enums\DeliveryMethod::freeDeliveryThreshold())
                <div class="mt-8 hidden rounded-3xl bg-plum-900 p-5 text-white lg:block">
                    <x-icon name="truck" class="size-6 text-butter-300" />
                    <p class="mt-3 font-display text-lg font-bold">Free delivery</p>
                    <p class="mt-1 text-sm text-white/70">On {{ Str::lower(\App\Enums\DeliveryMethod::Standard->label()) }} orders over @rm($freeOver).</p>
                </div>
            @endif
        </aside>

        <div>
            <div class="mb-6 flex items-center justify-between gap-3">
                <p class="hidden text-sm text-ink-soft sm:block">Showing {{ $products->count() }} results</p>
                <form action="{{ route('products.index') }}" class="ml-auto flex items-center gap-2">
                    @foreach (array_filter(['category' => $activeCategory?->slug, 'search' => $search]) as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <label for="sort" class="text-sm font-semibold text-ink-soft">Sort</label>
                    <select id="sort" name="sort" data-autosubmit class="input w-auto rounded-full py-2 pr-9">
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @if ($products->isEmpty())
                <div class="card flex flex-col items-center px-6 py-16 text-center">
                    <span class="grid size-16 place-items-center rounded-full bg-berry-50 text-berry-600"><x-icon name="search" class="size-7" /></span>
                    <h2 class="mt-4 text-2xl font-bold">No products found</h2>
                    <p class="mt-1 max-w-sm text-sm text-ink-soft">Try a different search or browse all collections.</p>
                    <a href="{{ route('products.index') }}" class="btn-outline mt-6">Clear filters</a>
                </div>
            @else
                <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 md:grid-cols-3">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.shop>
