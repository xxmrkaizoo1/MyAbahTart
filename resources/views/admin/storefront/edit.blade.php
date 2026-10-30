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

            <section class="card p-5 sm:p-7">
                <h2 class="font-display text-xl font-bold">Announcement & scrolling strip</h2>
                <div class="mt-5 grid gap-4">
                    <div>
                        <label for="announcement" class="label">Top bar message <span class="font-normal text-ink-soft">· shown after the free-delivery note</span></label>
                        <input id="announcement" name="announcement" value="{{ $field('announcement') }}" maxlength="120" placeholder="e.g. Raya pre-orders now open!" class="input bg-paper">
                    </div>
                    <div>
                        <label for="ticker" class="label">Scrolling strip <span class="font-normal text-ink-soft">· one phrase per line</span></label>
                        <textarea id="ticker" name="ticker" rows="4" maxlength="1000" placeholder="Leave blank to show your product names" class="input bg-paper">{{ $field('ticker') }}</textarea>
                    </div>
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
