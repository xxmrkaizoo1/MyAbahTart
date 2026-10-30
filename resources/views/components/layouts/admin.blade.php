@props(['title', 'subtitle' => null])

@php
    $navigation = [
        ['admin.dashboard', 'Overview', 'dashboard', 'admin.dashboard'],
        ['admin.orders.index', 'Orders', 'receipt', 'admin.orders.*'],
        ['admin.pos.index', 'Counter', 'cart', 'admin.pos.*'],
        ['admin.products.index', 'Products', 'box', 'admin.products.*'],
        ['admin.categories.index', 'Collections', 'grid', 'admin.categories.*'],
        ['admin.refunds.index', 'Refunds', 'refresh', 'admin.refunds.*'],
        ['admin.vouchers.index', 'Vouchers', 'ticket', 'admin.vouchers.*'],
        ['admin.storefront.edit', 'Storefront', 'store', 'admin.storefront.*'],
        ['admin.delivery.edit', 'Delivery', 'truck', 'admin.delivery.*'],
    ];
@endphp

<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme->value }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="{{ $theme->swatches()[2] }}">

        <title>{{ $title }} — Admin — MYabah Tart Lejen</title>

        <link rel="icon" type="image/jpeg" href="{{ asset('images/logo.jpg') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logo.jpg') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh bg-canvas print:bg-white">
        <x-flash />

        <header data-site-header class="bg-plum-900 text-white print:hidden">
            <div class="container-page flex h-16 items-center gap-3 lg:h-[72px] lg:gap-8">
                <button type="button" data-drawer-open="admin-drawer" class="-ml-2 grid size-11 place-items-center rounded-full hover:bg-white/10 lg:hidden" aria-label="Open menu">
                    <x-icon name="menu" class="size-6" />
                </button>
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                    <x-logo light compact />
                    <span class="hidden leading-none whitespace-nowrap sm:block">
                        <span class="block font-display text-base font-extrabold">MYabah HQ</span>
                        <span class="block text-[10px] font-bold tracking-[0.2em] text-berry-200 uppercase">Admin portal</span>
                    </span>
                </a>

                <nav data-nav-track class="hidden items-center gap-1 rounded-button bg-white/5 p-1 ring-1 ring-white/10 lg:flex" aria-label="Admin">
                    @foreach ($navigation as [$route, $label, $icon, $pattern])
                        @php($isActive = request()->routeIs($pattern))
                        <a href="{{ route($route) }}" title="{{ $label }}" @if ($isActive) aria-current="page" data-lit @endif @class([
                            'group relative flex items-center gap-2 rounded-button px-4 py-2 text-sm font-semibold whitespace-nowrap text-white/70 lg:max-2xl:px-3 transition-colors duration-300 not-data-lit:hover:text-white data-lit:text-plum-900',
                            'z-0' => $isActive,
                            'z-10' => ! $isActive,
                        ])>
                            @if ($isActive)
                                <span data-nav-pill class="absolute inset-y-0 left-0 -z-10 w-full rounded-button bg-white shadow-md" aria-hidden="true"></span>
                            @endif
                            <x-icon :name="$icon" class="size-4.5 transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:-rotate-12" /> <span class="lg:max-2xl:sr-only">{{ $label }}</span>
                            @if ($route === 'admin.orders.index' && $activeOrderCount > 0)
                                <span class="rounded-full bg-berry-500 px-1.5 py-0.5 text-[10px] font-extrabold text-white">{{ $activeOrderCount }}</span>
                            @endif
                            @if ($route === 'admin.refunds.index' && $pendingRefundCount > 0)
                                <span class="rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-extrabold text-white">{{ $pendingRefundCount }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('home') }}" title="View shop" class="hidden items-center gap-2 rounded-full px-3 py-2 text-sm font-semibold whitespace-nowrap text-white/70 hover:bg-white/10 hover:text-white md:flex">
                        <x-icon name="store" class="size-4.5" /> <span class="lg:max-2xl:sr-only">View shop</span>
                    </a>
                    <details class="group relative">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full p-1 hover:bg-white/10 sm:pr-3">
                            <span class="grid size-9 place-items-center rounded-full bg-butter-300 text-xs font-extrabold text-plum-900">{{ auth()->user()->initials() }}</span>
                            <span class="hidden text-sm font-semibold sm:block">{{ auth()->user()->firstName() }}</span>
                        </summary>
                        <div class="card absolute right-0 z-50 mt-2 w-64 p-2 text-sm text-ink">
                            <p class="truncate px-3 py-2 text-xs text-ink-soft">{{ auth()->user()->email }}</p>
                            <div class="border-y border-ink/5 px-3 py-3">
                                <p class="mb-2 text-xs font-bold text-ink-soft">Colour theme</p>
                                <x-theme-switcher class="flex-col items-stretch" />
                            </div>
                            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-2xl px-3 py-2.5 hover:bg-paper"><x-icon name="store" class="size-4.5" /> View shop</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-rose-700 hover:bg-rose-50"><x-icon name="logout" class="size-4.5" /> Sign out</button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </header>

        {{-- Mobile drawer --}}
        <div id="admin-drawer" data-drawer class="fixed inset-0 z-50 hidden lg:hidden" role="dialog" aria-modal="true" aria-label="Admin menu">
            <div data-drawer-close class="absolute inset-0 bg-plum-950/60 backdrop-blur-sm"></div>
            <div class="absolute inset-y-0 left-0 flex w-[82%] max-w-xs flex-col bg-plum-900 p-5 text-white shadow-pop">
                <div class="flex items-center justify-between">
                    <x-logo light />
                    <button type="button" data-drawer-close class="grid size-11 place-items-center rounded-full hover:bg-white/10" aria-label="Close menu"><x-icon name="x" class="size-6" /></button>
                </div>
                <nav class="mt-8 space-y-1">
                    @foreach ($navigation as [$route, $label, $icon, $pattern])
                        <a href="{{ route($route) }}" style="--i: {{ $loop->index }}" @class([
                            'flex items-center gap-3 rounded-2xl px-4 py-3 font-semibold transition',
                            'bg-surface text-plum-900' => request()->routeIs($pattern),
                            'text-white/75 hover:bg-white/10' => ! request()->routeIs($pattern),
                        ])>
                            <x-icon :name="$icon" /> {{ $label }}
                            @if ($route === 'admin.orders.index' && $activeOrderCount > 0)
                                <span class="ml-auto rounded-full bg-berry-500 px-2 py-0.5 text-xs font-extrabold text-white">{{ $activeOrderCount }}</span>
                            @endif
                            @if ($route === 'admin.refunds.index' && $pendingRefundCount > 0)
                                <span class="ml-auto rounded-full bg-rose-500 px-2 py-0.5 text-xs font-extrabold text-white">{{ $pendingRefundCount }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
                <a href="{{ route('home') }}" class="mt-auto flex items-center gap-3 rounded-2xl px-4 py-3 font-semibold text-white/75 hover:bg-white/10"><x-icon name="store" /> View shop</a>
            </div>
        </div>

        <main class="container-page py-6 sm:py-8 lg:py-10">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between lg:mb-8 print:hidden">
                <div class="min-w-0">
                    <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="mt-1.5 text-sm text-ink-soft sm:text-base">{{ $subtitle }}</p>
                    @endif
                </div>
                @isset($actions)
                    <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                @endisset
            </div>

            {{ $slot }}
        </main>
        <x-confirm-dialog />
    </body>
</html>
