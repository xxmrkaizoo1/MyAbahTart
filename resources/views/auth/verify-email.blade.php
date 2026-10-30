<x-layouts.auth title="Verify your email">
    <div class="text-center">
        <span class="mx-auto grid size-16 place-items-center rounded-full bg-berry-50 text-berry-600"><x-icon name="mail" class="size-7" /></span>
        <p class="eyebrow mt-5">One last step</p>
        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">Check your inbox</h1>
        <p class="mt-3 text-sm text-ink-soft">
            We sent a verification link to <span class="font-bold text-ink">{{ auth()->user()->email }}</span>.
            Click it to start ordering. It may take a minute, so check your spam folder too.
        </p>
    </div>

    <form method="POST" action="{{ route('verification.send') }}" class="mt-8">
        @csrf
        <button type="submit" class="btn-primary w-full py-3.5 text-base"><x-icon name="refresh" class="size-4.5" /> Send the link again</button>
    </form>

    <div class="mt-6 flex items-center justify-between gap-3 text-sm">
        <a href="{{ route('profile.edit') }}" class="font-bold text-berry-700 hover:underline">Wrong email? Change it</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="font-semibold text-ink-soft hover:text-ink">Sign out</button>
        </form>
    </div>
</x-layouts.auth>
