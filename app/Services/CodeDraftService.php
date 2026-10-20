<?php

namespace App\Services;

use App\Models\CodeDraft;
use App\Models\Mission;
use App\Models\Student;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pengelolaan draf kode (autosave) per tim per misi.
 *
 * Penguncian optimis: klien mengirim versi terakhir yang diketahui. Bila versi
 * di DB lebih baru (orang lain menyimpan duluan), simpan DITOLAK dan pemanggil
 * menampilkan banner konflik.
 */
final class CodeDraftService
{
    public const KONFLIK = 'konflik';

    /**
     * Pastikan draf ada untuk semua fungsi + program utama dari starter_code.
     *
     * @return Collection<int, CodeDraft>
     */
    public function inisialisasi(Team $team, Mission $misi): Collection
    {
        $composer = app(SubmissionComposer::class);
        $bagian = $composer->split((string) $misi->starter_code);

        $draf = collect();

        foreach ($bagian as $b) {
            $draf->push(CodeDraft::firstOrCreate(
                [
                    'team_id' => $team->team_id,
                    'mission_id' => $misi->mission_id,
                    'function_name' => $b['function_name'],
                ],
                ['code_text' => $b['code']],
            ));
        }

        return $draf;
    }

    /**
     * Simpan draf dengan penguncian optimis.
     *
     * @return array{status: string, draft?: CodeDraft}
     */
    public function simpan(CodeDraft $draf, string $kode, Student $siswa, int $versiKlien): array
    {
        if ($versiKlien < (int) $draf->version) {
            return ['status' => self::KONFLIK];
        }

        $draf->update([
            'code_text' => $kode,
            'version' => (int) $draf->version + 1,
            'updated_by' => $siswa->student_id,
        ]);

        return ['status' => 'ok', 'draft' => $draf->fresh()];
    }

    /**
     * Tandai versi draf sudah ditinjau Reviewer.
     */
    public function tandaiDitinjau(CodeDraft $draf, Student $siswa): void
    {
        $draf->update([
            'reviewed_version' => (int) $draf->version,
            'reviewed_by' => $siswa->student_id,
        ]);
    }
}
