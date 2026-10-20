@props([
    'kode' => '',
    'sorot' => [],
])

{{--
    Blok kode monospace dengan nomor baris.

    SELURUH isi di-escape oleh Blade ({{ }}), tidak ada {!! !!}: kode siswa
    adalah input tidak tepercaya. Nomor baris dibuat di sisi server agar tidak
    bergantung pada JavaScript dan tetap tampil walau JS gagal.

    Props:
      kode   teks Python
      sorot  daftar nomor baris (1-based) yang diberi latar berbeda, mis. baris
             yang hasilnya berbeda antara klaim perangkat dan verifikasi guru.
--}}
@php
    $baris = preg_split('/\r\n|\r|\n/', (string) $kode) ?: [];
    $sorot = array_map('intval', (array) $sorot);
@endphp

<div {{ $attributes->merge(['class' => 'overflow-auto rounded-lg border border-line bg-slate-50']) }}>
    <table class="w-full border-collapse font-mono text-xs leading-relaxed">
        <tbody>
            @foreach ($baris as $nomor => $isi)
                @php $nomorBaris = $nomor + 1; @endphp
                <tr @class(['bg-amber-50' => in_array($nomorBaris, $sorot, true)])>
                    <td class="w-10 select-none border-e border-line px-2 py-0.5 text-end align-top text-slate-400">
                        {{ $nomorBaris }}
                    </td>
                    <td class="whitespace-pre px-3 py-0.5 align-top text-slate-800">{{ $isi === '' ? ' ' : $isi }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
