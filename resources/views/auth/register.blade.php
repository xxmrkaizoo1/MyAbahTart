<x-layouts.auth title="Create an account">
    <div class="text-center">
        <p class="eyebrow">Join the club</p>
        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">Create an account</h1>
        <p class="mt-2 text-sm text-ink-soft">Order faster, track every order and get picks made for you.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
        @csrf

        @foreach ([
            ['name', 'Full name', 'text', 'Your full name', 'name'],
            ['email', 'Email', 'email', 'you@example.com', 'email'],
            ['phone', 'Mobile number', 'tel', '012-345 6789', 'tel'],
        ] as [$field, $label, $type, $placeholder, $autocomplete])
            <div>
                <label for="{{ $field }}" class="label">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" required autocomplete="{{ $autocomplete }}" placeholder="{{ $placeholder }}" class="input">
                @error($field) <p class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
            </div>
        @endforeach

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="At least 8 characters" class="input">
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repeat password" class="input">
            </div>
        </div>
        @error('password') <p class="text-sm font-medium text-rose-700">{{ $message }}</p> @enderror

        <button type="submit" class="btn-primary mt-2 w-full py-3.5 text-base">Create account <x-icon name="arrow-right" class="size-4.5" /></button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-soft">
        Already have an account? <a href="{{ route('login') }}" class="font-bold text-berry-700 hover:underline">Sign in</a>
    </p>
</x-layouts.auth>
