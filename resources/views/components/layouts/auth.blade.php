@props(['title'])

<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme->value }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="{{ $theme->metaColor() }}">

        <title>{{ $title }} — MYabah Tart Lejen</title>

        <link rel="icon" type="image/jpeg" href="{{ asset('images/logo.jpg') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logo.jpg') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="relative min-h-dvh overflow-x-hidden">
        <x-flash />

        {{-- Decorative background --}}
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
            <div class="absolute -top-32 -left-24 size-96 rounded-full bg-butter-200/70 blur-3xl"></div>
            <div class="absolute -right-24 -bottom-32 size-[28rem] rounded-full bg-berry-200/60 blur-3xl"></div>
            @foreach ([['pineapple', 'top-16 right-[8%] size-28 rotate-12'], ['lemon', 'bottom-24 left-[6%] size-24 -rotate-12'], ['pistachio', 'top-[45%] right-[3%] size-20 rotate-6'], ['berry', 'top-[30%] left-[4%] size-16 -rotate-6']] as [$art, $classes])
                <div class="absolute hidden overflow-hidden rounded-full opacity-90 shadow-soft ring-4 ring-surface md:block {{ $classes }} animate-float">
                    <x-tart-art :art="$art" />
                </div>
            @endforeach
        </div>

        <div class="flex min-h-dvh flex-col items-center px-4 py-8 sm:py-12">
            <a href="{{ route('home') }}"><x-logo /></a>

            <div class="flex w-full flex-1 items-center justify-center py-8">
                <div class="card w-full max-w-md bg-surface/80 p-6 backdrop-blur-xl sm:p-9">
                    {{ $slot }}
                </div>
            </div>

            <x-theme-switcher :labels="false" class="mb-3" />
            <p class="text-xs text-ink-soft">&copy; {{ now()->year }} MYabah Tart Lejen</p>
        </div>
    </body>
</html>
