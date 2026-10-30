<x-layouts.auth title="Choose a new password">
    <div class="text-center">
        <p class="eyebrow">Account help</p>
        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">Choose a new password</h1>
        <p class="mt-2 text-sm text-ink-soft">Use at least 8 characters.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email" class="input">
            @error('email') <p class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">New password</label>
            <div class="relative">
                <input id="password" name="password" type="password" required autofocus autocomplete="new-password" placeholder="••••••••" class="input pr-12">
                <button type="button" data-toggle-password="password" class="absolute top-1/2 right-2 grid size-9 -translate-y-1/2 place-items-center rounded-full text-ink-soft hover:bg-ink/5" aria-label="Show password">
                    <x-icon name="eye" class="size-4.5" />
                </button>
            </div>
            @error('password') <p class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="label">Type it again</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="••••••••" class="input">
        </div>

        <button type="submit" class="btn-primary w-full py-3.5 text-base">Save new password <x-icon name="arrow-right" class="size-4.5" /></button>
    </form>
</x-layouts.auth>
