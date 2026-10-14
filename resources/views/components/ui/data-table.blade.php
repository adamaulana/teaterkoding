@props([
    'rows',
    'columns',
    'searchable' => false,
    'searchProperty' => 'search',
    'searchPlaceholder' => 'Cari...',
    'perPageProperty' => 'perPage',
    'showPerPage' => true,
    'sortProperty' => null,
    'sortDirection' => null,
    'emptyTitle' => 'Belum ada data',
    'emptyMessage' => 'Belum ada data yang bisa ditampilkan.',
])

{{--
    Tabel data siap pakai untuk halaman Livewire.

    $columns = [
        ['key' => 'name', 'label' => 'Nama', 'sortable' => true],
        ['key' => 'role', 'label' => 'Peran', 'format' => fn ($row) => ...],
    ]

    Query/ pencarian/ urutan dikelola halaman (pakai App\Concerns\InteractsWithTable);
    komponen ini hanya menampilkan + memancarkan aksi (wire:click / wire:model).
--}}

<x-ui.card :padding="false">
    {{-- Alat atas: pencarian + jumlah data per halaman --}}
    <div class="flex flex-col gap-3 border-b border-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        @if ($searchable)
            <div class="relative w-full sm:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <x-ui.icon.search class="size-4" />
                </span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="{{ $searchProperty }}"
                    placeholder="{{ $searchPlaceholder }}"
                    aria-label="{{ $searchPlaceholder }}"
                    class="block w-full rounded-lg border border-line bg-white py-2 pl-9 pr-3 text-sm text-slate-800 placeholder:text-slate-400 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                >
            </div>
        @else
            <div></div>
        @endif

        @if ($showPerPage)
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <span>Tampilkan</span>
                <select wire:model.live="{{ $perPageProperty }}" class="rounded-lg border-line bg-white py-1.5 text-sm text-slate-700 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30">
                    @foreach ([10, 25, 50, 100] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                <span>data</span>
            </div>
        @endif
    </div>

    {{-- Indikator memuat --}}
    <div class="h-0.5 w-full overflow-hidden bg-primary-100/40">
        <div class="h-full w-full animate-pulse bg-primary-400 opacity-0 transition-opacity" wire:loading.class="opacity-100"></div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-page/60">
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500 {{ $column['class'] ?? '' }}">
                            @if ($column['sortable'] ?? false)
                                <button type="button" wire:click="sortTable('{{ $column['key'] }}')" class="inline-flex items-center gap-1 uppercase tracking-wider transition hover:text-primary-700">
                                    {{ $column['label'] }}
                                    @if ($sortProperty === $column['key'])
                                        <x-dynamic-component :component="'ui.icon.'.($sortDirection === 'desc' ? 'arrow-down' : 'arrow-up')" class="size-3.5 text-primary-600" />
                                    @else
                                        <x-ui.icon.arrow-up class="size-3.5 text-slate-300" />
                                    @endif
                                </button>
                            @else
                                {{ $column['label'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody class="divide-y divide-line bg-white">
                @forelse ($rows as $row)
                    <tr wire:key="baris-{{ $loop->index }}" class="transition hover:bg-page/70">
                        @foreach ($columns as $column)
                            @php
                                // Kolom dengan closure 'format' dianggap markup tepercaya
                                // (mis. badge/avatar/dropdown). Kolom tanpa 'format'
                                // diambil via data_get dan tetap di-escape otomatis.
                                $hasFormat = isset($column['format']) && is_callable($column['format']);
                                $cell = $hasFormat ? ($column['format'])($row) : data_get($row, $column['key']);
                            @endphp
                            <td class="px-4 py-3 align-middle {{ $column['class'] ?? '' }}">
                                @if ($hasFormat)
                                    {!! $cell instanceof \Illuminate\Contracts\Support\Htmlable ? $cell->toHtml() : (string) $cell !!}
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="px-4 py-16 text-center">
                            <div class="mx-auto grid size-12 place-items-center rounded-full bg-primary-50 text-primary-500">
                                <x-ui.icon.list-check />
                            </div>
                            <p class="mt-3 text-sm font-medium text-slate-700">{{ $emptyTitle }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $emptyMessage }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Footer tabel: info jumlah + paginasi --}}
    @if ($rows instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="flex flex-col gap-3 border-t border-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">
                Menampilkan {{ $rows->firstItem() ?? 0 }}&ndash;{{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} data
            </p>

            <div class="flex items-center gap-2">
                <x-ui.button
                    variant="outline"
                    size="sm"
                    wire:click="$set('page', {{ max(1, $rows->currentPage() - 1) }})"
                    @disabled(! $rows->previousPageUrl())
                >
                    <x-ui.icon.chevron-left class="size-4" />
                    Sebelumnya
                </x-ui.button>

                <span class="text-xs text-slate-500">Hal. {{ $rows->currentPage() }} / {{ $rows->lastPage() }}</span>

                <x-ui.button
                    variant="outline"
                    size="sm"
                    wire:click="$set('page', {{ $rows->currentPage() + 1 }})"
                    @disabled(! $rows->nextPageUrl())
                >
                    Berikutnya
                    <x-ui.icon.chevron-right class="size-4" />
                </x-ui.button>
            </div>
        </div>
    @endif
</x-ui.card>
