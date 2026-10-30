<x-layouts.auth title="Forgot password">
    <div class="text-center">
        <p class="eyebrow">Account help</p>
        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">Forgot your password?</h1>
        <p class="mt-2 text-sm text-ink-soft">Enter the email you registered with and we’ll send you a link to choose a new one.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@example.com" class="input">
            @error('email') <p class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-primary w-full py-3.5 text-base">Email me a reset link <x-icon name="arrow-right" class="size-4.5" /></button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-soft">
        Remembered it? <a href="{{ route('login') }}" class="font-bold text-berry-700 hover:underline">Back to sign in</a>
    </p>
</x-layouts.auth>
