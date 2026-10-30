<x-layouts.shop title="Menu">
    <section class="container-page pt-4 sm:pt-6">
        <div class="relative overflow-hidden rounded-4xl bg-plum-900 px-6 py-10 text-white sm:px-10 sm:py-14">
            <div class="pointer-events-none absolute -top-24 -right-16 size-80 rounded-full bg-berry-500/50 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 left-1/3 size-80 rounded-full bg-butter-400/30 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(255_255_255/0.08)_1px,transparent_1px)] [background-size:18px_18px]" aria-hidden="true"></div>
            <nav class="relative text-sm text-white/60" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-white">Home</a> <span class="mx-1.5">/</span> <span class="font-semibold text-white">Menu</span>
            </nav>
            <h1 class="relative mt-3 text-4xl font-extrabold sm:text-6xl">{{ $activeCategory?->name ?? 'The full menu' }}</h1>
            <p class="relative mt-3 text-white/70">
                {{ $products->count() }} {{ Str::plural('product', $products->count()) }}
                @if ($search !== '')
                    matching “<span class="font-semibold text-white">{{ $search }}</span>”
                @else
                    prepared fresh for you
                @endif
            </p>
        </div>
    </section>

    <div class="container-page grid gap-8 py-6 sm:py-10 lg:grid-cols-[240px_1fr] lg:gap-12">
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
