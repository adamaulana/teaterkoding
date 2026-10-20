<?php

namespace App\Services;

/**
 * Pembuatan & normalisasi KODE AKSES siswa (login tanpa akun & tanpa kata sandi).
 *
 * Aturan kode:
 * - 8 karakter dari alfabet TANPA karakter membingungkan (tanpa 0, O, 1, I, L).
 * - Disimpan TANPA tanda hubung, ditampilkan sebagai XXXX-XXXX.
 * - Normalisasi saat dicek: huruf besar, buang spasi & tanda hubung.
 */
final class AccessCodeService
{
    /** Alfabet aman: huruf tanpa O/I/L, angka tanpa 0/1. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const PANJANG = 8;

    /**
     * Buat kode akses acak 8 karakter (unik) tanpa tanda hubung.
     */
    public function generate(): string
    {
        $maks = strlen(self::ALPHABET) - 1;
        $kode = '';

        for ($i = 0; $i < self::PANJANG; $i++) {
            $kode .= self::ALPHABET[random_int(0, $maks)];
        }

        return $kode;
    }

    /**
     * Normalisasi input siswa: huruf besar, buang spasi, tab, baris baru, dan
     * tanda hubung. Dipakai untuk membandingkan dengan kolom access_code.
     */
    public function normalisasi(string $kode): string
    {
        $kode = mb_strtoupper(trim($kode));

        return str_replace(['-', ' ', "\t", "\n", "\r"], '', $kode);
    }

    /**
     * Format tampilan XXXX-XXXX (hanya bila panjangnya tepat 8).
     */
    public function display(string $kode): string
    {
        $kode = $this->normalisasi($kode);

        if (strlen($kode) !== self::PANJANG) {
            return $kode;
        }

        return substr($kode, 0, 4).'-'.substr($kode, 4, 4);
    }

    /**
     * Panjang kode standar.
     */
    public function panjang(): int
    {
        return self::PANJANG;
    }
}
