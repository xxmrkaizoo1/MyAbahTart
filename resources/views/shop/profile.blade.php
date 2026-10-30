<x-layouts.shop title="Profile">
    <div class="container-page py-6 sm:py-10">
        <section class="relative overflow-hidden rounded-4xl bg-berry-600 p-6 text-white shadow-pop sm:p-10">
            <div class="pointer-events-none absolute -top-16 -right-16 size-64 rounded-full bg-butter-300/30 blur-2xl" aria-hidden="true"></div>
            <div class="relative flex flex-wrap items-center gap-5">
                <span class="grid size-20 place-items-center rounded-3xl bg-butter-300 font-display text-3xl font-extrabold text-plum-900 shadow-pop">{{ $user->initials() }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-white/70">Hello,</p>
                    <h1 class="truncate text-3xl font-extrabold sm:text-4xl">{{ $user->name }}</h1>
                    <p class="truncate text-sm text-white/70">{{ $user->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn bg-white/15 text-white hover:bg-white/25"><x-icon name="logout" class="size-4" /> Sign out</button>
                </form>
            </div>
            <dl class="relative mt-8 grid grid-cols-3 gap-3">
                @foreach ([[$orderCount, 'Orders'], ['RM '.number_format($totalSpent, 0), 'Spent'], [$reviewCount, 'Reviews']] as [$value, $label])
                    <div class="rounded-2xl bg-white/10 p-3 sm:p-4">
                        <dt class="text-xs font-semibold text-white/70">{{ $label }}</dt>
                        <dd class="mt-0.5 font-display text-xl font-extrabold sm:text-2xl">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        @if ($user->isAdmin())
            <a href="{{ route('admin.dashboard') }}" class="mt-4 flex items-center gap-4 rounded-3xl bg-plum-900 p-4 text-white hover:bg-plum-800">
                <span class="grid size-11 place-items-center rounded-2xl bg-white/10"><x-icon name="dashboard" /></span>
                <span class="flex-1 font-bold">Open the admin portal</span>
                <x-icon name="arrow-right" />
            </a>
        @endif

        <section class="mt-8 rounded-4xl bg-surface p-5 shadow-soft ring-1 ring-ink/5 sm:p-8">
            <h2 class="font-display text-2xl font-bold">Choose your design</h2>
            <p class="mt-1 text-sm text-ink-soft">Each theme changes the colours, heading font and shape of the whole site. Your choice is saved on this device.</p>
            <x-theme-switcher detailed class="mt-6" />
        </section>

        <form method="POST" action="{{ route('profile.update') }}" class="mt-8 grid gap-6 lg:grid-cols-[1.3fr_1fr]">
            @csrf
            @method('PUT')

            <section class="rounded-4xl bg-surface p-5 shadow-soft ring-1 ring-ink/5 sm:p-8">
                <h2 class="font-display text-2xl font-bold">Personal details</h2>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([['name', 'Full name', 'text'], ['email', 'Email', 'email'], ['phone', 'Mobile number', 'tel']] as [$field, $label, $type])
                        <div @class(['sm:col-span-2' => $field === 'name'])>
                            <label for="{{ $field }}" class="label">{{ $label }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $user->{$field}) }}" required class="input bg-paper">
                            @error($field) <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <label for="address" class="label">Default delivery address</label>
                        <textarea id="address" name="address" rows="3" class="input bg-paper">{{ old('address', $user->address) }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-4xl bg-surface p-5 shadow-soft ring-1 ring-ink/5 sm:p-8 lg:self-start">
                <h2 class="font-display text-2xl font-bold">Password</h2>
                <p class="mt-1 text-sm text-ink-soft">Leave blank to keep your current password.</p>
                <div class="mt-6 space-y-4">
                    <div>
                        <label for="current_password" class="label">Current password</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="input bg-paper">
                        @error('current_password') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password" class="label">New password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" class="input bg-paper">
                    </div>
                    <div>
                        <label for="password_confirmation" class="label">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="input bg-paper">
                    </div>
                    @error('password') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </section>

            <div class="lg:col-span-2">
                <button class="btn-primary w-full px-10 py-3.5 text-base sm:w-auto">Save changes</button>
            </div>
        </form>
    </div>
</x-layouts.shop>
