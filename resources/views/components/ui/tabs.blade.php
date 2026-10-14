@props([
    'tabs',
    'active' => null,
    'property' => 'activeTab',
])

<nav class="flex gap-6 overflow-x-auto border-b border-line" role="tablist">
    @foreach ($tabs as $tab)
        <button
            type="button"
            role="tab"
            wire:click="$set('{{ $property }}', '{{ $tab['key'] }}')"
            @class([
                '-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition',
                'border-primary-600 text-primary-700' => $active === $tab['key'],
                'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' => $active !== $tab['key'],
            ])
        >
            {{ $tab['label'] }}
            @if (array_key_exists('count', $tab) && $tab['count'] !== null)
                <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold',
                    'bg-primary-100 text-primary-700' => $active === $tab['key'],
                    'bg-slate-100 text-slate-500' => $active !== $tab['key'],
                ])>{{ $tab['count'] }}</span>
            @endif
        </button>
    @endforeach
</nav>
