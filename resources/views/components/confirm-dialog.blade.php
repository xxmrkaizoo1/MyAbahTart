{{-- Shared "are you sure?" pop-up used by every form or button with data-confirm. --}}
<dialog id="confirm-dialog" data-dialog
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-4xl bg-surface p-0 text-ink shadow-pop backdrop:bg-plum-950/50 backdrop:backdrop-blur-sm open:animate-[pop-in_160ms_ease-out]" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
    <div class="p-6 text-center sm:p-7">
        <span class="mx-auto grid size-14 place-items-center rounded-full bg-rose-50 text-rose-600 ring-8 ring-rose-50/50">
            <x-icon name="trash" class="size-6" data-confirm-icon="danger" />
            <x-icon name="alert" class="hidden size-6" data-confirm-icon="caution" />
        </span>
        <h2 id="confirm-dialog-title" class="mt-4 font-display text-xl font-bold">Are you sure?</h2>
        <p id="confirm-dialog-message" class="mt-2 text-sm leading-relaxed text-ink-soft" data-confirm-message></p>

        <div class="mt-6 grid grid-cols-2 gap-2">
            <button type="button" data-dialog-close class="btn-outline">Cancel</button>
            <button type="button" data-confirm-accept class="btn bg-rose-600 text-white hover:bg-rose-700">Yes, continue</button>
        </div>
    </div>
</dialog>
