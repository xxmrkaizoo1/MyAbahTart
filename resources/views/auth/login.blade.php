<x-layouts.auth title="Sign in">
    <div class="text-center">
        <p class="eyebrow">Welcome back</p>
        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">Sign in</h1>
        <p class="mt-2 text-sm text-ink-soft">Use the email or mobile number you registered with.</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label for="login" class="label">Email or mobile number</label>
            <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" placeholder="you@example.com" class="input">
            @error('login') <p class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Password</label>
            <div class="relative">
                <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" class="input pr-12">
                <button type="button" data-toggle-password="password" class="absolute top-1/2 right-2 grid size-9 -translate-y-1/2 place-items-center rounded-full text-ink-soft hover:bg-ink/5" aria-label="Show password">
                    <x-icon name="eye" class="size-4.5" />
                </button>
            </div>
        </div>

        <label class="flex items-center gap-2.5 text-sm text-ink-soft">
            <input type="checkbox" name="remember" class="size-4.5 rounded border-ink/20 text-berry-600 focus:ring-berry-500">
            Keep me signed in
        </label>

        <button type="submit" class="btn-primary w-full py-3.5 text-base">Sign in <x-icon name="arrow-right" class="size-4.5" /></button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-soft">
        New here? <a href="{{ route('register') }}" class="font-bold text-berry-700 hover:underline">Create an account</a>
    </p>
</x-layouts.auth>
