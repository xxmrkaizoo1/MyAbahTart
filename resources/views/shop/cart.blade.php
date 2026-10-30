<x-layouts.shop title="Your cart" :cart-pill="false">
    <div class="container-page py-6 sm:py-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Checkout</p>
                <h1 class="mt-1 text-4xl font-extrabold sm:text-5xl">Your cart</h1>
            </div>
            <ol class="flex items-center gap-2 text-xs font-bold sm:text-sm" aria-label="Checkout steps">
                <li class="flex items-center gap-2"><span class="grid size-7 place-items-center rounded-full bg-plum-900 text-white">1</span> Cart</li>
                <li class="h-px w-6 bg-ink/20 sm:w-10"></li>
                <li class="flex items-center gap-2 text-ink-soft"><span class="grid size-7 place-items-center rounded-full bg-surface ring-1 ring-ink/15">2</span> Details</li>
                <li class="h-px w-6 bg-ink/20 sm:w-10"></li>
                <li class="flex items-center gap-2 text-ink-soft"><span class="grid size-7 place-items-center rounded-full bg-surface ring-1 ring-ink/15">3</span> Done</li>
            </ol>
        </div>

        @if ($lines->isEmpty())
            <div class="mt-8 flex flex-col items-center rounded-4xl bg-surface px-6 py-16 text-center shadow-soft ring-1 ring-ink/5">
                <span class="grid size-20 place-items-center rounded-full bg-butter-200 text-plum-900"><x-icon name="bag" class="size-9" /></span>
                <h2 class="mt-5 text-3xl font-extrabold">Your cart is empty</h2>
                <p class="mt-2 max-w-sm text-ink-soft">Everything on the menu is made fresh today. Go on, treat yourself.</p>
                <a href="{{ route('products.index') }}" class="btn-primary mt-6 px-7 py-3.5">Browse the menu <x-icon name="arrow-right" class="size-4.5" /></a>
            </div>

            @if ($suggestions->isNotEmpty())
                <section class="mt-14">
                    <h2 class="section-title">Popular right now</h2>
                    <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-6 lg:grid-cols-4">
                        @foreach ($suggestions as $product)
                            <x-product-card :product="$product" />
                        @endforeach
                    </div>
                </section>
            @endif
        @else
            <div data-checkout data-subtotal="{{ $summary['subtotal'] }}" data-discount="{{ $summary['discount'] }}"
                class="mt-8 grid gap-6 lg:grid-cols-[1fr_400px] lg:items-start lg:gap-10">
                <div class="space-y-8">
                    {{-- Items --}}
                    <section>
                        <h2 class="font-display text-xl font-bold">{{ $lines->sum('quantity') }} {{ Str::plural('item', $lines->sum('quantity')) }}</h2>
                        <ul class="mt-4 space-y-3">
                            @foreach ($lines as $line)
                                <li class="flex gap-3 rounded-3xl bg-surface p-3 ring-1 ring-ink/5 sm:gap-5 sm:p-4">
                                    <a href="{{ route('products.show', $line['product']) }}" class="shrink-0">
                                        <x-product-image :product="$line['product']" :size="$line['size']" class="size-24 rounded-2xl sm:size-28" />
                                    </a>
                                    <div class="flex min-w-0 flex-1 flex-col">
                                        <div class="flex items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-semibold text-ink-soft">{{ $line['product']->category->name }}</p>
                                                <a href="{{ route('products.show', $line['product']) }}" class="block font-display text-lg leading-tight font-bold hover:text-berry-700">{{ $line['product']->name }}</a>
                                                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-soft">
                                                    @if ($line['size'])
                                                        <span class="chip bg-plum-900 text-white">Size {{ $line['size']->value }} · {{ $line['product']->sizeLabel($line['size']) }}</span>
                                                    @endif
                                                    <span>@rm($line['unit_price']) · {{ $line['product']->unitLabel() }}</span>
                                                </p>
                                            </div>
                                            <form method="POST" action="{{ route('cart.destroy', $line['product']) }}">
                                                @csrf
                                                @method('DELETE')
                                                @if ($line['size'])
                                                    <input type="hidden" name="size" value="{{ $line['size']->value }}">
                                                @endif
                                                <button class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-rose-50 hover:text-rose-700" aria-label="Remove {{ $line['product']->name }}"><x-icon name="trash" class="size-4.5" /></button>
                                            </form>
                                        </div>
                                        <div class="mt-auto flex items-end justify-between gap-2 pt-3">
                                            <form method="POST" action="{{ route('cart.update', $line['product']) }}" data-stepper class="flex items-center rounded-full bg-paper ring-1 ring-ink/10">
                                                @csrf
                                                @method('PATCH')
                                                @if ($line['size'])
                                                    <input type="hidden" name="size" value="{{ $line['size']->value }}">
                                                @endif
                                                <button type="button" data-step="-1" class="grid size-9 place-items-center rounded-full text-ink-soft hover:text-ink" aria-label="Decrease quantity"><x-icon name="minus" class="size-3.5" /></button>
                                                <input type="number" name="quantity" value="{{ $line['quantity'] }}" min="0" max="{{ $line['product']->stockFor($line['size']) }}" data-autosubmit class="w-8 border-0 bg-transparent p-0 text-center text-sm font-bold focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none" aria-label="Quantity">
                                                <button type="button" data-step="1" class="grid size-9 place-items-center rounded-full text-ink-soft hover:text-ink" aria-label="Increase quantity"><x-icon name="plus" class="size-3.5" /></button>
                                            </form>
                                            <p class="font-display text-lg font-bold">@rm($line['line_total'])</p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @error('cart') <p class="mt-3 rounded-2xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $message }}</p> @enderror
                    </section>

                    {{-- Smart nudge --}}
                    @if ($summary['free_delivery_gap'] > 0 && $summary['free_delivery_gap'] <= 80)
                        @php($progress = min(100, $summary['subtotal'] / \App\Enums\DeliveryMethod::freeDeliveryThreshold() * 100))
                        <x-insight title="You’re close to free delivery" tone="butter">
                            Add <span class="font-bold text-ink">@rm($summary['free_delivery_gap'])</span> more to unlock free standard delivery.
                            @if ($suggestions->isNotEmpty())
                                Customers often add <a href="{{ route('products.show', $suggestions->first()) }}" class="font-bold text-berry-700 hover:underline">{{ $suggestions->first()->name }}</a>.
                            @endif
                            <span class="mt-3 block h-2 rounded-full bg-surface"><span class="block h-full rounded-full bg-butter-500" style="width: {{ $progress }}%"></span></span>
                        </x-insight>
                    @elseif ($suggestions->isNotEmpty())
                        <x-insight title="Goes well with your order">
                            People who ordered these also loved
                            <a href="{{ route('products.show', $suggestions->first()) }}" class="font-bold text-mint-700 hover:underline">{{ $suggestions->first()->name }}</a>.
                        </x-insight>
                    @endif

                    {{-- Delivery --}}
                    <section>
                        <h2 class="font-display text-xl font-bold">How would you like it?</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            @foreach ($deliveryMethods as $method)
                                @php($fee = $method->feeFor($summary['subtotal']))
                                @php($isLive = $liveQuoteMethod === $method)
                                <label class="relative flex cursor-pointer gap-3 rounded-3xl bg-surface p-4 ring-1 ring-ink/10 transition has-[:checked]:bg-berry-50 has-[:checked]:ring-2 has-[:checked]:ring-berry-600 sm:flex-col">
                                    <input type="radio" name="delivery_method" value="{{ $method->value }}" form="checkout-form" class="peer sr-only"
                                        data-fee="{{ $isLive ? '' : $fee }}" @if ($isLive) data-live-quote="{{ route('delivery.quote') }}" @endif data-needs-address="{{ $method->requiresAddress() ? 1 : 0 }}" @checked($deliveryMethod === $method)>
                                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-paper-deep text-ink peer-checked:bg-berry-600 peer-checked:text-white"><x-icon :name="$method->icon()" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-bold">{{ $method->label() }}</span>
                                        <span class="block text-xs text-ink-soft">{{ $method->description() }}</span>
                                    </span>
                                    @if ($isLive)
                                        <span class="text-sm font-extrabold" data-quote-price>
                                            <span class="block">By distance</span>
                                            <span class="block text-[11px] font-semibold text-ink-soft" data-quote-note>Live price from Lalamove</span>
                                        </span>
                                    @else
                                        <span @class(['text-sm font-extrabold', 'text-mint-600' => $fee == 0])>{{ $fee == 0 ? 'FREE' : 'RM '.number_format($fee, 2) }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- Payment --}}
                    @php($paymentMethods = \App\Enums\PaymentMethod::forCheckout())
                    @php($chosenPayment = \App\Enums\PaymentMethod::tryFrom((string) old('payment_method')) ?? $paymentMethods[0])
                    <section>
                        <h2 class="font-display text-xl font-bold">How would you like to pay?</h2>
                        @php($onlineComingSoon = \App\Enums\PaymentMethod::onlineComingSoon())
                        <div @class(['mt-4 grid gap-3', 'sm:grid-cols-2' => count($paymentMethods) > 1 || $onlineComingSoon])>
                            @if ($onlineComingSoon)
                                <div class="relative flex items-center gap-3 rounded-3xl bg-surface/60 p-4 opacity-60 ring-1 ring-ink/10" aria-disabled="true">
                                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-paper-deep text-ink"><x-icon name="lock" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-bold">{{ \App\Enums\PaymentMethod::Online->checkoutLabel() }}</span>
                                        <span class="block text-xs text-ink-soft">{{ \App\Enums\PaymentMethod::Online->checkoutDescription() }}</span>
                                    </span>
                                    <span class="shrink-0 rounded-full bg-paper-deep px-2.5 py-1 text-[11px] font-bold text-ink-soft">Coming soon</span>
                                </div>
                            @endif
                            @foreach ($paymentMethods as $method)
                                <label class="relative flex cursor-pointer items-center gap-3 rounded-3xl bg-surface p-4 ring-1 ring-ink/10 transition has-[:checked]:bg-berry-50 has-[:checked]:ring-2 has-[:checked]:ring-berry-600">
                                    <input type="radio" name="payment_method" value="{{ $method->value }}" form="checkout-form" class="peer sr-only" data-payment-option
                                        data-button="{{ $method === \App\Enums\PaymentMethod::Online ? 'Continue to payment' : 'Place order' }}"
                                        data-note="{{ $method === \App\Enums\PaymentMethod::Online ? 'Secure payment by Billplz · FPX online banking, cards & e-wallets' : 'Pay in cash on delivery or at pickup' }}"
                                        @checked($chosenPayment === $method)>
                                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-paper-deep text-ink peer-checked:bg-berry-600 peer-checked:text-white"><x-icon :name="$method === \App\Enums\PaymentMethod::Online ? 'lock' : 'ticket'" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-bold">{{ $method->checkoutLabel() }}</span>
                                        <span class="block text-xs text-ink-soft">{{ $method->checkoutDescription() }}</span>
                                    </span>
                                    @if ($method === \App\Enums\PaymentMethod::Online)
                                        <span class="hidden shrink-0 gap-1 text-[10px] font-extrabold tracking-wide text-ink-soft sm:flex">
                                            <span class="rounded-md bg-paper-deep px-1.5 py-1">FPX</span>
                                            <span class="rounded-md bg-paper-deep px-1.5 py-1">CARD</span>
                                        </span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        @error('payment_method') <p class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </section>

                    {{-- Contact --}}
                    <section>
                        <h2 class="font-display text-xl font-bold">Your details</h2>
                        <div class="mt-4 grid gap-4 rounded-3xl bg-surface p-4 ring-1 ring-ink/5 sm:grid-cols-2 sm:p-6">
                            <div>
                                <label for="phone" class="label">Mobile number</label>
                                <input id="phone" name="phone" form="checkout-form" type="tel" required value="{{ old('phone', $user?->phone) }}" placeholder="012-345 6789" class="input bg-paper">
                                @error('phone') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="notes" class="label">Note for our team <span class="font-normal text-ink-soft">(optional)</span></label>
                                <input id="notes" name="notes" form="checkout-form" type="text" value="{{ old('notes') }}" placeholder="e.g. Write “Happy Birthday” on the box" class="input bg-paper">
                            </div>
                            <div data-address-field class="sm:col-span-2">
                                <label for="address" class="label">Delivery address</label>
                                <textarea id="address" name="address" form="checkout-form" rows="2" placeholder="Unit, street, postcode, city" class="input bg-paper">{{ old('address', $user?->address) }}</textarea>
                                @error('address') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                                <p data-quote-status class="mt-1 hidden text-sm"></p>
                            </div>
                        </div>
                    </section>
                </div>

                {{-- Summary --}}
                <aside class="rounded-4xl bg-plum-900 p-5 text-white shadow-pop sm:p-7 lg:sticky lg:top-28">
                    <h2 class="font-display text-2xl font-bold">Order summary</h2>

                    <div class="mt-5">
                        @if ($summary['voucher'])
                            <div class="flex items-center gap-3 rounded-2xl bg-white/10 p-3">
                                <x-icon name="ticket" class="text-butter-300" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold">{{ $summary['voucher']->code }}</p>
                                    <p class="truncate text-xs text-white/60">{{ $summary['discount'] > 0 ? $summary['voucher']->description : 'Minimum spend not reached yet' }}</p>
                                </div>
                                <form method="POST" action="{{ route('cart.voucher.destroy') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="grid size-8 place-items-center rounded-full hover:bg-white/10" aria-label="Remove voucher"><x-icon name="x" class="size-4" /></button>
                                </form>
                            </div>
                        @else
                            <form method="POST" action="{{ route('cart.voucher.store') }}" class="flex gap-2">
                                @csrf
                                <input name="code" value="{{ old('code') }}" placeholder="Voucher code" class="input rounded-full bg-white/10 text-white uppercase ring-white/15 placeholder:text-white/40 placeholder:normal-case focus:bg-white/15" aria-label="Voucher code">
                                <button class="btn-butter shrink-0">Apply</button>
                            </form>
                            @error('code') <p class="mt-2 text-sm text-rose-200">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" class="mt-6">
                        @csrf
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between"><dt class="text-white/60">Subtotal</dt><dd class="font-semibold">@rm($summary['subtotal'])</dd></div>
                            <div class="flex justify-between"><dt class="text-white/60">Delivery</dt><dd class="font-semibold" data-summary="fee">{{ $summary['delivery_fee'] == 0 ? 'FREE' : 'RM '.number_format($summary['delivery_fee'], 2) }}</dd></div>
                            @if ($summary['discount'] > 0)
                                <div class="flex justify-between text-butter-300"><dt>Discount ({{ $summary['voucher']->code }})</dt><dd class="font-semibold">−@rm($summary['discount'])</dd></div>
                            @endif
                            <div class="flex items-end justify-between border-t border-white/15 pt-4">
                                <dt class="font-semibold">Total</dt>
                                <dd class="font-display text-3xl font-extrabold" data-summary="total">@rm($summary['total'])</dd>
                            </div>
                        </dl>

                        @auth
                            <button type="submit" class="btn-butter mt-6 w-full py-4 text-base"><span data-payment-button>{{ $chosenPayment === \App\Enums\PaymentMethod::Online ? 'Continue to payment' : 'Place order' }}</span> <x-icon name="arrow-right" class="size-4.5" /></button>
                        @else
                            <a href="{{ route('login', ['return' => 'cart']) }}" class="btn-butter mt-6 w-full py-4 text-base">Sign in to check out</a>
                        @endauth
                        <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-white/50"><x-icon name="lock" class="size-3.5" /> <span data-payment-note>{{ $chosenPayment === \App\Enums\PaymentMethod::Online ? 'Secure payment by Billplz · FPX online banking, cards & e-wallets' : 'Pay in cash on delivery or at pickup' }}</span></p>
                    </form>
                </aside>
            </div>
        @endif
    </div>
</x-layouts.shop>
