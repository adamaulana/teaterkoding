<?php

namespace App\Console\Commands;

use App\Models\BugAttempt;
use App\Models\FunctionOwnership;
use App\Models\GradingSetting;
use App\Models\ManualScore;
use App\Models\MissionPlan;
use App\Models\MissionPlanConcept;
use App\Models\RoleAssignment;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Submission;
use App\Models\SubmissionResult;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hapus data demo buatan DemoDataSeeder.
 *
 * PENTING: perintah ini HANYA menghapus kelas demo (nama dari
 * config/teater.php, default "[DEMO] X RPL") beserta seluruh turunannya.
 * Materi global (level, misi, kasus uji, kartu bug, kartu konsep) TIDAK
 * disentuh, karena mungkin sudah dipakai kelas nyata.
 */
class HapusDemo extends Command
{
    protected $signature = 'teater:hapus-demo {--force : Lewati konfirmasi}';

    protected $description = 'Hapus kelas demo beserta seluruh data turunannya (materi global tetap aman)';

    public function handle(): int
    {
        $namaKelas = (string) config('teater.demo_class_name', '[DEMO] X RPL');

        $kelas = SchoolClass::query()->where('class_name', $namaKelas)->get();

        if ($kelas->isEmpty()) {
            $this->warn("Tidak ditemukan kelas demo bernama \"{$namaKelas}\". Tidak ada yang dihapus.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Hapus kelas \"{$namaKelas}\" beserta seluruh data turunannya?")) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $classIds = $kelas->pluck('class_id')->all();

        $teamIds = Team::query()->whereIn('class_id', $classIds)->pluck('team_id')->all();
        $studentIds = Student::query()->whereIn('class_id', $classIds)->pluck('student_id')->all();
        $submissionIds = Submission::query()->whereIn('team_id', $teamIds)->pluck('submission_id')->all();

        DB::transaction(function () use ($classIds, $teamIds, $studentIds, $submissionIds): void {
            // 1. Hasil kasus uji menempel pada submission.
            if ($submissionIds !== []) {
                SubmissionResult::query()->whereIn('submission_id', $submissionIds)->delete();
            }

            // 2. Ledger skor & nilai manual (per tim dan per siswa).
            ScoreEntry::query()->whereIn('team_id', $teamIds)->delete();
            ManualScore::query()->whereIn('student_id', $studentIds)->delete();

            // 3. Aktivitas permainan.
            BugAttempt::query()->whereIn('team_id', $teamIds)->delete();
            FunctionOwnership::query()->whereIn('team_id', $teamIds)->delete();
            RoleAssignment::query()->whereIn('team_id', $teamIds)->delete();

            // Pivot rencana → kartu konsep harus dihapus sebelum rencananya.
            MissionPlanConcept::query()
                ->whereIn('plan_id', MissionPlan::query()->whereIn('team_id', $teamIds)->pluck('plan_id'))
                ->delete();

            MissionPlan::query()->whereIn('team_id', $teamIds)->delete();
            Submission::query()->whereIn('team_id', $teamIds)->delete();

            // 4. Bobot penilaian kelas demo.
            GradingSetting::query()->whereIn('class_id', $classIds)->delete();

            // 5. Struktur akademik.
            TeamMember::query()->whereIn('team_id', $teamIds)->delete();
            Team::query()->whereIn('class_id', $classIds)->delete();
            Student::query()->whereIn('class_id', $classIds)->delete();
            SchoolClass::query()->whereIn('class_id', $classIds)->delete();
        });

        $this->info('Data demo berhasil dihapus:');
        $this->line('  - '.count($classIds).' kelas');
        $this->line('  - '.count($teamIds).' kelompok');
        $this->line('  - '.count($studentIds).' siswa');
        $this->line('  - '.count($submissionIds).' submission');
        $this->line('Materi global (level, misi, kasus uji, kartu bug/konsep) tidak diubah.');

        return self::SUCCESS;
    }
}
