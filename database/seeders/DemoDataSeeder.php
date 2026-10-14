<?php

namespace Database\Seeders;

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\ConceptCard;
use App\Models\FunctionOwnership;
use App\Models\GradingSetting;
use App\Models\Level;
use App\Models\ManualScore;
use App\Models\Mission;
use App\Models\MissionPlan;
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
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
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

    /**
     * Kode misi: [kode unlock, level, ringkas brief klien].
     *
     * @var array<int, array{0: string, 1: int, 2: string}>
     */
    private array $daftarMisi = [
        ['KOP-L1-BUDI', 1, "Klien: Budi, penjual bakso keliling.\n\nBudi ingin program sederhana yang menyimpan nama pelanggan dan harga semangkuk bakso, lalu menampilkan total bayar untuk 1 porsi."],
        ['KOP-L2-STATUS', 2, "Klien: Koperasi Sekolah.\n\nKoperasi ingin menentukan status pinjaman anggota: \"lunas\" bila saldo 0 atau kurang, \"menunggu\" bila saldo masih ada."],
        ['KOP-L3-LOOP', 3, "Klien: Bu Rina, pengelola kantin.\n\nBu Rina ingin program yang mencetak daftar harga 5 jenis makanan tanpa menulis print berulang kali."],
        ['KOP-L4-KASIR', 4, "Klien: Koperasi Sekolah (Ujian Sumatif).\n\nBuat fungsi hitung_total dan status_pinjaman untuk mesin kasir koperasi. Fungsi harus memakai perulangan dan percabangan agar dapat menghitung total belanja banyak barang."],
    ];

    /**
     * Kasus uji per misi: [label, setup_code, check_target, expected_value, toleransi].
     *
     * @var array<string, array<int, array{0: string, 1: string|null, 2: string, 3: string, 4: float}>>
     */
    private array $daftarKasus = [
        'KOP-L1-BUDI' => [
            ['Nama pelanggan', null, 'nama_pelanggan', '"Budi"', 0],
            ['Harga semangkuk', null, 'harga_bakso', '15000', 0],
            ['Total 1 porsi', 'harga_bakso = 15000', 'total_bayar', '15000', 0],
        ],
        'KOP-L2-STATUS' => [
            ['Pinjaman lunas', 'saldo = 0', 'status_pinjaman', '"lunas"', 0],
            ['Pinjaman menunggu', 'saldo = 500000', 'status_pinjaman', '"menunggu"', 0],
        ],
        'KOP-L3-LOOP' => [
            ['Daftar 5 harga', null, 'daftar_harga', '5', 0],
            ['Harga pertama', null, 'harga_pertama', '3000', 0],
        ],
        'KOP-L4-KASIR' => [
            ['Total satu barang', null, 'hitung_total(1, 5000)', '5000', 0],
            ['Total banyak barang', null, 'hitung_total(4, 2500)', '10000', 0],
            ['Total nol barang', null, 'hitung_total(0, 3000)', '0', 0],
            ['Status lunas', 'saldo = 0', 'status_pinjaman(0)', '"lunas"', 0],
            ['Status menunggu', null, 'status_pinjaman(250000)', '"menunggu"', 0],
        ],
    ];

    /**
     * Kartu bug: [level, judul, kode bermasalah, petunjuk, kunci, poin].
     *
     * @var array<int, array{0: int, 1: string, 2: string, 3: string, 4: string, 5: int}>
     */
    private array $daftarBug = [
        [1, 'Salah tulis variabel', "nama = \"Budi\"\nprint(nama_pelanggan)", 'Periksa nama variabel yang dicetak.', 'Nama variabel saat dibuat dan saat dipakai harus sama.', 10],
        [1, 'Kutip tidak ditutup', "nama = \"Budi\nprint(nama)", 'Perhatikan tanda kutip pada baris pertama.', 'Tutup string dengan tanda kutip pasangannya.', 10],
        [2, 'Salah operator perbandingan', "saldo = 0\nif saldo = 0:\n    print(\"lunas\")", 'Python membedakan = dan ==.', 'Gunakan == untuk membandingkan nilai.', 10],
        [2, 'Kondisi terbalik', "saldo = 500000\nif saldo == 0:\n    print(\"menunggu\")\nelse:\n    print(\"lunas\")", 'Cek ulang cabang mana yang benar.', 'Pertukarkan isi cabang if dan else.', 10],
        [3, 'Perulangan tidak jalan', "for i in range(0):\n    print(3000)", 'Berapa kali range(0) mengulang?', 'range(5) mengulang lima kali.', 15],
        [3, 'Indentasi hilang', "for i in range(5):\nprint(3000)", 'Python sangat ketat soal indentasi.', 'Isi perulangan harus menjorok ke dalam.', 15],
        [4, 'Akumulator tidak ditambah', "def hitung_total(jumlah, harga):\n    total = 0\n    for i in range(jumlah):\n        total = harga\n    return total", 'Hitung total belanja banyak barang.', 'Tambahkan harga ke total, bukan menggantinya (total += harga).', 20],
        [4, 'Fungsi tidak mengembalikan nilai', "def status_pinjaman(saldo):\n    if saldo <= 0:\n        status = \"lunas\"", 'Fungsi tidak memberi hasil ke pemanggil.', 'Tambahkan return pada setiap cabang.', 20],
    ];

    public function run(): void
    {
        $this->namaKelas = (string) config('teater.demo_class_name', '[DEMO] X RPL');

        DB::transaction(function (): void {
            $this->seedMateriMinimal();
            $this->seedKelas();
        });

        if ($this->command !== null) {
            $this->command->info('Data demo Fase 4 siap. Hapus dengan: php artisan teater:hapus-demo');
        }
    }

    /**
     * Materi minimal (level, misi, kasus uji, kartu bug) — hanya dibuat bila
     * belum ada, memakai updateOrCreate agar tidak menimpa materi guru.
     */
    private function seedMateriMinimal(): void
    {
        // Level 1-4 (hanya level 4 sumatif) — idempoten.
        foreach ([1, 2, 3, 4] as $nomor) {
            Level::updateOrCreate(
                ['level_number' => $nomor],
                [
                    'level_name' => 'Level '.$nomor,
                    'is_summative' => $nomor === 4,
                ],
            );
        }

        // Kartu konsep dasar.
        foreach (['Variabel', 'Operator', 'If/Else', 'Loop', 'Function'] as $namaKonsep) {
            ConceptCard::firstOrCreate(['concept_name' => $namaKonsep]);
        }

        $levelPerNomor = Level::query()->pluck('level_id', 'level_number');

        foreach ($this->daftarMisi as [$kode, $nomorLevel, $brief]) {
            $misi = Mission::updateOrCreate(
                ['unlock_code' => $kode],
                [
                    'level_id' => $levelPerNomor[$nomorLevel],
                    'client_brief' => $brief,
                ],
            );

            foreach ($this->daftarKasus[$kode] as $urutan => [$label, $setup, $target, $expected, $toleransi]) {
                TestCase::updateOrCreate(
                    [
                        'mission_id' => $misi->mission_id,
                        'label' => $label,
                    ],
                    [
                        'setup_code' => $setup,
                        'check_target' => $target,
                        'expected_value' => $expected,
                        'tolerance' => $toleransi,
                    ],
                );
            }
        }

        $levelPerNomor = Level::query()->pluck('level_id', 'level_number');

        foreach ($this->daftarBug as [$nomorLevel, $judul, $kode, $petunjuk, $kunci, $poin]) {
            BugCard::updateOrCreate(
                [
                    'level_id' => $levelPerNomor[$nomorLevel],
                    'bug_title' => $judul,
                ],
                [
                    'buggy_code' => $kode,
                    'hint_text' => $petunjuk,
                    'fix_explanation' => $kunci,
                    'points' => $poin,
                ],
            );
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
            ['planned_at' => $waktu],
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
        ]);

        foreach ($kasus as $urutan => $satu) {
            SubmissionResult::create([
                'submission_id' => $submission->submission_id,
                'test_case_id' => $satu->test_case_id,
                'is_passed' => $urutan < $lulus,
                'actual_value' => $urutan < $lulus ? (string) $satu->expected_value : 'None',
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
     */
    private function percobaanBug(Team $team, int $jumlah, Carbon $waktu): void
    {
        $kartu = BugCard::query()->orderBy('bug_id')->limit($jumlah)->get();

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
                    'fixed_code' => $selesai ? $satu->fix_explanation : null,
                    'is_solved' => $selesai,
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
     * Teks kode Python contoh per misi (untuk halaman Riwayat Submission &
     * analisis konsep). Isinya sengaja mengandung komentar/string yang
     * menyebut konsep agar heuristik ConceptUsageAnalyzer teruji.
     */
    private function kodeContoh(int $missionId, bool $lulus): string
    {
        $kode = Mission::query()->where('mission_id', $missionId)->value('unlock_code');

        return match ($kode) {
            'KOP-L1-BUDI' => $lulus
                ? "nama_pelanggan = \"Budi\"\nharga_bakso = 15000\ntotal_bayar = harga_bakso * 1\nprint(nama_pelanggan, total_bayar)"
                : "nama = \"Budi\"\nharga_bakso = 15000\nprint(nama)",
            'KOP-L2-STATUS' => "saldo = 0\n\nif saldo <= 0:\n    status_pinjaman = \"lunas\"\nelse:\n    status_pinjaman = \"menunggu\"\n\nprint(status_pinjaman)",
            'KOP-L3-LOOP' => "daftar_harga = []\n\nfor i in range(5):\n    daftar_harga.append(3000)\n\nharga_pertama = daftar_harga[0]",
            'KOP-L4-KASIR' => $lulus
                ? "def hitung_total(jumlah, harga):\n    total = 0\n    for i in range(jumlah):\n        total += harga\n    return total\n\n\ndef status_pinjaman(saldo):\n    if saldo <= 0:\n        return \"lunas\"\n    else:\n        return \"menunggu\""
                : "def hitung_total(jumlah, harga):\n    # belum memakai perulangan\n    return harga",
            default => 'print("kode demo")',
        };
    }
}
