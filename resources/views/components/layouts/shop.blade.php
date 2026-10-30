@props(['title' => null, 'cartPill' => true])

@php
    $links = [
        ['home', 'Home', 'home', 'home'],
        ['products.index', 'Shop all', 'grid', 'products.*'],
        ['orders.index', 'My orders', 'receipt', 'orders.*'],
    ];
@endphp

<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme->value }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="{{ $theme->metaColor() }}">

        <title>{{ $title ? $title.' — ' : '' }}MYabah Tart Lejen</title>

        <link rel="icon" type="image/jpeg" href="{{ asset('images/logo.jpg') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logo.jpg') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-dvh flex-col">
        <x-flash />

        @php($freeOver = $storefront->get('topbar_free_delivery') === '1' ? \App\Enums\DeliveryMethod::freeDeliveryThreshold() : null)
        @if ($storefront->get('topbar_enabled') === '1' && ($freeOver || $storefront->get('announcement') !== ''))
            <div class="bg-plum-900 text-white">
                <p class="container-page flex items-center justify-center gap-2 py-2 text-center text-[11px] font-semibold tracking-wide sm:text-xs">
                    <x-icon name="truck" class="size-4 shrink-0 text-butter-300" />
                    <span>@if ($freeOver)Free delivery over @rm($freeOver)@endif @if ($storefront->get('announcement') !== '')<span @class(['hidden sm:inline' => $freeOver])>{{ $freeOver ? '· ' : '' }}{{ $storefront->get('announcement') }}</span>@endif</span>
                </p>
            </div>
        @endif

        <header data-site-header class="sticky top-0 z-40 px-2 pt-2 sm:px-4 sm:pt-3">
            <div data-header-bar class="mx-auto flex h-16 max-w-7xl items-center gap-3 rounded-4xl bg-surface/75 px-3 ring-1 ring-ink/[0.07] backdrop-blur-xl sm:h-[4.5rem] sm:px-5 lg:gap-6">
                <button type="button" data-drawer-open="shop-drawer" class="-ml-2 grid size-11 place-items-center rounded-full hover:bg-ink/5 lg:hidden" aria-label="Open menu">
                    <x-icon name="menu" class="size-6" />
                </button>

                <a href="{{ route('home') }}" class="shrink-0" aria-label="MYabah Tart Lejen home"><x-logo /></a>

                <nav data-nav-track class="hidden items-center gap-1 rounded-button bg-paper-deep/60 p-1 ring-1 ring-ink/5 lg:flex" aria-label="Main">
                    @foreach ($links as [$route, $label, $icon, $pattern])
                        @php($isActive = request()->routeIs($pattern))
                        <a href="{{ route($route) }}" @if ($isActive) aria-current="page" data-lit @endif @class([
                            'group relative flex items-center gap-2 rounded-button px-4 py-2 text-sm font-semibold text-ink-soft transition-colors duration-300 not-data-lit:hover:text-ink data-lit:text-white',
                            'z-0' => $isActive,
                            'z-10' => ! $isActive,
                        ])>
                            @if ($isActive)
                                <span data-nav-pill class="absolute inset-y-0 left-0 -z-10 w-full rounded-button bg-berry-600 shadow-md shadow-berry-600/30" aria-hidden="true"></span>
                            @endif
                            <x-icon :name="$icon" class="size-4.5 transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:-rotate-12" />
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>

                <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                    <form action="{{ route('products.index') }}" class="relative hidden md:block">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-ink-soft" />
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search products" class="input w-48 rounded-full bg-paper/70 py-2.5 pl-10 xl:w-64" aria-label="Search products">
                    </form>

                    @auth
                        <details class="group relative hidden sm:block">
                            <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full p-1 pr-3 ring-1 ring-ink/10 transition hover:bg-surface">
                                <span class="grid size-8 place-items-center rounded-full bg-butter-300 text-xs font-extrabold text-plum-900">{{ auth()->user()->initials() }}</span>
                                <span class="text-sm font-semibold">{{ auth()->user()->firstName() }}</span>
                            </summary>
                            <div class="card absolute right-0 mt-2 w-56 p-2 text-sm">
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-2xl px-3 py-2.5 hover:bg-paper"><x-icon name="user" class="size-4.5" /> Profile</a>
                                <a href="{{ route('orders.index') }}" class="flex items-center gap-3 rounded-2xl px-3 py-2.5 hover:bg-paper"><x-icon name="bag" class="size-4.5" /> My orders</a>
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-2xl px-3 py-2.5 hover:bg-paper"><x-icon name="dashboard" class="size-4.5" /> Admin portal</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-ink/5 pt-1">
                                    @csrf
                                    <button class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-rose-700 hover:bg-rose-50"><x-icon name="logout" class="size-4.5" /> Sign out</button>
                                </form>
                            </div>
                        </details>
                    @else
                        <a href="{{ route('login') }}" class="btn-ghost hidden sm:inline-flex">Sign in</a>
                    @endauth

                    <a href="{{ route('cart.index') }}" class="btn-dark relative px-3.5 sm:px-4" aria-label="Cart, {{ $cartCount }} items">
                        <x-icon name="bag" class="size-5" />
                        <span class="hidden sm:inline">Cart</span>
                        <span @class(['grid min-w-5 place-items-center rounded-full px-1 text-[11px] font-extrabold', 'bg-butter-300 text-plum-900' => $cartCount > 0, 'bg-white/15' => $cartCount === 0])>{{ $cartCount }}</span>
                    </a>
                </div>
            </div>
        </header>

        {{-- Mobile drawer --}}
        <div id="shop-drawer" data-drawer class="fixed inset-0 z-50 hidden lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
            <div data-drawer-close class="absolute inset-0 bg-plum-950/50 backdrop-blur-sm"></div>
            <div class="absolute inset-y-0 left-0 flex w-[86%] max-w-sm flex-col overflow-y-auto bg-paper p-5 shadow-pop">
                <div class="flex items-center justify-between">
                    <x-logo />
                    <button type="button" data-drawer-close class="grid size-11 place-items-center rounded-full hover:bg-ink/5" aria-label="Close menu"><x-icon name="x" class="size-6" /></button>
                </div>

                <form action="{{ route('products.index') }}" class="relative mt-6">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-ink-soft" />
                    <input type="search" name="search" placeholder="Search products" class="input rounded-full pl-10" aria-label="Search products">
                </form>

                <nav class="mt-6 space-y-1" aria-label="Mobile">
                    @foreach ($links as [$route, $label, $icon, $pattern])
                        <a href="{{ route($route) }}" style="--i: {{ $loop->index }}" @if (request()->routeIs($pattern)) aria-current="page" @endif @class([
                            'group flex items-center gap-3 rounded-2xl px-3 py-3 font-display text-xl font-bold transition',
                            'bg-berry-600 text-white shadow-md shadow-berry-600/30' => request()->routeIs($pattern),
                            'hover:bg-surface' => ! request()->routeIs($pattern),
                        ])>
                            <span @class([
                                'grid size-10 place-items-center rounded-xl transition-transform duration-300 group-hover:-rotate-6',
                                'bg-white/15' => request()->routeIs($pattern),
                                'bg-berry-50 text-berry-700' => ! request()->routeIs($pattern),
                            ])><x-icon :name="$icon" class="size-5" /></span>
                            <span class="flex-1">{{ $label }}</span>
                            <x-icon name="arrow-right" class="size-5 opacity-50 transition-transform duration-300 group-hover:translate-x-1" />
                        </a>
                    @endforeach
                </nav>

                <p class="eyebrow mt-8 px-4">Collections</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    @foreach ($navCategories as $category)
                        <a href="{{ route('products.index', ['category' => $category->slug]) }}" class="flex items-center gap-2 rounded-2xl bg-surface p-2 text-sm font-semibold ring-1 ring-ink/5">
                            <img src="{{ $category->imageUrl() }}" alt="" class="size-9 shrink-0 rounded-xl object-cover">
                            <span class="leading-tight">{{ $category->name }}</span>
                        </a>
                    @endforeach
                </div>

                <p class="eyebrow mt-8 px-4">Colour theme</p>
                <x-theme-switcher class="mt-3 px-1" />

                <div class="mt-auto space-y-2 pt-8">
                    @auth
                        <a href="{{ route('profile.edit') }}" class="btn-outline w-full"><x-icon name="user" class="size-4.5" /> {{ auth()->user()->name }}</a>
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="btn-outline w-full"><x-icon name="dashboard" class="size-4.5" /> Admin portal</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn-ghost w-full text-rose-700">Sign out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn-dark w-full">Sign in</a>
                        <a href="{{ route('register') }}" class="btn-outline w-full">Create an account</a>
                    @endauth
                </div>
            </div>
        </div>

        <main class="flex-1 pb-28 lg:pb-20">
            {{ $slot }}
        </main>

        <footer class="relative mx-2 mb-2 overflow-hidden rounded-4xl bg-plum-900 sm:mx-4 sm:mb-4">
            <div class="pointer-events-none absolute -top-32 -right-24 size-96 rounded-full bg-berry-500/25 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-40 -left-24 size-96 rounded-full bg-butter-400/15 blur-3xl" aria-hidden="true"></div>
            <div class="container-page relative grid gap-10 py-14 text-sm text-white/70 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <div>
                    <x-logo light size="size-28" />
                    <p class="mt-4 max-w-xs leading-relaxed">{{ $storefront->get('footer_about') }}</p>
                    <p class="mt-6 text-xs font-bold tracking-wider text-white/50 uppercase">Colour theme</p>
                    <x-theme-switcher dark class="mt-2" />
                </div>
                <div>
                    <p class="font-display text-base font-bold text-white">Shop</p>
                    <ul class="mt-4 space-y-2.5">
                        @foreach ($navCategories as $category)
                            <li><a href="{{ route('products.index', ['category' => $category->slug]) }}" class="hover:text-white">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <p class="font-display text-base font-bold text-white">Account</p>
                    <ul class="mt-4 space-y-2.5">
                        <li><a href="{{ route('orders.index') }}" class="hover:text-white">Track my order</a></li>
                        <li><a href="{{ route('cart.index') }}" class="hover:text-white">Cart</a></li>
                        <li><a href="{{ route('profile.edit') }}" class="hover:text-white">Profile</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-display text-base font-bold text-white">Visit our shop</p>
                    <p class="mt-4 leading-relaxed whitespace-pre-line">{{ $storefront->get('address') }}</p>
                    @if ($storefront->get('opening_hours') !== '')
                        <p class="mt-3 flex items-center gap-2"><x-icon name="clock" class="size-4 text-butter-300" /> {{ $storefront->get('opening_hours') }}</p>
                    @endif
                </div>
            </div>
            <div class="relative border-t border-white/10">
                <p class="container-page py-5 text-xs text-white/50">&copy; {{ now()->year }} MYabah Tart Lejen. All rights reserved.</p>
            </div>
        </footer>

        @if ($cartPill && $cartCount > 0 && ! request()->routeIs('cart.*'))
            <a href="{{ route('cart.index') }}" class="fixed inset-x-4 bottom-4 z-40 flex items-center gap-3 rounded-full bg-plum-900 py-2 pr-2 pl-5 text-white shadow-pop lg:hidden" style="margin-bottom: env(safe-area-inset-bottom)">
                <x-icon name="bag" class="size-5 text-butter-300" />
                <span class="flex-1 text-sm font-semibold">{{ $cartCount }} {{ Str::plural('item', $cartCount) }} · @rm($cartSubtotal)</span>
                <span class="btn-butter py-2">Checkout <x-icon name="arrow-right" class="size-4" /></span>
            </a>
        @endif
        <x-confirm-dialog />
    </body>
</html>
