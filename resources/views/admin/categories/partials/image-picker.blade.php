{{-- Photo upload for a collection. Expects $category (or null for a new one) and a unique $id for the preview. --}}
<div>
    <p class="label">Photo</p>
    <div class="flex items-center gap-3">
        <div id="{{ $id }}" class="size-20 shrink-0 overflow-hidden rounded-2xl bg-paper ring-1 ring-ink/10">
            <img src="{{ $category?->imageUrl() ?? \App\Models\Product::defaultImageUrl() }}" alt="" class="size-full object-cover">
        </div>
        <div class="min-w-0 flex-1 space-y-2">
            <label class="btn-outline w-full cursor-pointer py-2">
                <x-icon name="upload" class="size-4" /> {{ $category?->image_path ? 'Change photo' : 'Upload photo' }}
                <input type="file" name="image" accept="image/*" class="sr-only" data-image-input="#{{ $id }}">
            </label>
            @if ($category?->image_path)
                <label class="flex items-center gap-2 text-xs text-ink-soft">
                    <input type="checkbox" name="remove_image" value="1" class="rounded border-ink/20 text-berry-600"> Remove photo
                </label>
            @else
                <p class="text-xs text-ink-soft">No photo? The default picture is used.</p>
            @endif
        </div>
    </div>
</div>
