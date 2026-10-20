{{--
    Tabel selisih: klaim perangkat siswa vs hasil verifikasi guru.

    Nilai harapan (expected_value) SENGAJA ditampilkan di sini — halaman ini
    khusus guru dan dijaga policy kelas. Paket yang dikirim ke browser guru
    tetap tidak memuat expected_value; nilainya berasal dari endpoint periksa
    yang dihitung server.
--}}
@php
    $hasilPerangkat = $data->results->keyBy(fn ($r) => (int) ($r->test_case_id ?? $r->bug_test_case_id));
    $hasilGuru = collect($pemeriksaanGuru['hasil'] ?? [])->keyBy('id');
    $adaSelisih = $data->has_mismatch;
@endphp

@if ($hasilGuru->isNotEmpty() || $adaSelisih)
    <div>
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                Perbandingan: Perangkat Siswa vs Verifikasi Guru
            </p>
            @if ($adaSelisih)
                <x-ui.badge variant="peringatan">
                    {{ (int) data_get($data->mismatch_detail, 'jumlah', 0) }} kasus berbeda
                </x-ui.badge>
            @endif
        </div>

        <div class="overflow-x-auto rounded-lg border border-line">
            <table class="min-w-full divide-y divide-line text-xs">
                <thead class="bg-page/60">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold text-slate-500">Kasus uji</th>
                        <th class="px-3 py-2 text-start font-semibold text-slate-500">Klaim perangkat</th>
                        <th class="px-3 py-2 text-start font-semibold text-slate-500">Verifikasi guru</th>
                        <th class="px-3 py-2 text-start font-semibold text-slate-500">Nilai harapan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-white">
                    @foreach ($data->results as $hasil)
                        @php
                            $id = (int) ($hasil->test_case_id ?? $hasil->bug_test_case_id);
                            $guru = $hasilGuru->get($id);
                            $beda = $guru !== null && (bool) $guru['lulus'] !== (bool) $hasil->is_passed;
                        @endphp
                        <tr @class(['bg-amber-50' => $beda])>
                            <td class="px-3 py-2 text-slate-700">
                                {{ $hasil->testCase?->label ?? 'Kasus uji' }}
                                @if ($beda)
                                    <x-ui.badge variant="peringatan" class="ms-1">Beda</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <span class="flex items-center gap-1.5">
                                    <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-600">{{ $hasil->actual_value ?? '—' }}</code>
                                    <x-ui.badge :variant="$hasil->is_passed ? 'sukses' : 'bahaya'">
                                        {{ $hasil->is_passed ? 'Lulus' : 'Gagal' }}
                                    </x-ui.badge>
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                @if ($guru === null)
                                    <span class="text-slate-400">Belum dijalankan</span>
                                @else
                                    <span class="flex items-center gap-1.5">
                                        <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-600">{{ $guru['aktual'] }}</code>
                                        <x-ui.badge :variant="$guru['lulus'] ? 'sukses' : 'bahaya'">
                                            {{ $guru['lulus'] ? 'Lulus' : 'Gagal' }}
                                        </x-ui.badge>
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-600">{{ $hasil->testCase?->expected_value ?? '—' }}</code>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
