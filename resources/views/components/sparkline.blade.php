@props(['values' => [], 'stroke' => 'stroke-berry-500', 'fill' => 'fill-berry-500/10'])

@php
    $values = array_values($values);
    $count = max(count($values), 2);
    $max = max([1, ...$values]);
    $min = min([0, ...$values]);
    $points = collect($values)->map(fn ($value, $index) => [
        round($index / ($count - 1) * 100, 2),
        round(28 - (($value - $min) / max($max - $min, 1)) * 24, 2),
    ]);
    $line = $points->map(fn ($point) => implode(',', $point))->implode(' ');
@endphp

@if ($points->count() > 1)
    <svg {{ $attributes->merge(['class' => 'h-10 w-full']) }} viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">
        <polygon points="0,30 {{ $line }} 100,30" class="{{ $fill }}" />
        <polyline points="{{ $line }}" fill="none" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" class="{{ $stroke }}" />
    </svg>
@endif
