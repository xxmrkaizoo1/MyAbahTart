@if (session('status') || session('error'))
    <div data-flash class="print:hidden pointer-events-none fixed inset-x-0 bottom-24 z-[60] flex justify-center px-4 transition duration-300 sm:top-6 sm:bottom-auto">
        <div @class([
            'pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-2xl px-4 py-3.5 text-sm font-semibold shadow-pop',
            'bg-plum-900 text-white' => session('status'),
            'bg-rose-700 text-white' => session('error'),
        ]) role="status">
            <span @class(['grid size-6 shrink-0 place-items-center rounded-full', 'bg-mint-500' => session('status'), 'bg-white/20' => session('error')])>
                <x-icon :name="session('error') ? 'alert' : 'check'" class="size-3.5" />
            </span>
            <span class="flex-1 pt-0.5">{{ session('error') ?? session('status') }}</span>
            <button type="button" data-dismiss class="rounded-lg p-0.5 opacity-70 hover:opacity-100" aria-label="Dismiss">
                <x-icon name="x" class="size-4" />
            </button>
        </div>
    </div>
@endif
