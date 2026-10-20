@php
    $sub = $this->submissionTerakhir;
    $memakaiPyodide = app(\App\Services\SubmissionService::class)->memakaiPyodide();
    $diagnosa = app(\App\Services\DiagnosaPenilaian::class);
@endphp

@if ($sub === null)
    <x-ui.card>
        <x-ui.empty-state icon="info-circle" title="Belum ada submission" message="Kumpulkan kode kalian terlebih dahulu untuk melihat hasil." />
    </x-ui.card>
@elseif ($sub->status === 'menunggu' && $memakaiPyodide)
    {{-- Penilaian terjadi di BROWSER siswa; server tidak pernah menjalankan
         kode. Karena itu jangan menampilkan "sedang dinilai" ala antrean job. --}}
    <x-ui.card>
        <div class="flex items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-50 text-primary-600">
                <x-ui.icon.braces class="size-5" />
            </span>
            <div>
                <h2 class="text-lg font-semibold text-slate-800">Menunggu penilaian di perangkat</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Kode kalian dijalankan di browser ini. Bila penilaian terputus (tab ditutup atau sesi kedaluwarsa),
                    tekan <span class="font-medium text-slate-700">Nilai Ulang</span> di bawah.
                </p>
            </div>
        </div>

        <div class="mt-4">
            <x-ui.penilai-pyodide jenis="misi" :id="$sub->submission_id" />
        </div>
    </x-ui.card>
@elseif ($sub->status === 'menunggu')
    @php
        $detik = (int) ($sub->submitted_at?->diffInSeconds(now()) ?? 0);
        $lama = $detik > 60;
        $pesanDiagnosa = $lama ? $diagnosa->pesanUntukPenantian() : null;
    @endphp

    {{-- Antrean job: poll cepat hanya selama 60 detik pertama. Setelah itu
         berhenti, supaya tidak membebani server tanpa hasil (dulu wire:poll.2s
         berjalan selamanya sampai "Maximum execution time" di log). --}}
    <x-ui.card>
        <div class="flex flex-col items-center py-8 text-center" @if (! $lama) wire:poll.2s @endif>
            <span class="grid size-12 place-items-center rounded-full bg-primary-50 text-primary-600">
                <x-ui.icon.refresh class="size-6 {{ $lama ? '' : 'animate-spin' }}" />
            </span>
            <p class="mt-4 text-base font-semibold text-slate-800">
                {{ $lama ? 'Penilaian belum selesai' : 'Sedang dinilai...' }}
            </p>
            <p class="mt-1 text-sm text-slate-400">
                {{ $lama ? 'Kodemu sudah tersimpan. Ini penyebabnya:' : 'Hasil akan tampil beberapa saat lagi.' }}
            </p>
        </div>

        @if ($lama)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                <p class="font-semibold">Penyebab</p>
                <p class="mt-0.5">{{ $pesanDiagnosa ?? 'Penilai otomatis belum mengolah submission ini. Kode kalian aman tersimpan.' }}</p>
                <p class="mt-1 text-amber-700/80">
                    Hubungi guru bila pesan ini tidak berubah setelah beberapa menit.
                </p>
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <x-ui.button variant="outline" wire:click="$refresh">
                    <x-ui.icon.refresh class="size-4" />
                    Periksa Status
                </x-ui.button>
            </div>
        @endif
    </x-ui.card>
@elseif ($sub->status === 'menunggu_verifikasi')
    @include('livewire.siswa.partials.hasil-kasus', ['sub' => $sub, 'sementara' => true])
@elseif ($sub->status === 'dinilai')
    @include('livewire.siswa.partials.hasil-kasus', ['sub' => $sub, 'sementara' => false])
@else
    {{-- galat / waktu_habis --}}
    <x-ui.card>
        <div class="flex items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-rose-50 text-rose-600">
                <x-ui.icon.alert-circle class="size-5" />
            </span>
            <div>
                <h2 class="text-lg font-semibold text-slate-800">
                    {{ $sub->status === 'galat' ? 'Ada galat pada kodemu' : 'Waktu habis' }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">Periksa kembali kodemu, lalu coba submit ulang.</p>

                @if (filled($sub->error_message))
                    <pre class="mt-3 overflow-x-auto rounded-lg border border-rose-100 bg-rose-50 p-3 font-mono text-xs text-rose-700">{{ $sub->error_message }}</pre>
                @endif
            </div>
        </div>

        <div class="mt-5 flex justify-between gap-3">
            <x-ui.button variant="outline" wire:click="$set('langkah', 'koding')">
                <x-ui.icon.chevron-left class="size-4" />
                Kembali ke Koding
            </x-ui.button>
        </div>
    </x-ui.card>
@endif