<x-layouts.admin title="Collections" subtitle="Group your products into collections. They appear as filters on the shop menu.">
    <div class="grid gap-6 lg:grid-cols-[1fr_380px] lg:items-start">
        <section class="grid gap-4 sm:grid-cols-2">
            @forelse ($categories as $category)
                @php($bag = $errors->getBag('category'.$category->id))
                <article class="card flex flex-col overflow-hidden">
                    <div class="flex items-center gap-4 p-5">
                        <img src="{{ $category->imageUrl() }}" alt="" class="size-16 shrink-0 rounded-2xl object-cover shadow-soft">
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate font-display text-xl font-bold">{{ $category->name }}</h2>
                            <p class="text-sm text-ink-soft">{{ $category->products_count }} {{ Str::plural('product', $category->products_count) }} · position {{ $category->sort_order }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Delete the {{ $category->name }} collection?">
                            @csrf
                            @method('DELETE')
                            <button @disabled($category->products_count > 0) class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-rose-50 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-ink-soft"
                                aria-label="Delete {{ $category->name }}" title="{{ $category->products_count > 0 ? 'Move or delete its products first' : 'Delete collection' }}">
                                <x-icon name="trash" class="size-4.5" />
                            </button>
                        </form>
                    </div>

                    <details class="group border-t border-ink/5" @if ($bag->any()) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-3 text-sm font-semibold text-ink-soft hover:text-ink">
                            <span class="flex items-center gap-2"><x-icon name="edit" class="size-4" /> Edit collection</span>
                            <a href="{{ route('admin.products.index', ['category' => $category->slug]) }}" class="text-berry-700 hover:underline">View products</a>
                        </summary>
                        <form method="POST" action="{{ route('admin.categories.update', $category) }}" enctype="multipart/form-data" class="space-y-4 px-5 pb-5">
                            @csrf
                            @method('PATCH')
                            <div class="grid grid-cols-[1fr_96px] gap-3">
                                <div>
                                    <label for="name-{{ $category->id }}" class="label">Name</label>
                                    <input id="name-{{ $category->id }}" name="name" value="{{ $bag->any() ? old('name') : $category->name }}" required maxlength="60" class="input bg-paper">
                                </div>
                                <div>
                                    <label for="sort-{{ $category->id }}" class="label">Position</label>
                                    <input id="sort-{{ $category->id }}" name="sort_order" type="number" min="0" max="999" value="{{ $bag->any() ? old('sort_order') : $category->sort_order }}" class="input bg-paper">
                                </div>
                            </div>
                            @foreach ($bag->all() as $message)
                                <p class="text-sm text-rose-700">{{ $message }}</p>
                            @endforeach
                            @include('admin.categories.partials.image-picker', ['category' => $category, 'id' => 'category-photo-'.$category->id])
                            <button class="btn-dark w-full">Save changes</button>
                        </form>
                    </details>
                </article>
            @empty
                <p class="card p-12 text-center text-sm text-ink-soft sm:col-span-2">No collections yet. Create your first one to start adding products.</p>
            @endforelse
        </section>

        <form method="POST" action="{{ route('admin.categories.store') }}" enctype="multipart/form-data" class="card space-y-4 p-5 sm:p-7 lg:sticky lg:top-6">
            @csrf
            <h2 class="font-display text-xl font-bold">New collection</h2>
            <div>
                <label for="name" class="label">Name</label>
                <input id="name" name="name" value="{{ $errors->any() ? old('name') : '' }}" required maxlength="60" placeholder="e.g. Festive Specials" class="input bg-paper">
                @error('name') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="sort_order" class="label">Position <span class="font-normal text-ink-soft">(optional)</span></label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="999" value="{{ $errors->any() ? old('sort_order') : '' }}" placeholder="Last" class="input bg-paper">
                <p class="mt-1 text-xs text-ink-soft">Lower numbers show first on the shop menu.</p>
                @error('sort_order') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            @include('admin.categories.partials.image-picker', ['category' => null, 'id' => 'new-category-photo'])
            @error('image') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
            <button class="btn-primary w-full"><x-icon name="plus" class="size-4" /> Create collection</button>
        </form>
    </div>
</x-layouts.admin>
