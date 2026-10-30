<x-layouts.admin title="Vouchers" subtitle="Create discount codes and see how often they’re used.">
    <div class="grid gap-6 lg:grid-cols-[1fr_380px] lg:items-start">
        <section class="grid gap-4 sm:grid-cols-2">
            @forelse ($vouchers as $voucher)
                @php($stats = $usage[$voucher->code] ?? null)
                <article @class(['relative flex flex-col overflow-hidden rounded-3xl shadow-soft', 'opacity-60' => ! $voucher->isUsable()])>
                    <div class="bg-berry-600 p-5 text-white">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-display text-4xl font-extrabold">{{ $voucher->valueLabel() }}<span class="text-lg"> off</span></p>
                            <form method="POST" action="{{ route('admin.vouchers.update', $voucher) }}">
                                @csrf
                                @method('PATCH')
                                <button @class([
                                    'relative inline-flex h-7 w-12 items-center rounded-full transition',
                                    'bg-butter-300' => $voucher->is_active,
                                    'bg-white/25' => ! $voucher->is_active,
                                ]) aria-label="{{ $voucher->is_active ? 'Pause' : 'Activate' }} {{ $voucher->code }}">
                                    <span @class(['inline-block size-5 rounded-full bg-white shadow transition', 'translate-x-6' => $voucher->is_active, 'translate-x-1' => ! $voucher->is_active])></span>
                                </button>
                            </form>
                        </div>
                        <p class="mt-1 text-sm text-white/80">{{ $voucher->description }}</p>
                    </div>
                    {{-- perforation --}}
                    <div class="relative h-0 border-t-2 border-dashed border-surface/60 bg-surface">
                        <span class="absolute -top-3 -left-3 size-6 rounded-full bg-canvas"></span>
                        <span class="absolute -top-3 -right-3 size-6 rounded-full bg-canvas"></span>
                    </div>
                    <div class="flex flex-1 flex-col bg-surface p-5">
                        <div class="flex items-center justify-between gap-2">
                            <code class="rounded-xl bg-paper px-3 py-1.5 font-mono text-base font-bold tracking-wider">{{ $voucher->code }}</code>
                            <form method="POST" action="{{ route('admin.vouchers.destroy', $voucher) }}" data-confirm="Delete voucher {{ $voucher->code }}?">
                                @csrf
                                @method('DELETE')
                                <button class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-rose-50 hover:text-rose-700" aria-label="Delete {{ $voucher->code }}"><x-icon name="trash" class="size-4.5" /></button>
                            </form>
                        </div>
                        <dl class="mt-4 grid grid-cols-3 gap-2 text-xs">
                            <div><dt class="text-ink-soft">Min. spend</dt><dd class="font-bold">@rm($voucher->min_subtotal)</dd></div>
                            <div><dt class="text-ink-soft">Used</dt><dd class="font-bold">{{ $stats->uses ?? 0 }}×</dd></div>
                            <div><dt class="text-ink-soft">Expires</dt><dd class="font-bold">{{ $voucher->expires_at?->format('j M Y') ?? 'Never' }}</dd></div>
                        </dl>
                    </div>
                </article>
            @empty
                <p class="card p-12 text-center text-sm text-ink-soft sm:col-span-2">No vouchers yet.</p>
            @endforelse
        </section>

        <form method="POST" action="{{ route('admin.vouchers.store') }}" class="card space-y-4 p-5 sm:p-7 lg:sticky lg:top-6">
            @csrf
            <h2 class="font-display text-xl font-bold">New voucher</h2>
            <div>
                <label for="code" class="label">Code</label>
                <input id="code" name="code" value="{{ old('code') }}" required placeholder="SUMMER20" class="input bg-paper font-mono uppercase">
                @error('code') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="description" class="label">Description</label>
                <input id="description" name="description" value="{{ old('description') }}" required placeholder="20% off for the summer" class="input bg-paper">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="type" class="label">Type</label>
                    <select id="type" name="type" class="input bg-paper">
                        <option value="fixed" @selected(old('type') === 'fixed')>Fixed RM</option>
                        <option value="percent" @selected(old('type') === 'percent')>Percent %</option>
                    </select>
                </div>
                <div>
                    <label for="value" class="label">Value</label>
                    <input id="value" name="value" type="number" step="0.01" min="0.01" value="{{ old('value') }}" required class="input bg-paper">
                    @error('value') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="min_subtotal" class="label">Min. spend</label>
                    <input id="min_subtotal" name="min_subtotal" type="number" step="0.01" min="0" value="{{ old('min_subtotal', 0) }}" class="input bg-paper">
                </div>
                <div>
                    <label for="expires_at" class="label">Expires</label>
                    <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at') }}" class="input bg-paper">
                    @error('expires_at') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>
            <button class="btn-primary w-full"><x-icon name="plus" class="size-4" /> Create voucher</button>
        </form>
    </div>
</x-layouts.admin>
