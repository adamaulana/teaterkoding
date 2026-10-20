{{--
    Modal "Rilis Kartu Bug" — dipakai bersama oleh Pantau Live & Kontrol Kelas.
    Halaman pemanggil menyediakan:
      - $this->opsiLevelRilis (array level_id => level_name)
      - $this->kartuBelumDirilis (Collection<BugCard>) untuk level terpilih
      - properti: rilisTeamId, rilisLevelId
      - aksi: bukaRilis(teamId), simpanRilis()
--}}
<x-ui.modal name="modal-rilis-bug" title="Rilis Kartu Bug">
    <form wire:submit="simpanRilis" class="space-y-4">
        <x-ui.alert variant="info">
            Kartu yang sudah pernah dirilis ke kelompok ini tidak akan muncul lagi di daftar.
        </x-ui.alert>

        <x-ui.select
            label="Level"
            name="rilisLevelId"
            :options="$this->opsiLevelRilis"
            wire:model.live="rilisLevelId"
            required
        />

        @if ($this->kartuBelumDirilis->isEmpty())
            <p class="rounded-lg border border-line bg-page/60 px-3 py-4 text-center text-sm text-slate-400">
                Tidak ada kartu bug yang bisa dirilis pada level ini.
            </p>
        @else
            <div class="max-h-64 space-y-2 overflow-y-auto">
                @foreach ($this->kartuBelumDirilis as $kartu)
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-line p-3 transition hover:border-primary-200">
                        <input type="radio" name="rilisBugId" value="{{ $kartu->bug_id }}" wire:model="rilisBugId" class="mt-0.5 size-4 border-line text-primary-600 focus:ring-primary-500/30">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-800">{{ $kartu->bug_title }}</span>
                            <span class="block text-xs text-slate-400">+{{ $kartu->points }} poin</span>
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end gap-2 pt-2">
            <x-ui.button variant="outline" type="button" x-on:click="$dispatch('modal-rilis-bug-close')">Batal</x-ui.button>
            <x-ui.button variant="primary" type="submit" @disabled($this->kartuBelumDirilis->isEmpty())>Rilis Kartu</x-ui.button>
        </div>
    </form>
</x-ui.modal>