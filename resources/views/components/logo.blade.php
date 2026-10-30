@props(['light' => false, 'compact' => false, 'size' => 'size-11'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <img src="{{ asset('images/logo.jpg') }}" alt="" width="640" height="640" class="{{ $size }} shrink-0 rounded-full bg-white object-cover shadow-sm ring-2 ring-white/80">
    @unless ($compact)
        <span class="leading-none">
            <span @class(['block font-display text-lg font-extrabold tracking-tight', 'text-white' => $light, 'text-ink' => ! $light])>MYabah</span>
            <span @class(['block text-[10px] font-bold tracking-[0.22em] uppercase', 'text-berry-200' => $light, 'text-berry-600' => ! $light])>Tart Lejen</span>
        </span>
    @endunless
</span>
