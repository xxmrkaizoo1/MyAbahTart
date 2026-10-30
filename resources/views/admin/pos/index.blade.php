<x-layouts.admin title="Counter" subtitle="Ring up walk-in customers. Stock is shared with the online shop.">
    <x-slot:actions>
        <span class="rounded-full bg-surface px-4 py-2 text-sm ring-1 ring-ink/10">
            Today: <span class="font-bold">{{ $salesCount }} {{ Str::plural('sale', $salesCount) }}</span> · <span class="font-bold">@rm($salesTotal)</span>
        </span>
        <a href="{{ route('admin.sales.index') }}" class="btn-ghost"><x-icon name="chart" class="size-4" /> Online vs counter</a>
        <a href="{{ route('admin.pos.menu') }}" class="btn-outline"><x-icon name="sliders" class="size-4" /> Today’s items</a>
    </x-slot:actions>

    @if (! $hasTodayMenu)
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-3xl bg-butter-100 p-4 text-sm ring-1 ring-butter-300/40">
            <x-icon name="alert" class="size-5 shrink-0 text-butter-600" />
            <p class="flex-1">You haven’t picked today’s counter items yet, so every product is shown.</p>
            <a href="{{ route('admin.pos.menu') }}" class="btn-dark py-2">Pick today’s items</a>
        </div>
    @elseif ($showAll)
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-3xl bg-paper-deep p-4 text-sm">
            <p class="flex-1">Showing <span class="font-bold">all products</span>, not just today’s counter items.</p>
            <a href="{{ route('admin.pos.index') }}" class="btn-outline py-2">Show today’s items only</a>
        </div>
    @endif

    <div data-pos class="grid gap-4 lg:grid-cols-[1fr_380px] lg:items-start">
        {{-- Products --}}
        <section>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-48 flex-1">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-ink-soft" />
                    <input type="search" data-pos-search placeholder="Search items" class="input rounded-full pl-10" aria-label="Search items">
                </div>
                @if ($hasTodayMenu && ! $showAll)
                    <a href="{{ route('admin.pos.index', ['all' => 1]) }}" class="btn-ghost">Show all products</a>
                @endif
            </div>

            @php($categories = $products->pluck('category.name')->unique()->sort()->values())
            @if ($categories->count() > 1)
                <div class="scrollbar-none -mx-4 mt-3 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0" role="group" aria-label="Collections">
                    <button type="button" data-pos-category="" aria-pressed="true" class="shrink-0 rounded-full bg-surface px-4 py-2 text-sm font-semibold ring-1 ring-ink/10 aria-pressed:bg-plum-900 aria-pressed:text-white aria-pressed:ring-plum-900">All</button>
                    @foreach ($categories as $categoryName)
                        <button type="button" data-pos-category="{{ $categoryName }}" aria-pressed="false" class="shrink-0 rounded-full bg-surface px-4 py-2 text-sm font-semibold ring-1 ring-ink/10 aria-pressed:bg-plum-900 aria-pressed:text-white aria-pressed:ring-plum-900">{{ $categoryName }}</button>
                    @endforeach
                </div>
            @endif

            @if ($products->isEmpty())
                <div class="card mt-4 flex flex-col items-center px-6 py-16 text-center">
                    <span class="grid size-16 place-items-center rounded-full bg-butter-200 text-plum-900"><x-icon name="box" class="size-7" /></span>
                    <p class="mt-4 font-display text-xl font-bold">Nothing to sell yet</p>
                    <p class="mt-1 max-w-sm text-sm text-ink-soft">Add products in the admin portal, then pick today’s counter items.</p>
                </div>
            @else
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach ($products as $product)
                        @php($sellable = \App\Models\CounterItem::sellableToday($product, $broughtOut, $soldToday))
                        @php($brought = $broughtOut[$product->id] ?? null)
                        @php($unavailable = $sellable < 1)
                        <article data-pos-product data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-category="{{ $product->category->name }}" data-stock="{{ $sellable }}"
                            @class(['flex flex-col overflow-hidden rounded-3xl bg-surface ring-1 ring-ink/10', 'opacity-50' => $unavailable])>
                            @if ($product->hasSizes())
                                <x-product-image :product="$product" class="aspect-[4/3]" />
                            @else
                                <button type="button" data-pos-add data-size="" data-price="{{ number_format((float) $product->price, 2, '.', '') }}" @disabled($unavailable)
                                    class="block transition active:scale-[0.97] disabled:cursor-not-allowed" aria-label="Add {{ $product->name }}">
                                    <x-product-image :product="$product" class="aspect-[4/3]" />
                                </button>
                            @endif
                            <div class="flex flex-1 flex-col p-3">
                                <p class="font-bold leading-tight">{{ $product->name }}</p>
                                @if ($brought !== null)
                                    <div class="mt-1.5">
                                        <div class="flex justify-between text-xs font-semibold">
                                            <span @class(['text-rose-700' => $sellable <= max(2, (int) ceil($brought * 0.2)), 'text-ink-soft' => $sellable > max(2, (int) ceil($brought * 0.2))])>{{ $unavailable ? 'Sold out at counter' : $sellable.' of '.$brought.' left' }}</span>
                                            <span class="text-ink-soft">{{ $soldToday[$product->id] ?? 0 }} sold</span>
                                        </div>
                                        <div class="mt-1 h-1.5 rounded-full bg-ink/5"><div class="h-full rounded-full bg-berry-500" style="width: {{ $brought > 0 ? min(100, round($sellable / $brought * 100)) : 0 }}%"></div></div>
                                    </div>
                                @else
                                    <p @class(['mt-0.5 text-xs font-semibold', 'text-rose-700' => $product->isLowStock(), 'text-ink-soft' => ! $product->isLowStock()])>
                                        {{ $unavailable ? 'Sold out' : $sellable.' in stock' }}
                                    </p>
                                @endif
                                <div class="mt-auto pt-3">
                                    @if ($product->hasSizes())
                                        @php($sizeCount = count($product->sizePrices()))
                                        <div @class(['grid gap-1.5', 'grid-cols-1' => $sizeCount === 1, 'grid-cols-2' => $sizeCount === 2, 'grid-cols-3' => $sizeCount === 3])>
                                            @foreach ($product->sizePrices() as $code => $price)
                                                @php($sizeLeft = \App\Models\CounterItem::sellableTodayInSize($product, \App\Enums\ProductSize::from($code), $sellable, $sizeBroughtOut, $soldBySize))
                                                @php($sizeBrought = $sizeBroughtOut[$product->id][$code] ?? null)
                                                <button type="button" data-pos-add data-size="{{ $code }}" data-size-stock="{{ $sizeLeft }}" data-price="{{ number_format($price, 2, '.', '') }}" @disabled($unavailable || $sizeLeft === 0)
                                                    class="flex flex-col items-center rounded-2xl bg-paper py-2 text-xs ring-1 ring-ink/10 transition hover:bg-berry-600 hover:text-white active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                                                    aria-label="Add {{ $product->name }}, size {{ $code }}">
                                                    <span class="font-display text-base font-extrabold">{{ $code }}</span>
                                                    <span class="font-semibold">{{ number_format($price, 2) }}</span>
                                                    <span class="text-[10px] opacity-70">{{ $sizeLeft === 0 ? 'Sold out' : ($sizeBrought === null ? $sizeLeft.' left' : $sizeLeft.' of '.$sizeBrought) }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @else
                                        <button type="button" data-pos-add data-size="" data-price="{{ number_format((float) $product->price, 2, '.', '') }}" @disabled($unavailable)
                                            class="btn-primary w-full py-2" aria-label="Add {{ $product->name }}">
                                            <x-icon name="plus" class="size-4" /> @rm($product->price)
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <p data-pos-no-results class="card mt-4 hidden p-10 text-center text-sm text-ink-soft">No items match your search.</p>
            @endif
        </section>

        {{-- Current sale --}}
        <form method="POST" action="{{ route('admin.pos.store') }}" data-pos-form class="card overflow-hidden lg:sticky lg:top-4">
            @csrf
            <div class="flex items-center justify-between bg-plum-900 px-5 py-4 text-white">
                <h2 class="font-display text-xl font-bold">Current sale</h2>
                <button type="button" data-pos-clear class="rounded-full px-3 py-1 text-sm font-semibold text-white/70 hover:bg-white/10 hover:text-white">Clear</button>
            </div>

            <ul data-pos-lines class="max-h-[40vh] divide-y divide-ink/5 overflow-y-auto">
                <li data-pos-empty class="flex flex-col items-center px-5 py-10 text-center text-sm text-ink-soft">
                    <x-icon name="cart" class="size-8 opacity-40" />
                    <span class="mt-2">Tap an item to add it to the sale.</span>
                </li>
            </ul>
            <div data-pos-inputs hidden></div>

            <div class="space-y-4 border-t border-ink/5 bg-paper p-5">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="customer_name" class="label">Customer <span class="font-normal text-ink-soft">(optional)</span></label>
                        <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" maxlength="100" placeholder="Walk-in" class="input bg-surface py-2.5">
                    </div>
                    <div>
                        <label for="discount" class="label">Discount (RM)</label>
                        <input id="discount" name="discount" type="number" step="0.01" min="0" value="{{ old('discount') }}" placeholder="0.00" data-pos-discount class="input bg-surface py-2.5">
                    </div>
                </div>

                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-soft">Subtotal</dt><dd data-pos-subtotal>RM 0.00</dd></div>
                    <div class="flex justify-between text-mint-700"><dt>Discount</dt><dd data-pos-discount-total>−RM 0.00</dd></div>
                    <div class="flex items-end justify-between pt-1"><dt class="font-bold">Total</dt><dd data-pos-total class="font-display text-3xl font-extrabold text-berry-700">RM 0.00</dd></div>
                </dl>

                <fieldset>
                    <legend class="label">Payment</legend>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (\App\Enums\PaymentMethod::forCounter() as $method)
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="{{ $method->value }}" data-pos-method class="peer sr-only" @checked(old('payment_method', 'cash') === $method->value)>
                                <span class="flex flex-col items-center gap-1 rounded-2xl bg-surface px-2 py-2.5 text-center text-xs font-bold ring-1 ring-ink/10 transition peer-checked:bg-plum-900 peer-checked:text-white peer-checked:ring-plum-900 peer-focus-visible:ring-2 peer-focus-visible:ring-berry-500">
                                    <x-icon :name="$method->icon()" class="size-5" /> {{ $method->label() }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div data-pos-cash class="space-y-2">
                    <label for="amount_tendered" class="label">Cash received (RM)</label>
                    <input id="amount_tendered" name="amount_tendered" type="number" step="0.01" min="0" value="{{ old('amount_tendered') }}" data-pos-tendered class="input bg-surface text-lg font-bold" inputmode="decimal">
                    <div class="grid grid-cols-5 gap-1.5">
                        <button type="button" data-pos-quick="exact" class="rounded-xl bg-surface py-2 text-xs font-bold ring-1 ring-ink/10 hover:bg-paper-deep">Exact</button>
                        @foreach ([10, 20, 50, 100] as $note)
                            <button type="button" data-pos-quick="{{ $note }}" class="rounded-xl bg-surface py-2 text-xs font-bold ring-1 ring-ink/10 hover:bg-paper-deep">RM{{ $note }}</button>
                        @endforeach
                    </div>
                    <p class="flex items-center justify-between rounded-2xl bg-mint-50 px-4 py-2.5 text-sm text-mint-700">
                        <span class="font-semibold">Change</span><span data-pos-change class="font-display text-xl font-extrabold">RM 0.00</span>
                    </p>
                </div>

                @if ($errors->any())
                    <div class="rounded-2xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">
                        @foreach ($errors->all() as $message)
                            <p>{{ $message }}</p>
                        @endforeach
                    </div>
                @endif

                <button data-pos-charge disabled class="btn-primary w-full py-4 text-lg">Charge <span data-pos-charge-total>RM 0.00</span></button>
            </div>
        </form>
    </div>

    @if ($recentSales->isNotEmpty())
        <section class="mt-8">
            <h2 class="section-title">Today’s counter sales</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($recentSales as $sale)
                    <li>
                        <a href="{{ route('admin.pos.receipt', $sale) }}" class="card flex items-center gap-3 p-4 transition hover:-translate-y-0.5">
                            <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-paper-deep"><x-icon :name="$sale->payment_method?->icon() ?? 'receipt'" class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold">{{ $sale->code }} · {{ $sale->customerName() }}</span>
                                <span class="block truncate text-xs text-ink-soft">{{ $sale->created_at->format('g:i a') }} · {{ $sale->items->sum('quantity') }} items · {{ $sale->payment_method?->label() }}</span>
                            </span>
                            <span class="font-display font-bold">@rm($sale->total)</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.admin>
