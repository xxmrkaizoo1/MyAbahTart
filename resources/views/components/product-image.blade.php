@props(['product', 'size' => null])

<div {{ $attributes->merge(['class' => 'relative overflow-hidden bg-paper-deep']) }}>
    <img src="{{ $product->imageUrl($size) ?? \App\Models\Product::defaultImageUrl() }}" alt="{{ $product->name }}" class="size-full object-cover" loading="lazy">
    {{ $slot }}
</div>
