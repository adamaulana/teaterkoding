<?php

namespace Database\Seeders;

use App\Actions\ValidasiKasusUji;
use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\BugTestCase;
use App\Models\ConceptCard;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SubmissionResult;
use App\Models\TestCase;
use Illuminate\Database\Seeder;

/**
 * MATERI KOPERASI — SATU-SATUNYA SUMBER MATERI.
 *
 * Sumber kebenaran: dokumen "Kode Koperasi — Konten Lengkap Kartu Python Dasar
 * (Tema Keuangan)". Seeder ini mengisi:
 *   - level 1-4 (level 4 sumatif),
 *   - 5 kartu konsep beserta isi lengkapnya,
 *   - 4 misi (client_brief + starter_code),
 *   - kasus uji misi (Level 1: 5, Level 2: 16, Level 3: 5, Level 4: 13),
 *   - 8 kartu bug (kode/gejala/petunjuk/kunci + poin sesuai dokumen),
 *   - kasus uji tiap kartu bug.
 *
 * IDEMPOTEN. Karena kunci kartu bug adalah (level_id, bug_title), judul lama
 * dipetakan lebih dulu lewat UPDATE baris (mempertahankan bug_id) supaya
 * bug_attempts tidak lepas dan kartu tidak tergandakan. Kartu orifan tanpa
 * bug_attempts dihapus; yang masih punya attempt DIBIARKAN dan dilaporkan.
 *
 * Semua kasus uji (misi & bug) DIPERIKSA dengan App\Actions\ValidasiKasusUji;
 * bila ada yang melanggar, seeder GAGAL dengan laporan.
 */
class MateriKoperasiSeeder extends Seeder
{
    /**
     * 5 kartu konsep: nama => [fungsi, sintaks, kapan, jebakan, pertanyaan].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private array $kartuKonsep = [
        'Variabel' => [
            'Menyimpan data agar bisa dipakai ulang dan diberi nama yang bermakna.',
            'saldo = 2_500_000, nama = "Budi"',
            'Setiap data yang dipakai lebih dari sekali, atau yang nilainya bisa berubah.',
            'Nama tidak jelas (x, a); mengira "2500000" (teks) sama dengan 2500000 (angka).',
            'Data apa saja dalam pesanan Bu Rina yang perlu disimpan?',
        ],
        'Operator' => [
            'Melakukan perhitungan dan perbandingan.',
            '+ - * /, // (bagi bulat), % (sisa bagi), >= <= == !=',
            'Menghitung angka, atau membandingkan dua nilai untuk menghasilkan True/False.',
            'Tertukar // dan %; lupa urutan prioritas operasi (kali/bagi lebih dulu daripada tambah); memakai = padahal maksudnya ==.',
            'Bagian mana dari pesanan yang butuh menghitung, dan bagian mana yang butuh membandingkan?',
        ],
        'If/Else' => [
            'Membuat program memilih tindakan berdasarkan kondisi.',
            'if kondisi: … elif kondisi: … else:',
            'Ada aturan bertingkat atau beberapa kemungkinan hasil.',
            'Urutan kondisi salah (kondisi lebih luas ditaruh di atas sehingga yang sempit tidak pernah tercapai); salah pakai > vs >= di nilai batas.',
            'Ada berapa kemungkinan hasil? Kondisi mana yang harus dicek paling dulu?',
        ],
        'Loop' => [
            'Mengulang langkah yang sama tanpa menulis kode berulang.',
            'for x in daftar:, for i in range(12):, while kondisi:',
            'Memproses banyak data, atau langkah yang berulang dalam jumlah tertentu.',
            'Loop tak berhenti (while tanpa perubahan variabel); meletakkan total = 0 di dalam loop sehingga terus di-reset.',
            'Langkah mana yang diulang? Berapa kali? Variabel apa yang harus disiapkan sebelum loop dimulai?',
        ],
        'Function' => [
            'Membungkus langkah-langkah jadi satu perintah bernama yang bisa dipakai ulang.',
            'def nama(parameter): … return hasil',
            'Ada perhitungan atau aturan yang dipakai di banyak tempat.',
            'Memakai print() padahal maksudnya return (hasilnya tidak bisa dipakai lagi); lupa return di sebagian cabang if; return diletakkan di dalam loop sehingga langsung berhenti pada putaran pertama.',
            'Bagian mana yang dipakai berulang? Apa masukan (parameter) dan keluarannya?',
        ],
    ];

    /**
     * 4 misi: [kode_unlock, nomor_level, client_brief, starter_code].
     *
     * @var array<int, array{0: string, 1: int, 2: string, 3: string}>
     */
    private array $daftarMisi = [
        ['KOP-L1-BUDI', 1, <<<'TXT'
Klien: Bu Rina (Manajer Koperasi Sejahtera) — Level 1: "Buku Tabungan Budi"

Nasabah kami, Pak Budi, punya saldo awal Rp2.500.000. Bulan ini dia menyetor tiga kali: Rp500.000, Rp750.000, dan Rp250.000. Koperasi memberi bunga 0,5% dari saldo setelah semua setoran. Terakhir, Pak Budi mau menarik tunai Rp1.250.000 dalam pecahan Rp100.000. Tolong hitungkan semuanya.

Yang harus dihitung program:
1. Total setoran
2. Bunga bulan ini
3. Saldo akhir (setelah bunga)
4. Jumlah lembar Rp100.000 dan sisa uang yang harus diberikan dalam pecahan lain

Contoh output:
Total setoran: 1500000
Bunga: 20000.0
Saldo akhir: 4020000.0
Lembar 100rb: 12, sisa: 50000
TXT, <<<'PY'
# Buku Tabungan Budi
# Saldo awal Pak Budi
saldo_awal = 2_500_000

# Tiga kali setoran bulan ini
setoran_1 = 500_000
setoran_2 = 750_000
setoran_3 = 250_000

# Bunga 0,5% dan penarikan tunai
persen_bunga = 0.5
penarikan = 1_250_000

# Hitung dan simpan hasilnya pada variabel berikut:
# total_setoran = ...
# bunga = ...
# saldo_akhir = ...
# lembar = ...
# sisa = ...
PY],
        ['KOP-L2-STATUS', 2, <<<'TXT'
Klien: Bu Rina (Manajer Koperasi Sejahtera) — Level 2: "Penentu Status Nasabah"

Sekarang kami mau otomatis. Nasabah dengan saldo Rp10.000.000 ke atas berstatus Gold. Saldo Rp5.000.000 sampai di bawah Rp10.000.000 berstatus Silver. Di bawah itu Reguler. Hanya Gold dan Silver yang boleh mengajukan pinjaman. Oh ya, kalau ada input saldo negatif, itu pasti salah catat, tampilkan 'Data tidak valid'.

Contoh: saldo = 7_500_000 -> Status: Silver, Pinjaman: Boleh
TXT, <<<'PY'
# Saat pengujian, sistem MENYEDIAKAN variabel `saldo` untuk kodemu.
# Kamu tidak perlu menulis saldo = ... ; cukup pakai nilainya.
#
# Tentukan dua nilai berikut:
#   status  -> "Gold", "Silver", "Reguler", atau "Data tidak valid"
#   pinjaman -> "Boleh" atau "Tidak"
#
# Contoh: bila saldo >= 10_000_000 maka status = "Gold" dan pinjaman = "Boleh".

# status = ...
# pinjaman = ...
PY],
        ['KOP-L3-LOOP', 3, <<<'TXT'
Klien: Bu Rina (Manajer Koperasi Sejahtera) — Level 3: "Simulasi Setahun & Rekap Nasabah"

Ada dua permintaan. Pertama, simulasi tabungan: nasabah baru mulai dengan Rp1.000.000, lalu tiap bulan menyetor Rp300.000. Setiap bulan, setelah setoran, saldo mendapat bunga 0,5%. Berapa saldonya setelah 12 bulan? Kedua, ini daftar saldo lima nasabah kami: [12_000_000, 6_500_000, 3_000_000, 9_999_999, 5_000_000]. Tolong hitung total saldo semuanya, dan berapa nasabah Gold, Silver, dan Reguler.

Catatan: 9.999.999 termasuk Silver (bukan Gold).
TXT, <<<'PY'
# Simulasi tabungan 12 bulan
saldo_awal = 1_000_000
setoran_bulanan = 300_000
persen_bunga = 0.5
jumlah_bulan = 12

# Daftar saldo lima nasabah
daftar_saldo = [12_000_000, 6_500_000, 3_000_000, 9_999_999, 5_000_000]

# Hitung dan simpan hasilnya pada variabel berikut:
# saldo_tabungan = ...   (saldo setelah 12 bulan, toleransi ±1)
# total_saldo = ...
# jumlah_gold = ...
# jumlah_silver = ...
# jumlah_reguler = ...
PY],
        ['KOP-L4-KASIR', 4, <<<'TXT'
Klien: Bu Rina (Manajer Koperasi Sejahtera) — Level 4 FINAL (SUMATIF): "Program Kasir Koperasi"

Program kalian akan dipakai staf kami setiap hari, jadi jangan berupa satu blok kode panjang. Saya mau fungsi-fungsi terpisah supaya bisa dipakai ulang:

- hitung_bunga(saldo, persen) -> mengembalikan besar bunga
- cek_status(saldo) -> mengembalikan "Gold", "Silver", "Reguler", atau "Data tidak valid"
- boleh_pinjam(saldo) -> mengembalikan True atau False
- hitung_jumlah(daftar_saldo, status) -> mengembalikan jumlah nasabah dengan status tersebut
- simulasi_tabungan(saldo_awal, setoran_bulanan, bulan, persen_bunga) -> mengembalikan saldo setelah sekian bulan (setoran dulu, baru bunga tiap bulan)

Setelah semuanya jadi, jalankan semuanya untuk daftar nasabah dari pesanan sebelumnya.

Penilaian individu: setiap anggota mengerjakan fungsi miliknya sendiri. Skor individu = jumlah kasus uji lulus untuk fungsi miliknya.
TXT, <<<'PY'
# Program Utama: jalankan seluruh fungsi untuk daftar nasabah.
daftar_saldo = [12_000_000, 6_500_000, 3_000_000, 9_999_999, 5_000_000]

# print("Gold:", hitung_jumlah(daftar_saldo, "Gold"))
# print("Silver:", hitung_jumlah(daftar_saldo, "Silver"))
# print("Reguler:", hitung_jumlah(daftar_saldo, "Reguler"))

# === FUNGSI: hitung_bunga ===
def hitung_bunga(saldo, persen):
    pass

# === FUNGSI: cek_status ===
def cek_status(saldo):
    pass

# === FUNGSI: boleh_pinjam ===
def boleh_pinjam(saldo):
    pass

# === FUNGSI: hitung_jumlah ===
def hitung_jumlah(daftar_saldo, status):
    pass

# === FUNGSI: simulasi_tabungan ===
def simulasi_tabungan(saldo_awal, setoran_bulanan, bulan, persen_bunga):
    pass
PY],
    ];

    /**
     * Pemetaan judul LAMA -> judul DOKUMEN, per nomor level.
     *
     * Kunci kartu bug = (level_id, bug_title). Tanpa pemetaan ini, mengganti
     * judul akan membuat baris BARU dan menggandakan kartu.
     *
     * @var array<int, array<string, array<int, string>>>
     */
    private array $petaJudulLama = [
        1 => [
            'Saldo yang Berupa Teks' => ['Salah tulis variabel', 'Bunga setahun dihitung setengah'],
            'Pecahan Tertukar' => ['Kutip tidak ditutup', 'Pembagian lembar dan sisa tidak tepat'],
        ],
        2 => [
            'Gold yang Tak Pernah Muncul' => ['Salah operator perbandingan', 'Ambang status anggota salah'],
            'Batas yang Meleset' => ['Kondisi terbalik', 'Batas Gold dan Silver tertukar'],
        ],
        3 => [
            'Total yang Selalu Ulang dari Nol' => ['Perulangan tidak jalan', 'Total saldo tidak dijumlahkan'],
            'Bulan yang Tak Pernah Berganti' => ['Indentasi hilang', 'Sisa saldo tidak tepat'],
        ],
        4 => [
            'Fungsi yang Hanya Bicara' => ['Akumulator tidak ditambah', 'Simulasi tabungan salah'],
            'Return yang Terlalu Cepat' => ['Fungsi tidak mengembalikan nilai', 'Penghitung Gold salah'],
        ],
    ];

    /**
     * 8 kartu bug sesuai dokumen.
     *
     * Urutan: [nomor_level, judul, gejala, buggy_code, hint, fix, poin, kasus].
     * kasus = array of [label, setup_code, check_target, expected_value, toleransi].
     *
     * @var array<int, array{0: int, 1: string, 2: string, 3: string, 4: string, 5: string, 6: int, 7: array<int, array{0: string, 1: string|null, 2: string, 3: string, 4: float}>}>
     */
    private array $daftarBug = [
        [1, 'Saldo yang Berupa Teks',
            "Program berhenti dengan TypeError: can't multiply sequence by non-int of type 'float'.",
            "saldo = \"2500000\"\nbunga = saldo * 0.005\nprint(bunga)",
            'Perhatikan tanda kutip. Apa bedanya "2500000" dan 2500000?',
            'Data saldo berbentuk string. Perbaikan: saldo = 2500000 atau saldo = int("2500000").',
            10,
            [['Bunga dari saldo', null, 'bunga', '12500.0', 0.01]],
        ],
        [1, 'Pecahan Tertukar',
            'Output 50000 12, padahal harusnya 12 lembar dan sisa 50.000.',
            "penarikan = 1_250_000\nlembar = penarikan % 100_000\nsisa = penarikan // 100_000\nprint(lembar, sisa)",
            'Salah satu operator menghasilkan jumlah kelompok penuh, yang lain menghasilkan sisanya.',
            'Tukar % dan //: lembar = penarikan // 100_000, sisa = penarikan % 100_000.',
            10,
            [['Jumlah lembar', null, 'lembar', '12', 0], ['Sisa penarikan', null, 'sisa', '50000', 0]],
        ],
        [2, 'Gold yang Tak Pernah Muncul',
            'Nasabah dengan saldo 12 juta malah berstatus Silver.',
            "def status(saldo):\n    if saldo >= 5_000_000:\n        return \"Silver\"\n    elif saldo >= 10_000_000:\n        return \"Gold\"\n    else:\n        return \"Reguler\"\n\nprint(status(12_000_000))",
            'Python mengecek kondisi dari atas ke bawah dan berhenti di kondisi pertama yang benar. Apakah 12 juta memenuhi kondisi pertama?',
            'Urutan kondisi harus dari yang paling sempit ke yang paling luas: cek >= 10_000_000 (Gold) dulu, baru >= 5_000_000 (Silver).',
            15,
            [
                ['Gold 12 juta', null, 'status(12000000)', '"Gold"', 0],
                ['Silver 7 juta', null, 'status(7000000)', '"Silver"', 0],
                ['Reguler 1 juta', null, 'status(1000000)', '"Reguler"', 0],
            ],
        ],
        [2, 'Batas yang Meleset',
            'Output Silver Reguler, padahal nasabah dengan saldo tepat 10 juta seharusnya Gold dan tepat 5 juta seharusnya Silver.',
            "def status(saldo):\n    if saldo > 10_000_000:\n        return \"Gold\"\n    elif saldo > 5_000_000:\n        return \"Silver\"\n    else:\n        return \"Reguler\"\n\nprint(status(10_000_000), status(5_000_000))",
            'Klien menulis "10 juta ke atas". Apakah 10 juta termasuk "ke atas"?',
            'Ganti > dengan >= di kedua kondisi.',
            10,
            [
                ['Gold tepat 10 juta', null, 'status(10000000)', '"Gold"', 0],
                ['Silver tepat 5 juta', null, 'status(5000000)', '"Silver"', 0],
            ],
        ],

        [3, 'Total yang Selalu Ulang dari Nol',
            'Output 3000000, padahal totalnya 21.500.000.',
            "daftar = [12_000_000, 6_500_000, 3_000_000]\nfor saldo in daftar:\n    total = 0\n    total += saldo\nprint(total)",
            'Di mana total = 0 berada? Berapa kali baris itu dijalankan?',
            'Pindahkan total = 0 ke sebelum baris for.',
            15,
            [['Total saldo', null, 'total', '21500000', 0]],
        ],
        [3, 'Bulan yang Tak Pernah Berganti',
            'Program tidak pernah selesai (loop tak berhenti).',
            "saldo = 1_000_000\nbulan = 1\nwhile bulan <= 12:\n    saldo += 300_000\n    saldo += saldo * 0.005\nprint(saldo)",
            'Kondisi bulan <= 12 harus suatu saat jadi salah. Apa yang membuatnya berubah?',
            'Tambahkan bulan += 1 di dalam loop (atau ganti dengan for _ in range(12):).',
            15,
            [['Saldo setelah 12 bulan', null, 'saldo', '4780850', 1]],
        ],
        [4, 'Fungsi yang Hanya Bicara',
            "Program mencetak 20000.0, lalu error TypeError: unsupported operand type(s) for +: 'int' and 'NoneType'.",
            "def hitung_bunga(saldo, persen):\n    print(saldo * persen / 100)\n\nsaldo_akhir = 4_000_000 + hitung_bunga(4_000_000, 0.5)\nprint(saldo_akhir)",
            'print menampilkan nilai ke layar, tapi apa yang sebenarnya dikembalikan fungsi ini ke pemanggilnya?',
            'Ganti print(...) dengan return ....',
            20,
            [
                ['Bunga 0,5 persen', null, 'hitung_bunga(4000000, 0.5)', '20000.0', 0.01],
                ['Saldo akhir', null, 'saldo_akhir', '4020000.0', 0.01],
            ],
        ],
        [4, 'Return yang Terlalu Cepat',
            'Output 0, padahal ada 2 nasabah Gold.',
            "def jumlah_gold(daftar_saldo):\n    n = 0\n    for s in daftar_saldo:\n        if s >= 10_000_000:\n            n += 1\n        return n\n\nprint(jumlah_gold([3_000_000, 12_000_000, 15_000_000]))",
            'Perhatikan indentasi return n. Saat baris itu dijalankan, fungsi langsung berhenti. Di putaran ke berapa?',
            'Geser return n keluar dari loop (sejajar dengan for).',
            20,
            [['Jumlah Gold', null, 'jumlah_gold([3000000, 12000000, 15000000])', '2', 0]],
        ],
    ];

    /**
     * Kasus uji misi per kode unlock.
     *
     * Urutan: [label, setup_code, check_target, expected_value, toleransi].
     * Kunci pencocokan: (mission_id, label, setup_code).
     *
     * @var array<string, array<int, array{0: string, 1: string|null, 2: string, 3: string, 4: float}>>
     */
    private array $daftarKasusMisi = [
        'KOP-L1-BUDI' => [
            ['Total setoran', null, 'total_setoran', '1500000', 0],
            ['Bunga bulan ini', null, 'bunga', '20000.0', 0],
            ['Saldo akhir', null, 'saldo_akhir', '4020000.0', 0],
            ['Jumlah lembar 100rb', null, 'lembar', '12', 0],
            ['Sisa pecahan lain', null, 'sisa', '50000', 0],
        ],
        'KOP-L2-STATUS' => [
            ['Saldo 12 juta', 'saldo = 12000000', 'status', '"Gold"', 0],
            ['Pinjaman 12 juta', 'saldo = 12000000', 'pinjaman', '"Boleh"', 0],
            ['Saldo 10 juta', 'saldo = 10000000', 'status', '"Gold"', 0],
            ['Pinjaman 10 juta', 'saldo = 10000000', 'pinjaman', '"Boleh"', 0],
            ['Saldo 9.999.999', 'saldo = 9999999', 'status', '"Silver"', 0],
            ['Pinjaman 9.999.999', 'saldo = 9999999', 'pinjaman', '"Boleh"', 0],
            ['Saldo 7,5 juta', 'saldo = 7500000', 'status', '"Silver"', 0],
            ['Pinjaman 7,5 juta', 'saldo = 7500000', 'pinjaman', '"Boleh"', 0],
            ['Saldo 5 juta', 'saldo = 5000000', 'status', '"Silver"', 0],
            ['Pinjaman 5 juta', 'saldo = 5000000', 'pinjaman', '"Boleh"', 0],
            ['Saldo 4.999.999', 'saldo = 4999999', 'status', '"Reguler"', 0],
            ['Pinjaman 4.999.999', 'saldo = 4999999', 'pinjaman', '"Tidak"', 0],
            ['Saldo 0', 'saldo = 0', 'status', '"Reguler"', 0],
            ['Pinjaman 0', 'saldo = 0', 'pinjaman', '"Tidak"', 0],
            ['Saldo negatif', 'saldo = -1', 'status', '"Data tidak valid"', 0],
            ['Pinjaman saldo negatif', 'saldo = -1', 'pinjaman', '"Tidak"', 0],
        ],
        'KOP-L3-LOOP' => [
            ['Saldo tabungan 12 bulan', null, 'saldo_tabungan', '4780850', 1],
            ['Total saldo nasabah', null, 'total_saldo', '36499999', 0],
            ['Jumlah Gold', null, 'jumlah_gold', '1', 0],
            ['Jumlah Silver', null, 'jumlah_silver', '3', 0],
            ['Jumlah Reguler', null, 'jumlah_reguler', '1', 0],
        ],
        'KOP-L4-KASIR' => [
            ['Bunga 4 juta 0,5%', null, 'hitung_bunga(4000000, 0.5)', '20000.0', 0],
            ['Bunga 10 juta 1%', null, 'hitung_bunga(10000000, 1)', '100000.0', 0],
            ['Status 10 juta', null, 'cek_status(10000000)', '"Gold"', 0],
            ['Status 5 juta', null, 'cek_status(5000000)', '"Silver"', 0],
            ['Status 4.999.999', null, 'cek_status(4999999)', '"Reguler"', 0],
            ['Status negatif', null, 'cek_status(-1)', '"Data tidak valid"', 0],
            ['Boleh pinjam 7,5 juta', null, 'boleh_pinjam(7500000)', 'True', 0],
            ['Tidak boleh pinjam 3 juta', null, 'boleh_pinjam(3000000)', 'False', 0],
            ['Jumlah Silver', null, 'hitung_jumlah([12000000, 6500000, 3000000, 9999999, 5000000], "Silver")', '3', 0],
            ['Jumlah Gold daftar kosong', null, 'hitung_jumlah([], "Gold")', '0', 0],
            ['Simulasi 12 bulan', null, 'simulasi_tabungan(1000000, 300000, 12, 0.5)', '4780850', 1],
            ['Simulasi nol bulan', null, 'simulasi_tabungan(1000000, 0, 0, 0.5)', '1000000', 0],
            ['Simulasi 6 bulan', null, 'simulasi_tabungan(2000000, 500000, 6, 0.5)', '5113695', 1],
        ],
    ];

    public function run(): void
    {
        $this->seedLevelDanKonsep();
        $this->seedMisiDanKasus();
        $this->hapusKasusMisiOrifan();
        $this->petakanJudulBugLama();
        $this->seedBug();
        $this->hapusBugOrifan();
    }

    /**
     * Hapus kasus uji misi yang (label, setup_code) tidak dikenal dokumen —
     * sisa materi lama. Kasus yang sudah punya submission_results DIBIARKAN
     * dan dilaporkan supaya riwayat penilaian tidak rusak.
     */
    private function hapusKasusMisiOrifan(): void
    {
        $dilindungi = [];

        foreach ($this->daftarMisi as [$kode]) {
            $misi = Mission::query()->where('unlock_code', $kode)->first();

            if ($misi === null) {
                continue;
            }

            $kunciDokumen = collect($this->daftarKasusMisi[$kode])
                ->map(fn (array $k) => ($k[0] ?? '').'|'.(string) ($k[1] ?? ''))
                ->all();

            $orifan = TestCase::query()->where('mission_id', $misi->mission_id)->get();

            foreach ($orifan as $kasus) {
                $kunci = $kasus->label.'|'.(string) $kasus->setup_code;

                if (in_array($kunci, $kunciDokumen, true)) {
                    continue;
                }

                if (SubmissionResult::query()->where('test_case_id', $kasus->test_case_id)->exists()) {
                    $dilindungi[] = "{$kode} → \"{$kasus->label}\"";

                    continue;
                }

                $kasus->delete();
            }
        }

        if ($dilindungi !== [] && $this->command !== null) {
            $this->command->warn(
                'Kasus uji misi berikut TIDAK dihapus karena punya submission_results: '
                .implode(', ', $dilindungi),
            );
        }
    }

    /**
     * Level 1-4 + 5 kartu konsep berisi lengkap (idempoten).
     */
    private function seedLevelDanKonsep(): void
    {
        foreach ([1, 2, 3, 4] as $nomor) {
            Level::updateOrCreate(
                ['level_number' => $nomor],
                ['level_name' => 'Level '.$nomor, 'is_summative' => $nomor === 4],
            );
        }

        foreach ($this->kartuKonsep as $nama => [$fungsi, $sintaks, $kapan, $jebakan, $pertanyaan]) {
            ConceptCard::updateOrCreate(
                ['concept_name' => $nama],
                [
                    'function_desc' => $fungsi,
                    'syntax_example' => $sintaks,
                    'when_to_use' => $kapan,
                    'common_pitfalls' => $jebakan,
                    'trigger_question' => $pertanyaan,
                ],
            );
        }
    }

    /**
     * Misi (brief + starter_code) beserta kasus ujinya.
     */
    private function seedMisiDanKasus(): void
    {
        $levelPerNomor = Level::query()->pluck('level_id', 'level_number');
        $validator = new ValidasiKasusUji;
        $pelanggaran = [];

        foreach ($this->daftarMisi as [$kode, $nomorLevel, $brief, $starter]) {
            $misi = Mission::updateOrCreate(
                ['unlock_code' => $kode],
                [
                    'level_id' => $levelPerNomor[$nomorLevel],
                    'client_brief' => $brief,
                    'starter_code' => $starter,
                ],
            );

            foreach ($this->daftarKasusMisi[$kode] as [$label, $setup, $target, $expected, $toleransi]) {
                $pesan = $validator->periksa([
                    'label' => $label,
                    'setup_code' => $setup,
                    'check_target' => $target,
                    'expected_value' => $expected,
                    'tolerance' => $toleransi,
                ]);

                if ($pesan !== null) {
                    $pelanggaran[] = "Misi {$kode} → kasus \"{$label}\": {$pesan}";

                    continue;
                }

                TestCase::updateOrCreate(
                    [
                        'mission_id' => $misi->mission_id,
                        'label' => $label,
                        'setup_code' => $setup,
                    ],
                    [
                        'check_target' => $target,
                        'expected_value' => $expected,
                        'tolerance' => $toleransi,
                    ],
                );
            }
        }

        $this->laporPelanggaran($pelanggaran, 'kasus uji misi');
    }

    /**
     * Ubah judul kartu bug LAMA menjadi judul dokumen lewat UPDATE baris,
     * supaya bug_id (dan bug_attempts-nya) tetap menempel.
     */
    private function petakanJudulBugLama(): void
    {
        $levelPerNomor = Level::query()->pluck('level_id', 'level_number');

        foreach ($this->petaJudulLama as $nomorLevel => $peta) {
            $levelId = $levelPerNomor[$nomorLevel] ?? null;

            if ($levelId === null) {
                continue;
            }

            foreach ($peta as $judulBaru => $judulLamaList) {
                $sudahAda = BugCard::query()
                    ->where('level_id', $levelId)
                    ->where('bug_title', $judulBaru)
                    ->exists();

                if ($sudahAda) {
                    continue; // sudah pernah dipetakan
                }

                $kandidat = BugCard::query()
                    ->where('level_id', $levelId)
                    ->whereIn('bug_title', $judulLamaList)
                    ->orderBy('bug_id')
                    ->first();

                if ($kandidat !== null) {
                    $kandidat->update(['bug_title' => $judulBaru]);
                }
            }
        }
    }

    /**
     * Isi 8 kartu bug + symptom_text + kasus uji bug (idempoten).
     */
    private function seedBug(): void
    {
        $levelPerNomor = Level::query()->pluck('level_id', 'level_number');
        $validator = new ValidasiKasusUji;
        $pelanggaran = [];

        foreach ($this->daftarBug as [$nomorLevel, $judul, $gejala, $kode, $petunjuk, $kunci, $poin, $kasus]) {
            $kartu = BugCard::updateOrCreate(
                ['level_id' => $levelPerNomor[$nomorLevel], 'bug_title' => $judul],
                [
                    'symptom_text' => $gejala,
                    'buggy_code' => $kode,
                    'hint_text' => $petunjuk,
                    'fix_explanation' => $kunci,
                    'points' => $poin,
                ],
            );

            foreach ($kasus as [$label, $setup, $target, $expected, $toleransi]) {
                $pesan = $validator->periksa([
                    'label' => $label,
                    'setup_code' => $setup,
                    'check_target' => $target,
                    'expected_value' => $expected,
                    'tolerance' => $toleransi,
                ]);

                if ($pesan !== null) {
                    $pelanggaran[] = "Bug \"{$judul}\" → kasus \"{$label}\": {$pesan}";

                    continue;
                }

                BugTestCase::updateOrCreate(
                    ['bug_id' => $kartu->bug_id, 'label' => $label],
                    [
                        'setup_code' => $setup,
                        'check_target' => $target,
                        'expected_value' => $expected,
                        'tolerance' => $toleransi,
                    ],
                );
            }
        }

        $this->laporPelanggaran($pelanggaran, 'kasus uji bug');
    }

    /**
     * Hapus kartu bug yang judulnya tidak dikenal dokumen DAN tidak punya
     * bug_attempts. Kartu yang masih punya attempt DIBIARKAN dan dilaporkan.
     */
    private function hapusBugOrifan(): void
    {
        $judulDokumen = collect($this->daftarBug)->pluck(1)->all();

        $orifan = BugCard::query()->whereNotIn('bug_title', $judulDokumen)->get();

        $dilindungi = [];

        foreach ($orifan as $kartu) {
            if (BugAttempt::query()->where('bug_id', $kartu->bug_id)->exists()) {
                $dilindungi[] = "#{$kartu->bug_id} \"{$kartu->bug_title}\"";

                continue;
            }

            BugTestCase::query()->where('bug_id', $kartu->bug_id)->delete();
            $kartu->delete();
        }

        if ($dilindungi !== [] && $this->command !== null) {
            $this->command->warn(
                'Kartu bug berikut TIDAK dihapus karena masih punya bug_attempts: '
                .implode(', ', $dilindungi),
            );
        }
    }

    /**
     * @param  array<int, string>  $pelanggaran
     */
    private function laporPelanggaran(array $pelanggaran, string $konteks): void
    {
        if ($pelanggaran === []) {
            return;
        }

        throw new \RuntimeException(
            "Ada {$konteks} yang MELANGGAR aturan validasi Fase 3 dan TIDAK disimpan:".PHP_EOL
            .implode(PHP_EOL, $pelanggaran),
        );
    }
}
