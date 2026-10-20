<?php

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AccessCodeService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.print'), Title('Cetak Kartu Akses')]
class extends Component
{
    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    /** @return array<int, string> */
    #[Computed]
    public function opsiKelas(): array
    {
        return SchoolClass::query()
            ->visibleTo(auth()->user())
            ->orderBy('class_name')
            ->pluck('class_name', 'class_id')
            ->all();
    }

    /** @return Collection<int, Student> */
    #[Computed]
    public function siswa(): Collection
    {
        return Student::query()
            ->visibleTo(auth()->user())
            ->when($this->kelasId !== null, fn ($q) => $q->where('class_id', $this->kelasId))
            ->with(['schoolClass', 'teamMembers.team'])
            ->orderBy('full_name')
            ->get();
    }

    public function tampil(string $kode): string
    {
        return app(AccessCodeService::class)->display($kode);
    }
}; ?>

<div class="mx-auto max-w-5xl px-4 py-6">
    <div class="no-print mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-line bg-white p-4 shadow-sm">
        <div class="min-w-56 flex-1">
            <x-ui.select
                label="Filter Kelas"
                name="kelasId"
                :options="$this->opsiKelas"
                placeholder="Semua kelas"
                wire:model.live="kelasId"
            />
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.siswa') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-page">
                <x-ui.icon.chevron-left class="size-4" />
                Kembali
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-primary-600/20 transition hover:bg-primary-700">
                <x-ui.icon.download class="size-4" />
                Cetak
            </button>
        </div>
    </div>

    @if ($this->siswa->isEmpty())
        <div class="rounded-xl border border-line bg-white p-10 text-center text-sm text-slate-400">
            Belum ada siswa dengan kode akses.
        </div>
    @else
        <div class="grid gap-3 print:grid-cols-2">
            @foreach ($this->siswa as $s)
                <div class="kartu flex items-center justify-between gap-3 rounded-xl border border-line bg-white p-4 shadow-sm">
                    <div class="min-w-0">
                        <p class="text-base font-bold text-slate-800">{{ $s->full_name }}</p>
                        <p class="text-xs text-slate-500">{{ $s->schoolClass?->class_name }} · {{ $s->teamMembers->first()?->team?->team_name ?? 'Belum ada kelompok' }}</p>
                        <p class="mt-1 text-[11px] text-slate-400">{{ url('/siswa/masuk') }}</p>
                    </div>
                    <div class="text-end">
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Kode Akses</p>
                        <p class="font-mono text-xl font-bold tracking-widest text-primary-700">{{ $s->access_code ? $this->tampil($s->access_code) : '—' }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>