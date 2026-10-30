@props(['rating' => 0, 'size' => 'size-4'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5']) }} aria-label="{{ number_format((float) $rating, 1) }} out of 5 stars">
    @for ($star = 1; $star <= 5; $star++)
        <x-icon name="star" :filled="true" class="{{ $size }} {{ $star <= round((float) $rating) ? 'text-butter-500' : 'text-ink/10' }}" />
    @endfor
</span>
