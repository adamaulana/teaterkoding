{{-- Ilustrasi perputaran peran: panah melingkar (SVG inline) + alur level. --}}
<li class="reveal flex flex-col justify-center rounded-xl border border-dashed border-primary-200 bg-primary-50/50 p-5">
    <div class="flex items-center gap-3">
        <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-white text-primary-600 ring-1 ring-primary-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6" aria-hidden="true">
                <path d="M20 12a8 8 0 1 1-2.34-5.66" />
                <path d="M20 4v4h-4" />
            </svg>
        </span>
        <p class="text-sm font-semibold text-slate-800">Peran bergiliran</p>
    </div>

    <p class="mt-3 text-sm leading-relaxed text-slate-500">
        Level 1 &rarr; Level 2 &rarr; Level 3 &rarr; Level 4: peran bergeser.
    </p>

    <div class="mt-3 flex items-center gap-1" aria-hidden="true">
        @foreach ([1, 2, 3, 4] as $level)
            <span class="flex-1 rounded-full bg-primary-200 py-0.5 text-center text-[10px] font-semibold text-primary-800">
                L{{ $level }}
            </span>
            @if ($level < 4)
                <span class="text-xs text-primary-400">&rarr;</span>
            @endif
        @endforeach
    </div>
</li>
