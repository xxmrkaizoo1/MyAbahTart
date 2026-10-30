<x-layouts.admin title="Storefront" subtitle="Change the wording and pictures customers see on the shop. Saved changes go live straight away.">
    <x-slot:actions>
        <a href="{{ route('home') }}" target="_blank" class="btn-outline"><x-icon name="eye" class="size-4" /> View shop</a>
    </x-slot:actions>

    @php
        $field = fn (string $key) => old($key, $storefront->get($key));
    @endphp

    <form method="POST" action="{{ route('admin.storefront.update') }}" enctype="multipart/form-data" class="grid gap-4 lg:grid-cols-[1fr_360px] lg:items-start">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <section class="card p-5 sm:p-7">
                <h2 class="font-display text-xl font-bold">Home page heading</h2>
                <p class="text-sm text-ink-soft">The first thing customers read on the home page.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <label for="hero_badge" class="label">Small badge <span class="font-normal text-ink-soft">· leave blank to hide</span></label>
                        <input id="hero_badge" name="hero_badge" value="{{ $field('hero_badge') }}" maxlength="60" class="input bg-paper">
                    </div>
                    <div>
                        <label for="hero_title_start" class="label">Heading start</label>
                        <input id="hero_title_start" name="hero_title_start" value="{{ $field('hero_title_start') }}" maxlength="40" class="input bg-paper">
                    </div>
                    <div>
                        <label for="hero_title_highlight" class="label">Highlighted word</label>
                        <input id="hero_title_highlight" name="hero_title_highlight" value="{{ $field('hero_title_highlight') }}" maxlength="30" required class="input bg-paper font-bold text-berry-700">
                        @error('hero_title_highlight') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="hero_title_end" class="label">Heading end</label>
                        <input id="hero_title_end" name="hero_title_end" value="{{ $field('hero_title_end') }}" maxlength="40" class="input bg-paper">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="hero_intro" class="label">Intro text</label>
                        <textarea id="hero_intro" name="hero_intro" rows="3" maxlength="300" class="input bg-paper">{{ $field('hero_intro') }}</textarea>
                    </div>
                    <div>
                        <label for="hero_button" class="label">Main button</label>
                        <input id="hero_button" name="hero_button" value="{{ $field('hero_button') }}" maxlength="30" required class="input bg-paper">
                        @error('hero_button') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            @php
                $checked = fn (string $key) => old($key, $errors->any() ? null : $storefront->get($key)) === '1';
            @endphp
            <section class="card p-5 sm:p-7" data-topbar-editor>
                <div class="flex items-start gap-4">
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-xl font-bold">Top bar</h2>
                        <p class="text-sm text-ink-soft">The thin strip above the menu on every shop page.</p>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="hidden" name="topbar_enabled" value="0">
                        <input type="checkbox" name="topbar_enabled" value="1" @checked($checked('topbar_enabled')) data-topbar-input class="peer sr-only">
                        <span class="relative h-7 w-12 shrink-0 rounded-full bg-ink/15 transition peer-checked:bg-mint-500 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                        <span class="sr-only">Show the top bar</span>
                    </label>
                </div>

                <div class="mt-5 overflow-hidden rounded-2xl ring-1 ring-ink/10">
                    <p class="bg-paper-deep px-3 py-1.5 text-[11px] font-bold tracking-wide text-ink-soft uppercase">Preview</p>
                    <div data-topbar-preview class="flex items-center justify-center gap-2 bg-plum-900 px-4 py-2.5 text-center text-xs font-semibold tracking-wide text-white">
                        <x-icon name="truck" class="size-4 shrink-0 text-butter-300" />
                        <span data-topbar-text></span>
                    </div>
                    <p data-topbar-hidden class="hidden bg-paper px-4 py-2.5 text-center text-xs text-ink-soft">The top bar is switched off.</p>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-[auto_1fr] sm:items-end">
                    <div class="rounded-2xl bg-mint-50 p-3 ring-1 ring-mint-500/20">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-bold">
                            <input type="hidden" name="topbar_free_delivery" value="0">
                            <input type="checkbox" name="topbar_free_delivery" value="1" @checked($checked('topbar_free_delivery')) data-topbar-input class="rounded border-ink/20 text-berry-600">
                            Show “Free delivery over”
                        </label>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="text-sm font-semibold text-ink-soft">RM</span>
                            <input id="delivery_free_over" name="delivery_free_over" type="number" step="0.01" min="0" value="{{ $field('delivery_free_over') }}" data-topbar-input class="input w-32 bg-surface" aria-label="Free delivery amount">
                        </div>
                        @error('delivery_free_over') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="announcement" class="label">Message <span class="font-normal text-ink-soft">· leave blank to hide</span></label>
                        <input id="announcement" name="announcement" value="{{ $field('announcement') }}" maxlength="120" placeholder="e.g. Raya pre-orders now open!" data-topbar-input class="input bg-paper">
                    </div>
                </div>
                <p class="mt-3 text-xs text-ink-soft">The free-delivery amount is the same one as on the Delivery page. It only shows while Standard delivery is switched on and the amount is above 0.</p>
            </section>

            <section class="card p-5 sm:p-7">
                <h2 class="font-display text-xl font-bold">Scrolling strip</h2>
                <div class="mt-5">
                    <label for="ticker" class="label">Phrases <span class="font-normal text-ink-soft">· one phrase per line</span></label>
                    <textarea id="ticker" name="ticker" rows="4" maxlength="1000" placeholder="Leave blank to show your product names" class="input bg-paper">{{ $field('ticker') }}</textarea>
                </div>
            </section>

            <section class="card p-5 sm:p-7">
                <h2 class="font-display text-xl font-bold">How it works</h2>
                <div class="mt-5">
                    <label for="steps_title" class="label">Section heading</label>
                    <input id="steps_title" name="steps_title" value="{{ $field('steps_title') }}" maxlength="60" required class="input bg-paper">
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    @foreach ([1, 2, 3] as $step)
                        <div class="rounded-3xl bg-paper p-4 ring-1 ring-ink/5">
                            <span class="grid size-7 place-items-center rounded-full bg-plum-900 text-xs font-extrabold text-white">{{ $step }}</span>
                            <label for="step_{{ $step }}_title" class="label mt-3">Title</label>
                            <input id="step_{{ $step }}_title" name="step_{{ $step }}_title" value="{{ $field("step_{$step}_title") }}" maxlength="40" required class="input bg-surface">
                            <label for="step_{{ $step }}_text" class="label mt-3">Text</label>
                            <textarea id="step_{{ $step }}_text" name="step_{{ $step }}_text" rows="3" maxlength="160" required class="input bg-surface">{{ $field("step_{$step}_text") }}</textarea>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card p-5 sm:p-7">
                <h2 class="font-display text-xl font-bold">Footer</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="footer_about" class="label">About the shop</label>
                        <textarea id="footer_about" name="footer_about" rows="3" maxlength="300" class="input bg-paper">{{ $field('footer_about') }}</textarea>
                    </div>
                    <div>
                        <label for="address" class="label">Address</label>
                        <textarea id="address" name="address" rows="3" maxlength="200" class="input bg-paper">{{ $field('address') }}</textarea>
                    </div>
                    <div>
                        <label for="opening_hours" class="label">Opening hours</label>
                        <input id="opening_hours" name="opening_hours" value="{{ $field('opening_hours') }}" maxlength="80" class="input bg-paper">
                    </div>
                </div>
            </section>
        </div>

        <div class="space-y-4 lg:sticky lg:top-6">
            <section class="card p-5">
                <h2 class="font-display text-xl font-bold">Home page picture</h2>
                <p class="text-sm text-ink-soft">Shown in the big circle. Without one, the limited-edition product is used.</p>
                <div id="hero-preview" class="mt-4 aspect-square overflow-hidden rounded-full bg-paper-deep ring-8 ring-paper">
                    @if ($storefront->heroImageUrl())
                        <img src="{{ $storefront->heroImageUrl() }}" alt="" class="size-full object-cover">
                    @else
                        <img src="{{ \App\Models\Product::defaultImageUrl() }}" alt="" class="size-full object-cover">
                    @endif
                </div>
                <label class="btn-outline mt-4 w-full cursor-pointer">
                    <x-icon name="upload" class="size-4" /> Upload picture
                    <input type="file" name="hero_image_file" accept="image/*" class="sr-only" data-image-input="#hero-preview">
                </label>
                @error('hero_image_file') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                @if ($storefront->heroImageUrl())
                    <label class="mt-3 flex items-center gap-2 text-sm text-ink-soft">
                        <input type="hidden" name="remove_hero_image" value="0">
                        <input type="checkbox" name="remove_hero_image" value="1" class="rounded border-ink/20 text-berry-600"> Remove picture
                    </label>
                @endif
            </section>

            <button class="btn-primary w-full py-3.5 text-base"><x-icon name="check" class="size-4.5" /> Save storefront</button>
        </div>
    </form>
</x-layouts.admin>
