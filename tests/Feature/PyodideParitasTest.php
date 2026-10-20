<?php

use App\Actions\BandingkanNilai;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Paritas pembanding: PHP (server) vs port JS (uji Node).
 *
 * Uji runtime Pyodide berjalan di Node dan memakai port JS dari
 * BandingkanNilai. Test ini memastikan keduanya TIDAK menyimpang: matriks
 * kasus yang sama dihitung PHP, lalu hasilnya dibandingkan dengan keluaran
 * Node (tests/Pyodide/paritas-pembanding.mjs).
 *
 * Bila Node tidak tersedia, test GAGAL (bukan di-skip) — sesuai aturan
 * "tidak boleh ada skip tanpa alasan tertulis".
 */
uses(RefreshDatabase::class);

/**
 * Matriks kasus: [laporan, expected, tolerance, hasil yang diharapkan].
 *
 * @return array<int, array{0: array<string, mixed>, 1: string, 2: mixed, 3: bool}>
 */
function matriksPembanding(): array
{
    return [
        [['tipe' => 'int', 'nilai' => 4780850], '4780850', 1, true],
        [['tipe' => 'int', 'nilai' => 4780851], '4780850', 1, true],
        [['tipe' => 'int', 'nilai' => 4780852], '4780850', 1, false],
        [['tipe' => 'float', 'nilai' => 12500.0], '12500.0', 0.01, true],
        [['tipe' => 'float', 'nilai' => 20000.0], '20000.0', 0, true],
        [['tipe' => 'int', 'nilai' => 12], '12', 0, true],
        [['tipe' => 'int', 'nilai' => 36499999], '36499999', 0, true],
        [['tipe' => 'float', 'nilai' => 36499999.0], '36499999', 0, true],
        [['tipe' => 'str', 'nilai' => 'Gold'], '"Gold"', 0, true],
        [['tipe' => 'str', 'nilai' => 'gold'], '"Gold"', 0, false],
        [['tipe' => 'str', 'nilai' => 'Data tidak valid'], '"Data tidak valid"', 0, true],
        [['tipe' => 'bool', 'nilai' => true], 'True', 0, true],
        [['tipe' => 'bool', 'nilai' => false], 'False', 0, true],
        // bool TIDAK dianggap angka.
        [['tipe' => 'bool', 'nilai' => true], '1', 0, false],
        [['tipe' => 'bool', 'nilai' => false], '0', 0, false],
        [['tipe' => 'none', 'nilai' => null], 'None', 0, true],
        [['tipe' => 'none', 'nilai' => null], '0', 0, false],
        [['tipe' => 'int', 'nilai' => 0], '0', 0, true],
        [['tipe' => 'str', 'nilai' => '1'], '1', 0, false],
        [['tipe' => 'lain', 'nilai' => '[1, 2]'], '1', 0, false],
        [['tipe' => 'belum_dibuat', 'nilai' => null], '1', 0, false],
        // Toleransi desimal.
        [['tipe' => 'float', 'nilai' => 100.004], '100.0', 0.01, true],
        [['tipe' => 'float', 'nilai' => 100.02], '100.0', 0.01, false],
        // String berisi escape.
        [['tipe' => 'str', 'nilai' => "baris1\nbaris2"], '"baris1\\nbaris2"', 0, true],
    ];
}

test('port JS pembanding menghasilkan hasil identik dengan PHP', function () {
    $banding = new BandingkanNilai;
    $harapan = [];

    foreach (matriksPembanding() as $i => [$laporan, $expected, $tolerance, $cocok]) {
        $hasilPhp = $banding->cocok($laporan, $expected, $tolerance);

        expect($hasilPhp)->toBe($cocok, "Kasus #{$i} salah di PHP.");

        $harapan[] = $hasilPhp;
    }

    // Kirim matriks ke Node dan minta hasil port JS.
    $berkas = storage_path('app/paritas-pembanding.json');

    file_put_contents($berkas, json_encode([
        'kasus' => array_map(fn (array $k) => [
            'laporan' => $k[0],
            'expected' => $k[1],
            'tolerance' => $k[2],
        ], matriksPembanding()),
        'harapan' => $harapan,
    ]));

    $skrip = base_path('tests/Pyodide/paritas-pembanding.mjs');

    $keluaran = shell_exec('node '.escapeshellarg($skrip).' '.escapeshellarg($berkas).' 2>&1');

    expect($keluaran)->not->toBeNull('Node tidak bisa dijalankan.');
    expect(trim((string) $keluaran))->toBe('PARITAS OK', 'Keluaran Node: '.$keluaran);

    @unlink($berkas);
});
