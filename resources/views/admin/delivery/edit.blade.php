<x-layouts.admin title="Delivery" subtitle="The options customers choose from at checkout. Saved changes go live straight away.">
    <x-slot:actions>
        <a href="{{ route('cart.index') }}" target="_blank" class="btn-outline"><x-icon name="eye" class="size-4" /> View checkout</a>
    </x-slot:actions>

    @php
        $field = fn (string $key) => old($key, $storefront->get($key));
    @endphp

    <form method="POST" action="{{ route('admin.delivery.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        @error('delivery_options') <p class="rounded-2xl bg-rose-50 p-4 text-sm font-semibold text-rose-700 ring-1 ring-rose-200">{{ $message }}</p> @enderror

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach (\App\Enums\DeliveryMethod::cases() as $method)
                @php($key = 'delivery_'.$method->value)
                <section class="card flex flex-col p-5 sm:p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-paper-deep text-ink"><x-icon :name="$method->icon()" /></span>
                        <p class="min-w-0 flex-1 text-xs font-bold tracking-wide text-ink-soft uppercase">{{ $method->requiresAddress() ? 'Delivered to the customer' : 'Customer collects' }}</p>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="checkbox" name="{{ $key }}_enabled" value="1" @checked(old($key.'_enabled', $errors->any() ? null : $storefront->get($key.'_enabled')) === '1') class="peer sr-only">
                            <span class="relative h-7 w-12 shrink-0 rounded-full bg-ink/15 transition peer-checked:bg-mint-500 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                            <span class="sr-only">Offer {{ $method->label() }}</span>
                        </label>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label for="{{ $key }}_label" class="label">Name</label>
                            <input id="{{ $key }}_label" name="{{ $key }}_label" value="{{ $field($key.'_label') }}" maxlength="40" required class="input bg-paper">
                            @error($key.'_label') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="{{ $key }}_description" class="label">Description</label>
                            <input id="{{ $key }}_description" name="{{ $key }}_description" value="{{ $field($key.'_description') }}" maxlength="120" class="input bg-paper">
                        </div>
                        <div>
                            <label for="{{ $key }}_fee" class="label">Fee (RM) <span class="font-normal text-ink-soft">· 0 for free</span></label>
                            <input id="{{ $key }}_fee" name="{{ $key }}_fee" type="number" step="0.01" min="0" value="{{ $field($key.'_fee') }}" required class="input bg-paper">
                            @error($key.'_fee') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                        </div>
                        @if ($method === \App\Enums\DeliveryMethod::Standard)
                            <div class="rounded-2xl bg-mint-50 p-3 ring-1 ring-mint-500/20">
                                <label for="delivery_free_over" class="label">Free when the order is over (RM)</label>
                                <input id="delivery_free_over" name="delivery_free_over" type="number" step="0.01" min="0" value="{{ $field('delivery_free_over') }}" class="input bg-surface">
                                <p class="mt-1 text-xs text-ink-soft">Leave at 0 to always charge. Shown in the top bar as “Free delivery over …”.</p>
                                @error('delivery_free_over') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        @if ($method === \App\Enums\DeliveryMethod::Express)
                            <div class="space-y-3 rounded-2xl bg-butter-50 p-3 ring-1 ring-butter-500/20">
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input type="checkbox" name="delivery_express_live" value="1" @checked(old('delivery_express_live', $errors->any() ? null : $storefront->get('delivery_express_live')) === '1') @disabled(! $lalamove->isConfigured()) class="mt-0.5 rounded border-ink/20 text-berry-600 disabled:opacity-40">
                                    <span>
                                        <span class="block text-sm font-bold">Live Lalamove price</span>
                                        <span class="block text-xs text-ink-soft">Charge the real Lalamove fare from your shop to the customer’s address. The fee above is then not used.</span>
                                    </span>
                                </label>

                                @if ($lalamove->isConfigured())
                                    <p class="flex items-center gap-1.5 text-xs font-semibold text-mint-700"><x-icon name="check" class="size-3.5" /> Connected to Lalamove {{ $lalamove->isSandbox() ? '(sandbox: test prices)' : '(live)' }}</p>
                                @else
                                    <p class="rounded-xl bg-surface p-2.5 text-xs text-ink-soft">To switch this on, add your Lalamove API key and secret to the <code class="font-bold">.env</code> file as <code class="font-bold">LALAMOVE_API_KEY</code> and <code class="font-bold">LALAMOVE_API_SECRET</code>. Get them from the Lalamove Partner Portal.</p>
                                @endif

                                <div>
                                    <label for="lalamove_service_type" class="label">Vehicle</label>
                                    <select id="lalamove_service_type" name="lalamove_service_type" class="input bg-surface">
                                        @foreach (\App\Services\Lalamove::SERVICE_TYPES as $type => $vehicle)
                                            <option value="{{ $type }}" @selected($field('lalamove_service_type') === $type)>{{ $vehicle }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="lalamove_pickup_address" class="label">Rider collects from <span class="font-normal text-ink-soft">· optional</span></label>
                                    <textarea id="lalamove_pickup_address" name="lalamove_pickup_address" rows="2" maxlength="300" placeholder="{{ $storefront->get('address') }}" class="input bg-surface">{{ $field('lalamove_pickup_address') }}</textarea>
                                    <p class="mt-1 text-xs text-ink-soft">Leave blank to use the shop address from the Storefront page.</p>
                                    @error('lalamove_pickup_address') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        <p class="text-sm text-ink-soft">Switched-off options are hidden at checkout. Past orders keep working, and counter sales are not affected.</p>

        <div class="flex justify-end">
            <button class="btn-primary px-8 py-3.5 text-base">Save delivery options</button>
        </div>
    </form>
</x-layouts.admin>
