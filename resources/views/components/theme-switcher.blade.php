@props(['dark' => false, 'labels' => true, 'detailed' => false])

@php
    use App\Enums\Theme;

    $current = Theme::current(request());
@endphp

@if ($detailed)
    <div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-3 lg:grid-cols-4']) }} role="group" aria-label="Design theme">
        @foreach (Theme::cases() as $option)
            @php([$primary, $accent, $darkColour] = $option->swatches())
            <button type="button" data-theme-choice="{{ $option->value }}" aria-pressed="{{ $current === $option ? 'true' : 'false' }}"
                class="group flex flex-col overflow-hidden rounded-3xl bg-surface text-left ring-1 ring-ink/10 transition hover:-translate-y-0.5 hover:shadow-soft aria-pressed:ring-2 aria-pressed:ring-ink">
                <span class="relative flex h-20 items-end gap-1.5 p-3" style="background-color: {{ $darkColour }}">
                    <span class="h-full flex-1 rounded-xl" style="background-color: {{ $primary }}"></span>
                    <span class="h-2/3 flex-1 rounded-xl" style="background-color: {{ $accent }}"></span>
                    <span class="absolute top-2.5 right-2.5 hidden size-6 place-items-center rounded-full bg-surface text-ink group-aria-pressed:grid"><x-icon name="check" class="size-3.5" /></span>
                </span>
                <span class="p-3.5">
                    <span class="flex items-center gap-2 text-sm font-bold text-ink">
                        {{ $option->label() }}
                        @if ($option->isDark())
                            <span class="chip bg-plum-900 text-white">Dark</span>
                        @endif
                    </span>
                    <span class="mt-0.5 block text-xs text-ink-soft">{{ $option->description() }}</span>
                </span>
            </button>
        @endforeach
    </div>
@else
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }} role="group" aria-label="Design theme">
        @foreach (Theme::cases() as $option)
            <button type="button" data-theme-choice="{{ $option->value }}" aria-pressed="{{ $current === $option ? 'true' : 'false' }}" title="{{ $option->label() }} — {{ $option->description() }}" @class([
                'flex items-center gap-2 rounded-full p-1 text-xs font-semibold ring-1 transition',
                'pr-3' => $labels,
                'text-white/80 ring-white/15 hover:ring-white/40 aria-pressed:text-white aria-pressed:ring-2 aria-pressed:ring-butter-300' => $dark,
                'text-ink-soft ring-ink/10 hover:ring-ink/30 aria-pressed:text-ink aria-pressed:ring-2 aria-pressed:ring-berry-600' => ! $dark,
            ])>
                <span class="flex -space-x-1.5" aria-hidden="true">
                    @foreach ($option->swatches() as $colour)
                        <span class="size-4 rounded-full ring-2 {{ $dark ? 'ring-plum-900' : 'ring-surface' }}" style="background-color: {{ $colour }}"></span>
                    @endforeach
                </span>
                <span @class(['sr-only' => ! $labels])>{{ $option->label() }}</span>
            </button>
        @endforeach
    </div>
@endif
