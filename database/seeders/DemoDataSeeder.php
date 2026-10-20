<?php

namespace Database\Seeders;

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\ClassLevelAccess;
use App\Models\CodeDraft;
use App\Models\ConceptCard;
use App\Models\FunctionOwnership;
use App\Models\GradingSetting;
use App\Models\Level;
use App\Models\ManualScore;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\Reflection;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Submission;
use App\Models\SubmissionResult;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TestCase;
use App\Models\User;
use App\Services\AccessCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh lengkap untuk mencoba Fase 4 (Dashboard, Pantau Live, Riwayat
 * Submission, Skor & Peringkat, Rekap Nilai Sumatif).
 *
 * BERSIFAT MANDIRI: bila tabel materi masih kosong, seeder ini ikut membuat
 * level 1-4, empat misi, kasus ujinya, dan kartu bug. Semua memakai
 * updateOrCreate / firstOrCreate sehingga aman dijalankan berulang dan tidak
 * menimpa materi yang sudah dibuat guru.
 *
 * HANYA untuk lingkungan local/testing — DatabaseSeeder tidak memanggilnya.
 * Bersihkan hasilnya dengan `php artisan teater:hapus-demo`.
 */
class DemoDataSeeder extends Seeder
{
    /** Nama kelas demo (dibaca juga oleh perintah teater:hapus-demo). */
    private string $namaKelas;

    public function run(): void
    {
        $this->namaKelas = (string) config('teater.demo_class_name', '[DEMO] X RPL');

        // MATERI bukan tanggung jawab seeder ini: MateriKoperasiSeeder adalah
        // satu-satunya sumber materi (level, misi, kasus uji, kartu konsep,
        // kartu bug, kasus uji bug, starter_code, symptom_text).
        $this->call(MateriKoperasiSeeder::class);

        DB::transaction(function (): void {
            $this->seedKelas();
        });

        if ($this->command !== null) {
            $this->command->info('Data demo Fase 4 siap. Hapus dengan: php artisan teater:hapus-demo');
        }
    }

    /**
     * Kelas demo, 24 siswa, 6 kelompok, lalu seluruh data permainannya.
     */
    private function seedKelas(): void
    {
        $kelas = SchoolClass::updateOrCreate(
            ['class_name' => $this->namaKelas],
            [
                'school_year' => '2025/2026',
                'user_id' => User::query()->where('role', 'guru')->value('id'),
            ],
        );

        // Bobot khusus kelas demo: 50/20/10/10/10 (sama dengan default, tetapi
        // disimpan agar halaman Pengaturan Penilaian menampilkan "Tersimpan").
        GradingSetting::updateOrCreate(
            ['class_id' => $kelas->class_id],
            GradingSetting::bobotDefault(),
        );

        $siswa = $this->seedSiswa($kelas);
        $kelompok = $this->seedKelompok($kelas, $siswa);

        $this->seedPenugasanPeran($kelompok);
        $this->seedPermainan($kelompok);
        $this->seedAksesLevel($kelas);
        $this->seedDrafRefleksiDanBug($kelompok);
        $this->cetakKodeAkses($siswa);
    }

    /**
     * Buat kode akses untuk siswa demo (8 karakter, unik) — idempoten.
     *
     * @param  array<int, Student>  $siswa
     */
    private function cetakKodeAkses(array $siswa): void
    {
        $servis = app(AccessCodeService::class);

        $daftar = [];

        foreach ($siswa as $satu) {
            if ($satu->access_code === null) {
                $kode = $this->kodeUnik($servis);
                $satu->forceFill([
                    'access_code' => $kode,
                    'access_code_generated_at' => now(),
                ])->save();
            }

            $daftar[] = [$satu->full_name, $servis->display((string) $satu->access_code)];
        }

        if ($this->command !== null) {
            $this->command->newLine();
            $this->command->info('Kode akses siswa demo (login di /siswa/masuk):');

            foreach ($daftar as [$nama, $kode]) {
                $this->command->line("  {$kode}  {$nama}");
            }

            $this->command->newLine();
        }
    }

    private function kodeUnik(AccessCodeService $servis): string
    {
        do {
            $kode = $servis->generate();
        } while (Student::query()->where('access_code', $kode)->exists());

        return $kode;
    }

    /**
     * Buka akses level 1-4 untuk kelas demo (idempoten).
     */
    private function seedAksesLevel(SchoolClass $kelas): void
    {
        foreach (Level::query()->pluck('level_id') as $levelId) {
            ClassLevelAccess::updateOrCreate(
                ['class_id' => $kelas->class_id, 'level_id' => $levelId],
                ['is_open' => true],
            );
        }
    }

    /**
     * Draf kode, refleksi, dan satu kartu bug yang sudah dirilis (idempoten).
     *
     * @param  array<string, Team>  $kelompok
     */
    private function seedDrafRefleksiDanBug(array $kelompok): void
    {
        $misiL1 = Mission::query()->where('unlock_code', 'KOP-L1-BUDI')->first();
        $level1 = Level::query()->where('level_number', 1)->value('level_id');
        $guru = User::query()->where('role', 'guru')->value('id');

        foreach ($kelompok as $namaTim => $team) {
            $anggota = TeamMember::query()
                ->where('team_id', $team->team_id)
                ->orderBy('team_member_id')
                ->pluck('student_id')
                ->values();

            if ($anggota->isEmpty()) {
                continue;
            }

            // Draf program utama untuk misi L1.
            if ($misiL1 !== null) {
                CodeDraft::updateOrCreate(
                    [
                        'team_id' => $team->team_id,
                        'mission_id' => $misiL1->mission_id,
                        'function_name' => CodeDraft::PROGRAM_UTAMA,
                    ],
                    [
                        'code_text' => $this->kodeContoh($misiL1->mission_id, $namaTim === 'Kelompok Echo'),
                        'owner_student_id' => $anggota->first(),
                        'updated_by' => $anggota->first(),
                        'version' => 1,
                    ],
                );

                // Refleksi pribadi untuk tiap anggota (level 1).
                foreach ($anggota as $studentId) {
                    Reflection::updateOrCreate(
                        [
                            'student_id' => $studentId,
                            'mission_id' => $misiL1->mission_id,
                            'type' => Reflection::TYPE_REFLEKSI,
                        ],
                        [
                            'team_id' => $team->team_id,
                            'body' => 'Saya belajar memakai variabel untuk menyimpan nilai pelanggan dan harga, lalu menghitung totalnya.',
                        ],
                    );
                }
            }

            // Kartu bug demo: pilih HANYA kartu yang punya kasus uji, lalu rilis
            // dengan tiga keadaan berbeda agar UI bisa diuji:
            //   1) belum dikerjakan, 2) sudah pakai petunjuk, 3) sudah solved.
            // Diambil dari beberapa level agar ketiga keadaan benar-benar ada.
            $kartu = BugCard::query()
                ->whereHas('testCases')
                ->orderBy('level_id')
                ->orderBy('bug_id')
                ->get()
                ->values();

            $keadaan = [
                ['solved' => false, 'hints' => 0],
                ['solved' => false, 'hints' => 1],
                ['solved' => true, 'hints' => 0],
            ];

            foreach ($kartu->take(3) as $urutan => $satu) {
                $status = $keadaan[$urutan % count($keadaan)];

                BugAttempt::updateOrCreate(
                    ['bug_id' => $satu->bug_id, 'team_id' => $team->team_id],
                    [
                        'released_by' => $guru,
                        'released_at' => now()->subMinutes(5),
                        'fixed_code' => $status['solved'] ? $satu->fix_explanation : null,
                        'is_solved' => $status['solved'],
                        'solved_at' => $status['solved'] ? now()->subMinutes(3) : null,
                        'grading_status' => $status['solved'] ? BugAttempt::STATUS_DINILAI : BugAttempt::STATUS_BELUM,
                        'hints_used' => $status['hints'],
                        'attempted_at' => now()->subMinutes(5),
                    ],
                );
            }
        }
    }

    /**
     * 24 siswa (6 kelompok x 4 anggota) — nama fiktif, idempoten.
     *
     * @return array<int, Student>
     */
    private function seedSiswa(SchoolClass $kelas): array
    {
        $nama = [
            'Adi Nugroho', 'Bella Safira', 'Cahyo Pratama', 'Dewi Lestari',
            'Eko Wijaya', 'Fitri Handayani', 'Galih Saputra', 'Hana Maulida',
            'Irfan Maulana', 'Jihan Aulia', 'Krisna Bayu', 'Laras Widya',
            'Maya Anggraini', 'Nanda Kusuma', 'Oka Mahendra', 'Putri Amelia',
            'Qori Ramadhan', 'Rani Oktaviani', 'Satria Yudha', 'Tika Purnama',
            'Umar Hidayat', 'Vina Marlina', 'Wahyu Setiawan', 'Yuni Kartika',
        ];

        $hasil = [];

        foreach ($nama as $urutan => $satu) {
            $hasil[] = Student::updateOrCreate(
                [
                    'class_id' => $kelas->class_id,
                    'student_no' => sprintf('D%03d', $urutan + 1),
                ],
                ['full_name' => $satu],
            );
        }

        return $hasil;
    }

    /**
     * Enam kelompok (4 anggota masing-masing) dengan karakter berbeda.
     *
     * @param  array<int, Student>  $siswa
     * @return array<string, Team> kunci = nama kelompok
     */
    private function seedKelompok(SchoolClass $kelas, array $siswa): array
    {
        $nama = ['Kelompok Alpha', 'Kelompok Bravo', 'Kelompok Charlie', 'Kelompok Delta', 'Kelompok Echo', 'Kelompok Foxtrot'];

        $hasil = [];

        foreach ($nama as $indeks => $namaTim) {
            $team = Team::updateOrCreate(
                [
                    'class_id' => $kelas->class_id,
                    'team_name' => $namaTim,
                ],
            );

            foreach (array_slice($siswa, $indeks * 4, 4) as $anggota) {
                TeamMember::updateOrCreate(
                    ['team_id' => $team->team_id, 'student_id' => $anggota->student_id],
                );
            }

            $hasil[$namaTim] = $team;
        }

        return $hasil;
    }

    /**
     * Beri peran (Coder/Tester/Analyst/Reviewer) ke anggota tiap kelompok.
     *
     * @param  array<string, Team>  $kelompok
     */
    private function seedPenugasanPeran(array $kelompok): void
    {
        $roleId = Role::query()->pluck('role_id', 'role_name');

        $urutanPeran = ['Coder', 'Tester', 'Analyst', 'Reviewer'];

        $level1 = Level::query()->where('level_number', 1)->value('level_id');

        if ($level1 === null) {
            return;
        }

        foreach ($kelompok as $team) {
            $anggota = TeamMember::query()
                ->where('team_id', $team->team_id)
                ->orderBy('team_member_id')
                ->pluck('student_id')
                ->values();

            foreach ($anggota as $posisi => $studentId) {
                $namaPeran = $urutanPeran[$posisi % count($urutanPeran)];

                if (! isset($roleId[$namaPeran])) {
                    continue;
                }

                RoleAssignment::updateOrCreate(
                    [
                        'team_id' => $team->team_id,
                        'student_id' => $studentId,
                        'level_id' => $level1,
                    ],
                    ['role_id' => $roleId[$namaPeran]],
                );
            }
        }
    }

    /**
     * Skenario permainan enam kelompok, sengaja dibuat beragam agar semua
     * kondisi UI Fase 4 bisa dilihat:
     *
     *   Alpha   — belum mulai sama sekali
     *   Bravo   — sudah merencanakan, belum pernah submit
     *   Charlie — mengerjakan level 1, belum lulus
     *   Delta   — lulus level 1 & 2 (rencana memilih Loop, kode tanpa loop)
     *   Echo    — lulus level 1-3, sedang mengerjakan misi sumatif level 4
     *   Foxtrot — MACET: 5 submission gagal beruntun pada level 1
     *
     * @param  array<string, Team>  $kelompok
     */
    private function seedPermainan(array $kelompok): void
    {
        $misi = Mission::query()->pluck('mission_id', 'unlock_code');

        if ($misi->count() < 4) {
            return;
        }

        // Seeder harus aman dijalankan berulang: bersihkan dulu seluruh data
        // permainan kelas demo (submission, poin, bug, rencana, nilai manual),
        // lalu bangun ulang dari nol agar angka tidak menumpuk.
        $teamIds = collect($kelompok)->pluck('team_id')->map(fn ($id) => (int) $id)->all();
        $submissionIds = Submission::query()->whereIn('team_id', $teamIds)->pluck('submission_id')->all();

        if ($submissionIds !== []) {
            SubmissionResult::query()->whereIn('submission_id', $submissionIds)->delete();
        }

        ScoreEntry::query()->whereIn('team_id', $teamIds)->delete();
        BugAttempt::query()->whereIn('team_id', $teamIds)->delete();
        FunctionOwnership::query()->whereIn('team_id', $teamIds)->delete();
        Submission::query()->whereIn('team_id', $teamIds)->delete();

        $studentIds = TeamMember::query()->whereIn('team_id', $teamIds)->pluck('student_id')->all();
        ManualScore::query()->whereIn('student_id', $studentIds)->delete();

        // ---- Skenario waktu RELATIF terhadap now() ----
        // Tujuannya agar panel "Progres Kelompok" menunjukkan variasi nyata:
        //   Alpha   : belum mulai            (tanpa baris sama sekali)
        //   Bravo   : aktif 3 menit lalu     (rencana saja, belum submit)
        //   Charlie : aktif 1 menit lalu     (sedang mengerjakan)
        //   Delta   : aktif 5 menit lalu     (lulus L1 & L2)
        //   Echo    : aktif 2 menit lalu     (lulus L1-L3, mengerjakan L4)
        //   Foxtrot : MACET                  (gagal 5x, terakhir 45 menit lalu)
        $menit = static fn (int $n): Carbon => Carbon::now()->subMinutes($n);

        // ---- Alpha: belum mulai (tidak ada rencana, tidak ada submission) ----

        // ---- Bravo: merencanakan level 1, belum submit ----
        $this->rencana($kelompok['Kelompok Bravo'], (int) $misi['KOP-L1-BUDI'], ['Variabel', 'Operator'], $menit(3));

        // ---- Charlie: mengerjakan level 1, 2x gagal ----
        $this->rencana($kelompok['Kelompok Charlie'], (int) $misi['KOP-L1-BUDI'], ['Variabel'], $menit(3));
        $this->submission($kelompok['Kelompok Charlie'], (int) $misi['KOP-L1-BUDI'], 2, 3, $menit(2));
        $this->submission($kelompok['Kelompok Charlie'], (int) $misi['KOP-L1-BUDI'], 3, 3, $menit(1));
        $this->percobaanBug($kelompok['Kelompok Charlie'], 2, $menit(2));

        // ---- Delta: lulus level 1 & 2 ----
        // Rencana level 2 memilih "Loop", tetapi kode L2 hanya memakai
        // assignment & percabangan → dashboard menampilkan
        // "dipilih tapi tidak dipakai" untuk konsep Loop.
        $this->rencana($kelompok['Kelompok Delta'], (int) $misi['KOP-L1-BUDI'], ['Variabel', 'Operator'], $menit(7));
        $this->submission($kelompok['Kelompok Delta'], (int) $misi['KOP-L1-BUDI'], 3, 3, $menit(6));

        $this->rencana(
            $kelompok['Kelompok Delta'],
            (int) $misi['KOP-L2-STATUS'],
            ['Variabel', 'If/Else', 'Operator', 'Loop'],
            $menit(6),
        );
        $this->submission(
            $kelompok['Kelompok Delta'],
            (int) $misi['KOP-L2-STATUS'],
            2,
            2,
            $menit(5),
            "saldo = 250000\n\nif saldo <= 0:\n    status_pinjaman = \"lunas\"\nelse:\n    status_pinjaman = \"menunggu\"\n\nprint(status_pinjaman)",
        );
        $this->percobaanBug($kelompok['Kelompok Delta'], 4, $menit(5));

        // ---- Echo: lulus 1-3, mengerjakan misi sumatif level 4 ----
        // Rencana level 4 TIDAK memilih "Function", padahal kodenya memakai def,
        // sehingga muncul "dipakai tapi tidak direncanakan".
        $this->rencana($kelompok['Kelompok Echo'], (int) $misi['KOP-L1-BUDI'], ['Variabel'], $menit(8));
        $this->submission($kelompok['Kelompok Echo'], (int) $misi['KOP-L1-BUDI'], 3, 3, $menit(7));

        $this->rencana($kelompok['Kelompok Echo'], (int) $misi['KOP-L2-STATUS'], ['If/Else'], $menit(6));
        $this->submission($kelompok['Kelompok Echo'], (int) $misi['KOP-L2-STATUS'], 2, 2, $menit(5));

        $this->rencana($kelompok['Kelompok Echo'], (int) $misi['KOP-L3-LOOP'], ['Loop', 'Variabel'], $menit(4));
        $this->submission($kelompok['Kelompok Echo'], (int) $misi['KOP-L3-LOOP'], 2, 2, $menit(3));

        $this->rencana($kelompok['Kelompok Echo'], (int) $misi['KOP-L4-KASIR'], ['Variabel', 'If/Else', 'Loop'], $menit(3));
        $this->submission(
            $kelompok['Kelompok Echo'],
            (int) $misi['KOP-L4-KASIR'],
            3,
            5,
            $menit(2),
            "def hitung_bunga(saldo, persen):\n    return saldo * persen / 100\n\n\ndef cek_status(saldo):\n    if saldo <= 0:\n        return \"Data tidak valid\"\n    if saldo >= 10000000:\n        return \"Gold\"\n    if saldo >= 5000000:\n        return \"Silver\"\n    return \"Reguler\"\n\n\ndef boleh_pinjam(saldo):\n    return cek_status(saldo) in (\"Gold\", \"Silver\")",
        );
        $this->kepemilikan($kelompok['Kelompok Echo'], (int) $misi['KOP-L4-KASIR']);
        $this->nilaiManual($kelompok['Kelompok Echo'], (int) $misi['KOP-L4-KASIR']);
        $this->percobaanBug($kelompok['Kelompok Echo'], 8, $menit(2));

        // ---- Fase 6: antrean verifikasi guru ----
        // Echo mengirim versi sumatif yang SUDAH dinilai perangkat tetapi belum
        // diverifikasi guru (tanpa poin). Satu di antaranya punya SELISIH:
        // perangkat mengaku lulus sebuah kasus yang sebenarnya gagal.
        $this->submissionMenungguVerifikasi(
            $kelompok['Kelompok Echo'],
            (int) $misi['KOP-L4-KASIR'],
            lulus: 11,
            waktu: $menit(2),
            kode: $this->kodeAcuanLevel4(),
        );

        $this->submissionMenungguVerifikasi(
            $kelompok['Kelompok Echo'],
            (int) $misi['KOP-L4-KASIR'],
            lulus: 12,
            waktu: $menit(1),
            kode: $this->kodeAcuanLevel4(),
            selisih: true,
        );

        // Charlie sudah submit formatif, tetapi penilaian di perangkat belum
        // selesai (status 'menunggu' + nonce masih berlaku).
        $this->submissionMenungguPerangkat(
            $kelompok['Kelompok Charlie'],
            (int) $misi['KOP-L1-BUDI'],
            $menit(1),
        );

        // Satu kartu bug sumatif (level 4) menunggu verifikasi guru.
        $this->bugMenungguVerifikasi($kelompok['Kelompok Echo'], $menit(1));

        // ---- Foxtrot: MACET — 5 submission gagal beruntun pada level 1 ----
        // Semua percobaan sengaja berumur > ambang macet (10 menit) agar
        // kelompok ini terdeteksi "5 kali gagal beruntun".
        $this->rencana($kelompok['Kelompok Foxtrot'], (int) $misi['KOP-L1-BUDI'], ['Variabel', 'Operator'], $menit(50));

        for ($percobaan = 1; $percobaan <= 5; $percobaan++) {
            // Selalu 2 dari 3 kasus lulus → semuanya GAGAL, sehingga kelompok
            // ini memenuhi syarat "5 kali gagal beruntun" (macet).
            $this->submission(
                $kelompok['Kelompok Foxtrot'],
                (int) $misi['KOP-L1-BUDI'],
                2,
                3,
                $menit(45 + (5 - $percobaan)),
            );
        }
    }

    /**
     * Rencana misi + pilihan kartu konsep.
     *
     * @param  array<int, string>  $konsep
     */
    private function rencana(Team $team, int $missionId, array $konsep, Carbon $waktu): MissionPlan
    {
        $rencana = MissionPlan::updateOrCreate(
            ['team_id' => $team->team_id, 'mission_id' => $missionId],
            ['planned_at' => $waktu, 'locked_at' => $waktu],
        );

        $conceptIds = ConceptCard::query()->whereIn('concept_name', $konsep)->pluck('concept_id')->all();

        $rencana->concepts()->sync($conceptIds);

        return $rencana;
    }

    /**
     * Submission + hasil kasus ujinya (yang pertama $lulus dianggap lulus).
     *
     * Kode disimpan apa adanya sebagai teks Python sederhana; isinya dipilih
     * lewat $kode agar kondisi demo (mis. konsep dipakai/dipilih) bisa tepat.
     */
    private function submission(Team $team, int $missionId, int $lulus, int $total, Carbon $waktu, ?string $kode = null): Submission
    {
        $pengirim = (int) TeamMember::query()
            ->where('team_id', $team->team_id)
            ->orderBy('team_member_id')
            ->value('student_id');

        $kasus = TestCase::query()
            ->where('mission_id', $missionId)
            ->orderBy('test_case_id')
            ->get();

        $submission = Submission::create([
            'team_id' => $team->team_id,
            'mission_id' => $missionId,
            'submitted_by' => $pengirim,
            'code_text' => $kode ?? $this->kodeContoh($missionId, $lulus >= $total),
            'submitted_at' => $waktu,
            'all_passed' => $lulus >= $total,
            'status' => Submission::STATUS_DINILAI,
            'attempt_no' => (int) Submission::query()
                ->where('team_id', $team->team_id)
                ->where('mission_id', $missionId)
                ->count() + 1,
            'graded_at' => $waktu,
        ]);

        foreach ($kasus as $urutan => $satu) {
            $lulus = $urutan < $lulus;

            SubmissionResult::create([
                'submission_id' => $submission->submission_id,
                'test_case_id' => $satu->test_case_id,
                'is_passed' => $lulus,
                // Yang lulus: nilai sesuai harapan. Yang gagal: nilai masuk akal
                // yang BUKAN kunci (mis. hasil perhitungan keliru / tipe salah).
                'actual_value' => $lulus
                    ? (string) $satu->expected_value
                    : $this->nilaiGagalWajar((string) $satu->expected_value),
            ]);
        }

        // Ledger poin: hasil misi + bonus/penalti kelompok.
        ScoreEntry::create([
            'team_id' => $team->team_id,
            'mission_id' => $missionId,
            'source_type' => 'mission_result',
            'points' => $lulus * 10,
            'created_at' => $waktu,
        ]);

        if ($lulus >= $total) {
            ScoreEntry::create([
                'team_id' => $team->team_id,
                'mission_id' => $missionId,
                'source_type' => 'plan_bonus',
                'points' => (int) config('teater.plan_bonus_points', 10),
                'created_at' => $waktu,
            ]);
        }

        return $submission;
    }

    /**
     * Nilai "gagal" yang masuk akal untuk kode demo — BUKAN kunci jawaban.
     * Angka digeser sedikit; string/boolean diberi nilai tiruan.
     */
    private function nilaiGagalWajar(string $expected): string
    {
        if (is_numeric($expected)) {
            $angka = (float) $expected;

            // Geser ~10% agar terlihat seperti salah hitung, bukan nol polos.
            $geser = $angka !== 0.0 ? $angka * 0.9 : 1.0;

            return str_contains($expected, '.')
                ? (string) round($geser, 1)
                : (string) (int) round($geser);
        }

        return match ($expected) {
            'True' => 'False',
            'False' => 'True',
            default => 'nilai_tiruan',
        };
    }

    /**
     * Kepemilikan fungsi: satu fungsi per anggota kelompok (maksimal sebanyak
     * fungsi yang ada pada misi tersebut).
     */
    private function kepemilikan(Team $team, int $missionId): void
    {
        $anggota = TeamMember::query()
            ->where('team_id', $team->team_id)
            ->orderBy('team_member_id')
            ->pluck('student_id')
            ->values();

        // Nama fungsi mengikuti check_target misi (mis. hitung_total(1, 5000)).
        $namaFungsi = TestCase::query()
            ->where('mission_id', $missionId)
            ->pluck('check_target')
            ->map(fn ($target) => preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', (string) $target, $cocok) === 1 ? $cocok[1] : null)
            ->filter()
            ->unique()
            ->values();

        if ($namaFungsi->isEmpty()) {
            return;
        }

        // Satu fungsi tepat satu pemilik per kelompok per misi (unique index),
        // jadi jumlah baris dibatasi oleh jumlah fungsi yang tersedia.
        foreach ($namaFungsi as $posisi => $nama) {
            $studentId = $anggota->get($posisi);

            if ($studentId === null) {
                break;
            }

            FunctionOwnership::updateOrCreate(
                [
                    'mission_id' => $missionId,
                    'team_id' => $team->team_id,
                    'function_name' => $nama,
                ],
                ['student_id' => $studentId],
            );
        }
    }

    /**
     * Nilai manual (refleksi & observasi) untuk seluruh anggota kelompok Echo
     * pada misi sumatif. Beberapa siswa sengaja dibiarkan kosong agar baris
     * "Sementara" tetap terlihat di halaman Rekap Nilai Sumatif.
     */
    private function nilaiManual(Team $team, int $missionId): void
    {
        $anggota = TeamMember::query()
            ->where('team_id', $team->team_id)
            ->orderBy('team_member_id')
            ->pluck('student_id')
            ->values();

        $penilai = User::query()->where('role', 'guru')->value('id');

        foreach ($anggota as $posisi => $studentId) {
            // Anggota ke-4 sengaja belum dinilai.
            if ($posisi === 3) {
                continue;
            }

            ManualScore::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'mission_id' => $missionId,
                ],
                [
                    'reflection_score' => 80 + ($posisi * 5),
                    'observation_score' => 85 + ($posisi * 3),
                    'notes' => $posisi === 0
                        ? 'Aktif membantu teman satu kelompok saat debugging.'
                        : null,
                    'graded_by' => $penilai,
                ],
            );
        }
    }

    /**
     * Percobaan kartu bug + poinnya (bonus saat terpecahkan, penalti petunjuk).
     *
     * Hanya kartu yang punya kasus uji yang dipakai, dan setiap baris diisi
     * released_at/grading_status agar konsisten dengan alur Fase 5.
     */
    private function percobaanBug(Team $team, int $jumlah, Carbon $waktu): void
    {
        $kartu = BugCard::query()
            ->whereHas('testCases')
            ->orderBy('bug_id')
            ->limit($jumlah)
            ->get();

        if ($kartu->isEmpty()) {
            return;
        }

        $guru = User::query()->where('role', 'guru')->value('id');

        // Sebar percobaan ke BELAKANG dari $waktu (bukan ke depan) supaya
        // tidak ada timestamp di masa depan — percobaan terakhir = $waktu.
        $selang = max(1, (int) floor(30 / max(1, $kartu->count())));

        foreach ($kartu as $urutan => $satu) {
            $selesai = $urutan % 2 === 0;

            $kapan = $waktu->copy()->subMinutes(($kartu->count() - 1 - $urutan) * $selang);

            BugAttempt::updateOrCreate(
                [
                    'bug_id' => $satu->bug_id,
                    'team_id' => $team->team_id,
                ],
                [
                    'released_by' => $guru,
                    'released_at' => $kapan,
                    'fixed_code' => $selesai ? $satu->fix_explanation : null,
                    'is_solved' => $selesai,
                    'solved_at' => $selesai ? $kapan : null,
                    'grading_status' => $selesai ? BugAttempt::STATUS_DINILAI : BugAttempt::STATUS_BELUM,
                    'hints_used' => $selesai ? 0 : 1,
                    'attempted_at' => $kapan,
                ],
            );

            if ($selesai) {
                ScoreEntry::create([
                    'team_id' => $team->team_id,
                    'bug_id' => $satu->bug_id,
                    'source_type' => 'bug_bonus',
                    'points' => (int) $satu->points,
                    'created_at' => $kapan,
                ]);
            } else {
                ScoreEntry::create([
                    'team_id' => $team->team_id,
                    'bug_id' => $satu->bug_id,
                    'source_type' => 'hint_penalty',
                    'points' => -5,
                    'created_at' => $kapan,
                ]);
            }
        }
    }

    /**
     * Kode acuan Level 4 (jawaban benar) — dipakai data demo verifikasi.
     */
    private function kodeAcuanLevel4(): string
    {
        return "def hitung_bunga(saldo, persen):\n"
            ."    return saldo * persen / 100\n\n"
            ."def cek_status(saldo):\n"
            ."    if saldo < 0:\n"
            ."        return \"Data tidak valid\"\n"
            ."    elif saldo >= 10_000_000:\n"
            ."        return \"Gold\"\n"
            ."    elif saldo >= 5_000_000:\n"
            ."        return \"Silver\"\n"
            ."    else:\n"
            ."        return \"Reguler\"\n\n"
            ."def boleh_pinjam(saldo):\n"
            ."    return cek_status(saldo) in (\"Gold\", \"Silver\")\n\n"
            ."def hitung_jumlah(daftar_saldo, status):\n"
            ."    n = 0\n"
            ."    for s in daftar_saldo:\n"
            ."        if cek_status(s) == status:\n"
            ."            n += 1\n"
            ."    return n\n\n"
            ."def simulasi_tabungan(saldo_awal, setoran_bulanan, bulan, persen_bunga):\n"
            ."    saldo = saldo_awal\n"
            ."    for _ in range(bulan):\n"
            ."        saldo += setoran_bulanan\n"
            ."        saldo += hitung_bunga(saldo, persen_bunga)\n"
            .'    return saldo';
    }

    /**
     * Submission SUMATIF yang sudah dinilai perangkat tetapi belum diverifikasi
     * guru (status 'menunggu_verifikasi').
     *
     * PENTING: helper ini TIDAK membuat ScoreEntry — poin hanya boleh diberikan
     * setelah guru memverifikasi (VerifikasiHasil). Inilah bedanya dengan
     * submission() biasa yang selalu 'dinilai' + poin.
     *
     * @param  int  $lulus  jumlah kasus uji yang lulus (sisanya gagal)
     * @param  bool  $selisih  tandai satu kasus sebagai selisih klaim perangkat
     */
    private function submissionMenungguVerifikasi(
        Team $team,
        int $missionId,
        int $lulus,
        Carbon $waktu,
        string $kode,
        bool $selisih = false,
    ): Submission {
        $pengirim = (int) TeamMember::query()
            ->where('team_id', $team->team_id)
            ->orderBy('team_member_id')
            ->value('student_id');

        $kasus = TestCase::query()
            ->where('mission_id', $missionId)
            ->orderBy('test_case_id')
            ->get();

        $submission = Submission::create([
            'team_id' => $team->team_id,
            'mission_id' => $missionId,
            'submitted_by' => $pengirim,
            'code_text' => $kode,
            'submitted_at' => $waktu,
            'all_passed' => false,
            'status' => Submission::STATUS_MENUNGGU_VERIFIKASI,
            'grading_source' => Submission::SUMBER_KLIEN,
            'client_report' => [
                'keluaran' => "Gold: 1\nSilver: 3\nReguler: 1\n",
                'galat' => null,
                'durasi_ms' => 412,
                'versi_pyodide' => (string) config('teater.pyodide.version', '314.0.7'),
                'jumlah_hasil' => $kasus->count(),
                'diterima_pada' => $waktu->toIso8601String(),
            ],
            'client_runtime_ms' => 412,
            'pyodide_version' => (string) config('teater.pyodide.version', '314.0.7'),
            'attempt_no' => (int) Submission::query()
                ->where('team_id', $team->team_id)
                ->where('mission_id', $missionId)
                ->count() + 1,
            'graded_at' => $waktu,
            'has_mismatch' => $selisih,
            'mismatch_detail' => $selisih ? $this->detailSelisihDemo($kasus, $lulus) : null,
        ]);

        foreach ($kasus as $urutan => $satu) {
            $lulusKasus = $urutan < $lulus;

            SubmissionResult::create([
                'submission_id' => $submission->submission_id,
                'test_case_id' => $satu->test_case_id,
                'is_passed' => $lulusKasus,
                'actual_value' => $lulusKasus
                    ? (string) $satu->expected_value
                    : $this->nilaiGagalWajar((string) $satu->expected_value),
            ]);
        }

        return $submission;
    }

    /**
     * Detail selisih contoh: satu kasus GAGAL yang diklaim LULUS oleh perangkat.
     *
     * Bentuknya sama dengan mismatch_detail yang dihasilkan server, sehingga
     * UI guru bisa memakai satu tampilan untuk keduanya.
     *
     * @param  Collection<int, TestCase>  $kasus
     * @return array<string, mixed>
     */
    private function detailSelisihDemo($kasus, int $lulus): array
    {
        $pertama = $kasus->get($lulus) ?? $kasus->first();

        return [
            'sumber' => 'perangkat-guru',
            'jumlah' => 1,
            'kasus' => [[
                'test_case_id' => (int) ($pertama?->test_case_id ?? 0),
                'sebelum' => [
                    'is_passed' => true,
                    'actual_value' => (string) ($pertama?->expected_value ?? ''),
                ],
                'sesudah' => [
                    'is_passed' => false,
                    'actual_value' => $this->nilaiGagalWajar((string) ($pertama?->expected_value ?? '')),
                ],
            ]],
            'dibandingkan_pada' => now()->toIso8601String(),
        ];
    }

    /**
     * Submission FORMATIF yang sudah dikirim tetapi penilaian di perangkat
     * belum selesai: status 'menunggu' + nonce masih berlaku.
     *
     * Tanpa hasil kasus uji (perangkat belum melaporkan apa pun) dan tanpa
     * poin — persis seperti kondisi setelah submit pada runner pyodide.
     */
    private function submissionMenungguPerangkat(Team $team, int $missionId, Carbon $waktu): Submission
    {
        $pengirim = (int) TeamMember::query()
            ->where('team_id', $team->team_id)
            ->orderBy('team_member_id')
            ->value('student_id');

        return Submission::create([
            'team_id' => $team->team_id,
            'mission_id' => $missionId,
            'submitted_by' => $pengirim,
            'code_text' => $this->kodeContoh($missionId, false),
            'submitted_at' => $waktu,
            'all_passed' => false,
            'status' => Submission::STATUS_MENUNGGU,
            'attempt_no' => (int) Submission::query()
                ->where('team_id', $team->team_id)
                ->where('mission_id', $missionId)
                ->count() + 1,
            'grading_nonce' => bin2hex(random_bytes(32)),
            'nonce_expires_at' => now()->addMinutes(
                (int) config('teater.pyodide.nonce_ttl_minutes', 15),
            ),
        ]);
    }

    /**
     * Satu percobaan Kartu Bug SUMATIF yang menunggu verifikasi guru.
     *
     * Memakai kartu pada level sumatif yang punya kasus uji, lalu mengisi hasil
     * per kasus dari kode perbaikan yang benar — TANPA poin (menunggu guru).
     */
    private function bugMenungguVerifikasi(Team $team, Carbon $waktu): void
    {
        $levelSumatif = Level::query()->where('is_summative', true)->value('level_id');

        $kartu = BugCard::query()
            ->when($levelSumatif !== null, fn ($q) => $q->where('level_id', $levelSumatif))
            ->whereHas('testCases')
            ->orderBy('bug_id')
            ->first();

        if ($kartu === null) {
            return;
        }

        $attempt = BugAttempt::updateOrCreate(
            ['bug_id' => $kartu->bug_id, 'team_id' => $team->team_id],
            [
                'released_by' => User::query()->where('role', 'guru')->value('id'),
                'released_at' => $waktu,
                'fixed_code' => $kartu->fix_explanation,
                'is_solved' => false,
                'solved_at' => null,
                'grading_status' => BugAttempt::STATUS_MENUNGGU_VERIFIKASI,
                'grading_source' => BugAttempt::SUMBER_KLIEN,
                'client_report' => [
                    'keluaran' => '',
                    'galat' => null,
                    'durasi_ms' => 180,
                    'versi_pyodide' => (string) config('teater.pyodide.version', '314.0.7'),
                ],
                'hints_used' => 0,
                'attempted_at' => $waktu,
            ],
        );

        foreach ($kartu->testCases as $satu) {
            $attempt->results()->updateOrCreate(
                ['attempt_id' => $attempt->attempt_id, 'bug_test_case_id' => $satu->id],
                ['is_passed' => true, 'actual_value' => (string) $satu->expected_value],
            );
        }
    }

    /**
     * Teks kode Python contoh per misi (untuk halaman Riwayat Submission &
     * analisis konsep). Isinya sengaja mengandung komentar/string yang
     * menyebut konsep agar heuristik ConceptUsageAnalyzer teruji.
     */
    private function kodeContoh(int $missionId, bool $lulus): string
    {
        $kode = Mission::query()->where('mission_id', $missionId)->value('unlock_code');

        return match ($kode) {
            'KOP-L1-BUDI' => $lulus
                ? "saldo_awal = 2_500_000\nsetoran_1 = 500_000\nsetoran_2 = 750_000\nsetoran_3 = 250_000\ntotal_setoran = setoran_1 + setoran_2 + setoran_3\nbunga = (saldo_awal + total_setoran) * 0.005\nsaldo_akhir = saldo_awal + total_setoran + bunga\npenarikan = 1_250_000\nlembar = penarikan // 100_000\nsisa = penarikan % 100_000"
                : "saldo_awal = 2_500_000\nsetoran_1 = 500_000\ntotal_setoran = setoran_1\nbunga = saldo_awal * 0.005",
            'KOP-L2-STATUS' => "if saldo < 0:\n    status = \"Data tidak valid\"\n    pinjaman = \"Tidak\"\nelif saldo >= 10_000_000:\n    status = \"Gold\"\n    pinjaman = \"Boleh\"\nelif saldo >= 5_000_000:\n    status = \"Silver\"\n    pinjaman = \"Boleh\"\nelse:\n    status = \"Reguler\"\n    pinjaman = \"Tidak\"",
            'KOP-L3-LOOP' => "saldo_tabungan = 1_000_000\nfor bulan in range(12):\n    saldo_tabungan += 300_000\n    saldo_tabungan += saldo_tabungan * 0.005\n\ndaftar_saldo = [12_000_000, 6_500_000, 3_000_000, 9_999_999, 5_000_000]\ntotal_saldo = 0\njumlah_gold = 0\njumlah_silver = 0\njumlah_reguler = 0\nfor saldo in daftar_saldo:\n    total_saldo += saldo\n    if saldo >= 10_000_000:\n        jumlah_gold += 1\n    elif saldo >= 5_000_000:\n        jumlah_silver += 1\n    else:\n        jumlah_reguler += 1",
            'KOP-L4-KASIR' => $lulus
                ? "def hitung_bunga(saldo, persen):\n    return saldo * persen / 100\n\ndef cek_status(saldo):\n    if saldo < 0:\n        return \"Data tidak valid\"\n    elif saldo >= 10_000_000:\n        return \"Gold\"\n    elif saldo >= 5_000_000:\n        return \"Silver\"\n    else:\n        return \"Reguler\"\n\ndef boleh_pinjam(saldo):\n    return cek_status(saldo) in [\"Gold\", \"Silver\"]\n\ndef hitung_jumlah(daftar_saldo, status):\n    jumlah = 0\n    for saldo in daftar_saldo:\n        if cek_status(saldo) == status:\n            jumlah += 1\n    return jumlah\n\ndef simulasi_tabungan(saldo_awal, setoran_bulanan, bulan, persen_bunga):\n    saldo = saldo_awal\n    for i in range(bulan):\n        saldo += setoran_bulanan\n        saldo += hitung_bunga(saldo, persen_bunga)\n    return saldo"
                : "def hitung_bunga(saldo, persen):\n    # belum dibagi 100\n    return saldo * persen\n\ndef cek_status(saldo):\n    return \"Reguler\"",
            default => 'print("kode demo")',
        };
    }
}
