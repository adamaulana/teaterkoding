@props(['items' => []])

@if (count($items) > 0)
    <nav class="flex flex-wrap items-center gap-1.5 text-sm" aria-label="Breadcrumb">
        @foreach ($items as $item)
            @if (! $loop->last)
                @if (! empty($item['url']))
                    <a href="{{ $item['url'] }}" class="text-slate-400 transition hover:text-primary-700">{{ $item['label'] }}</a>
                @else
                    <span class="text-slate-400">{{ $item['label'] }}</span>
                @endif
                <x-ui.icon.chevron-right class="size-4 text-slate-300" />
            @else
                <span class="font-medium text-slate-700">{{ $item['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif
