<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Materi Game
|--------------------------------------------------------------------------
| Dipakai halaman Materi (Level, Misi & Kasus Uji, Kartu Konsep, Kartu Bug)
| dan seeder materi.
*/

return [

    /*
    | Prefix kode unlock misi. Ditaruh di config agar mudah diganti tanpa
    | menyentuh kode. Format kode otomatis: {PREFIX}-L{n}-{KATA}.
    */
    'unlock_prefix' => env('MATERI_UNLOCK_PREFIX', 'KOP'),

    /*
    | Kata pendek Bahasa Indonesia untuk generator kode unlock.
    | Dipilih yang mudah dieja dan tanpa huruf beraksen.
    */
    'unlock_words' => [
        'BUDI', 'STATUS', 'LOOP', 'KASIR', 'SALDO', 'TABUNG', 'PINJAM', 'ANGSUR',
        'SIMPAN', 'HITUNG', 'TOTAL', 'BAYAR', 'UNTUNG', 'LAPOR', 'MODAL', 'BUNGA',
        'TRANSAKSI', 'ANGGOTA', 'UNIT', 'SHU', 'SISA', 'JUAL', 'BELI', 'KOPERASI',
    ],

    /*
    | Ikon Tabler untuk tiap kartu konsep (nama kartu => nama ikon).
    | Nama ikon harus ada di scripts/generate-icons.mjs.
    */
    'concept_icons' => [
        'Variabel' => 'variable',
        'Operator' => 'math-function',
        'If/Else' => 'git-branch',
        'Loop' => 'repeat',
        'Function' => 'braces',
    ],

    /*
    | Ikon cadangan bila nama kartu tidak terdaftar di atas.
    */
    'concept_icon_default' => 'cards',

    /*
    | Batas validasi kasus uji (mengikuti panjang kolom skema).
    */
    'batas' => [
        'label' => 100,
        'setup_code' => 200,
        'check_target' => 100,
        'expected_value' => 100,
    ],

    /*
    | Token/kata terlarang pada check_target — mencegah eksekusi berbahaya
    | saat kasus uji dijalankan di sandbox pada tahap berikutnya.
    */
    'check_target_terlarang' => [
        'import', '__', 'exec', 'eval', 'open', 'os', 'sys', 'subprocess', 'input',
    ],

];
