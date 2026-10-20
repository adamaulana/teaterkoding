<?php

namespace App\Services;

use App\Models\CodeDraft;
use Illuminate\Support\Collection;

/**
 * Memecah & menyusun kode tim untuk misi sumatif.
 *
 * Format starter_code sumatif memakai penanda baris:
 *     # === FUNGSI: nama_fungsi ===
 * Teks sebelum penanda pertama = "Program Utama" (function_name kosong);
 * tiap bagian setelah penanda = draf fungsi dengan nama tersebut.
 */
final class SubmissionComposer
{
    /** Awalan baris penanda fungsi (dari config/teater.php). */
    private const PENANDA = '# === FUNGSI:';

    /**
     * Pecah starter_code menjadi draf per fungsi + program utama.
     *
     * @return Collection<int, array{function_name: string, code: string}>
     */
    public function split(string $starterCode): Collection
    {
        $baris = preg_split('/\r\n|\r|\n/', $starterCode) ?: [];
        $hasil = collect();
        $penyangga = [];
        $namaAktif = '';

        $simpan = function () use (&$hasil, &$penyangga, &$namaAktif): void {
            $hasil->push([
                'function_name' => $namaAktif,
                'code' => trim(implode("\n", $penyangga)),
            ]);
            $penyangga = [];
        };

        foreach ($baris as $satu) {
            if (preg_match('/^'.preg_quote(self::PENANDA, '/').'\s*([A-Za-z_][A-Za-z0-9_]*)\s*===?/', $satu, $cocok) === 1) {
                $simpan();
                $namaAktif = $cocok[1];

                continue;
            }

            $penyangga[] = $satu;
        }

        $simpan();

        return $hasil->values();
    }

    /**
     * Susun kode tim: semua draf fungsi (urut sesuai penanda starter) lalu
     * Program Utama di akhir, dipisah baris kosong. Draf kosong diganti stub.
     *
     * @param  Collection<int, string>  $urutanFungsi  nama fungsi sesuai penanda
     * @param  Collection<int, CodeDraft>  $draf
     * @return array{code: string, parts: Collection<int, array{function_name: string, student_id: int|null, code_text: string}>}
     */
    public function susun(Collection $urutanFungsi, Collection $draf): array
    {
        $perNama = $draf->keyBy(fn (CodeDraft $d) => $d->function_name);

        $bagian = [];
        $parts = collect();

        foreach ($urutanFungsi as $nama) {
            $d = $perNama->get($nama);
            $kode = $d !== null && trim((string) $d->code_text) !== ''
                ? (string) $d->code_text
                : $this->stub($nama);

            $bagian[] = $kode;
            $parts->push([
                'function_name' => (string) $nama,
                'student_id' => $d?->owner_student_id,
                'code_text' => $kode,
            ]);
        }

        $utama = $perNama->get(CodeDraft::PROGRAM_UTAMA);
        $kodeUtama = $utama !== null && trim((string) $utama->code_text) !== ''
            ? (string) $utama->code_text
            : '';

        $bagian[] = $kodeUtama;
        $parts->push([
            'function_name' => CodeDraft::PROGRAM_UTAMA,
            'student_id' => $utama?->owner_student_id,
            'code_text' => $kodeUtama,
        ]);

        return [
            'code' => implode("\n\n", $bagian),
            'parts' => $parts,
        ];
    }

    /**
     * Stub untuk fungsi yang drafnya kosong (masih "pass").
     */
    public function stub(string $namaFungsi): string
    {
        return "def {$namaFungsi}():\n    pass";
    }

    /**
     * Ekstrak daftar nama fungsi sesuai urutan penanda pada starter_code.
     *
     * @return Collection<int, string>
     */
    public function urutanFungsi(string $starterCode): Collection
    {
        return $this->split($starterCode)
            ->filter(fn (array $bagian) => $bagian['function_name'] !== '')
            ->pluck('function_name')
            ->values();
    }

    /**
     * Deteksi nama fungsi ganda pada starter_code (kembalikan nama yang duplikat).
     *
     * @return array<int, string>
     */
    public function fungsiGanda(string $starterCode): array
    {
        $nama = $this->urutanFungsi($starterCode)->all();

        return array_values(array_unique(array_diff_assoc($nama, array_unique($nama))));
    }
}
